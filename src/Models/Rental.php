<?php
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Book.php';
require_once __DIR__ . '/Penalty.php';

class Rental
{
    private $pdo;
    private $userModel;
    private $bookModel;
    private $penaltyModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
        $this->userModel = new User();
        $this->bookModel = new Book();
        $this->penaltyModel = new Penalty();
    }

    public function rentBook($userId, $bookId, $durationDays, $quantity = 1, $cashReceived = null, $paymentMethod = null, $cardDetails = [], $onlineTxn = null)
    {
        // Validations
        if (!$this->userModel->canRent($userId)) throw new Exception('User cannot rent at this time');
        if ((int)$quantity < 1) throw new Exception('Quantity must be at least 1');
        if ($this->bookModel->available($bookId) < $quantity) throw new Exception('Not enough copies available. Available: ' . $this->bookModel->available($bookId));
        if ($durationDays < 1) throw new Exception('Duration must be >= 1');

        // Get book details for validation
        $book = $this->bookModel->find($bookId);
        if (!$book) throw new Exception('Book not found');
        
        // Clean and validate cash payment
        if ($cashReceived !== null) {
            // Strip currency symbols and whitespace, keep only digits and decimal point
            $cleanCash = preg_replace('/[^\d.]/', '', (string)$cashReceived);
            $cashReceived = (float)$cleanCash;
            
            // For cash payments, validate that amount is sufficient
            if ($paymentMethod === 'cash') {
                $bookPrice = (float)($book['price'] ?? 0);
                $totalPrice = $bookPrice * $quantity;
                if ($cashReceived < $totalPrice) {
                    throw new Exception('Insufficient cash. Total price: ₱' . number_format($totalPrice, 2) . ', Cash provided: ₱' . number_format($cashReceived, 2));
                }
            }
        }

        $this->pdo->beginTransaction();
        try {
            // NOTE: Books are NOT marked as rented yet - they will be marked when admin approves (status = 'active')
            // This allows the user to rent while pending admin approval without affecting inventory

            $rentDate = (new DateTime())->format('Y-m-d H:i:s');
            $due = (new DateTime())->modify("+$durationDays days")->format('Y-m-d H:i:s');

            // Calculate change if cash was provided and price column exists
            $changeAmount = null;
            if ($cashReceived !== null) {
                $bookPrice = (float)($book['price'] ?? 0);
                $totalPrice = $bookPrice * $quantity;
                if ($totalPrice > 0) {
                    $changeAmount = (float)$cashReceived - $totalPrice;
                }
            }

            // Try to insert with quantity column (handles both old and new schema)
            try {
                $stmt = $this->pdo->prepare('INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days, quantity, status, cash_received, change_amount, payment_method, card_number, card_holder, card_expiry, card_cvv, online_transaction_no) VALUES (:uid, :bid, :r, :d, :dur, :qty, :status, :cash, :change, :pmethod, :cnum, :cholder, :cexp, :ccvv, :otxn)');
                $stmt->execute([
                    'uid'=>$userId,
                    'bid'=>$bookId,
                    'r'=>$rentDate,
                    'd'=>$due,
                    'dur'=>$durationDays,
                    'qty'=>$quantity,
                    'status'=>'pending',
                    'cash'=>$cashReceived,
                    'change'=>$changeAmount,
                    'pmethod'=>$paymentMethod,
                    'cnum'=>$cardDetails['card_number'] ?? null,
                    'cholder'=>$cardDetails['card_holder'] ?? null,
                    'cexp'=>$cardDetails['card_expiry'] ?? null,
                    'ccvv'=>$cardDetails['card_cvv'] ?? null,
                    'otxn'=>$onlineTxn
                ]);
            } catch (Exception $colError) {
                // Fallback: quantity column doesn't exist yet, insert without it
                if (strpos($colError->getMessage(), 'Unknown column') !== false) {
                    $stmt = $this->pdo->prepare('INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days, status, cash_received, change_amount, payment_method, card_number, card_holder, card_expiry, card_cvv, online_transaction_no) VALUES (:uid, :bid, :r, :d, :dur, :status, :cash, :change, :pmethod, :cnum, :cholder, :cexp, :ccvv, :otxn)');
                    $stmt->execute([
                        'uid'=>$userId,
                        'bid'=>$bookId,
                        'r'=>$rentDate,
                        'd'=>$due,
                        'dur'=>$durationDays,
                        'status'=>'pending',
                        'cash'=>$cashReceived,
                        'change'=>$changeAmount,
                        'pmethod'=>$paymentMethod,
                        'cnum'=>$cardDetails['card_number'] ?? null,
                        'cholder'=>$cardDetails['card_holder'] ?? null,
                        'cexp'=>$cardDetails['card_expiry'] ?? null,
                        'ccvv'=>$cardDetails['card_cvv'] ?? null,
                        'otxn'=>$onlineTxn
                    ]);
                } else {
                    throw $colError;
                }
            }

            $rentalId = $this->pdo->lastInsertId();

            // Record transaction (handles missing table gracefully)
            $totalPrice = (float)($book['price'] ?? 0) * $quantity;
            $this->userModel->recordTransaction($userId, 'rent', "Rented $quantity copy/copies of book ID: $bookId", $totalPrice, $rentalId);

            // Record payment in tbl_payments (handles missing table gracefully)
            $this->recordPayment($rentalId, $userId, $totalPrice, $paymentMethod, $cashReceived, $cardDetails, $onlineTxn);

            // audit log
            $this->log(null, sprintf('User %d rented %d copy/copies of book %d for %d days', $userId, $quantity, $bookId, $durationDays));

            $this->pdo->commit();
            return $rentalId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function approveRental($rentalId)
    {
        // Get rental details
        $stmt = $this->pdo->prepare('SELECT * FROM rentals WHERE id = :id');
        $stmt->execute(['id' => $rentalId]);
        $rental = $stmt->fetch();
        
        if (!$rental) {
            throw new Exception('Rental not found');
        }
        
        if ($rental['status'] !== 'pending') {
            throw new Exception('Only pending rentals can be approved');
        }

        $this->pdo->beginTransaction();
        try {
            // Get quantity - use 1 if column doesn't exist or is NULL
            $qty = isset($rental['quantity']) && $rental['quantity'] ? (int)$rental['quantity'] : 1;
            
            // Try to mark books as rented, but don't fail approval if inventory is already depleted
            // (inventory may have been reduced by other processes)
            $inventoryUpdated = false;
            try {
                if ($this->bookModel->markRented($rental['book_id'], $qty)) {
                    $inventoryUpdated = true;
                }
            } catch (Exception $inventoryError) {
                // Log inventory error but continue with approval
                error_log('Warning: Could not update book inventory for rental ' . $rentalId . ': ' . $inventoryError->getMessage());
            }

            // Update rental status to 'active' regardless of inventory update
            $stmt = $this->pdo->prepare('UPDATE rentals SET status = "active" WHERE id = :id');
            $stmt->execute(['id' => $rentalId]);

            // Log the approval
            $logMsg = sprintf('Rental %d approved and marked as active. Book %d quantity %d', $rentalId, $rental['book_id'], $qty);
            if ($inventoryUpdated) {
                $logMsg .= ' marked as rented';
            } else {
                $logMsg .= ' (inventory already depleted or unavailable)';
            }
            $this->log(null, $logMsg);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getActiveRentalsForUser($userId)
    {
        $stmt = $this->pdo->prepare('SELECT r.*, b.title, b.author FROM rentals r JOIN books b ON r.book_id = b.id WHERE r.user_id = :uid AND r.status = "active"');
        $stmt->execute(['uid'=>$userId]);
        return $stmt->fetchAll();
    }

    public function getOverdueRentals()
    {
        $stmt = $this->pdo->prepare('SELECT r.*, b.title, b.author, u.fullname as user_name FROM rentals r JOIN books b ON r.book_id = b.id JOIN users u ON u.id = r.user_id WHERE r.status = "active" AND r.due_date < NOW() ORDER BY r.due_date ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markOverdueAndApplyPenalties()
    {
        // find active rentals past due
        $stmt = $this->pdo->prepare('SELECT * FROM rentals WHERE status = "active" AND due_date < NOW()');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $count = 0;
        $config = require __DIR__ . '/../config.php';
        foreach ($rows as $r) {
            $dueDateObj = new DateTime($r['due_date']);
            $now = new DateTime();
            $interval = $dueDateObj->diff($now);
            $overdueDays = (int)$interval->days;
            if ($overdueDays <= 0) continue;

            $this->pdo->beginTransaction();
            try {
                $penaltyId = null;
                if ($overdueDays > 0 && $config['settings']['penalties_enabled']) {
                    $penaltyId = $this->penaltyModel->createForRental($r['id'], $r['user_id'], $overdueDays);
                }
                $stmt2 = $this->pdo->prepare('UPDATE rentals SET status = "overdue", penalty_id = :pid WHERE id = :id');
                $stmt2->execute(['pid'=>$penaltyId, 'id'=>$r['id']]);

                $this->log(null, sprintf('Rental %d marked overdue (days: %d)', $r['id'], $overdueDays));
                $this->pdo->commit();
                $count++;
            } catch (Exception $e) {
                $this->pdo->rollBack();
            }
        }
        return $count;
    }

    public function cancelRental($rentalId)
    {
        // only before release (status active, and rent_date maybe same day) - business rule: allow cancel if still active and not yet released (we assume release is immediate on rent)
        $stmt = $this->pdo->prepare('SELECT * FROM rentals WHERE id = :id AND status = "active"');
        $stmt->execute(['id'=>$rentalId]);
        $r = $stmt->fetch();
        if (!$r) throw new Exception('Rental not cancelable');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE rentals SET status = "cancelled" WHERE id = :id');
            $stmt->execute(['id'=>$rentalId]);
            // restore book (by quantity rented)
            $quantity = (int)($r['quantity'] ?? 1);
            $this->bookModel->markReturned($r['book_id'], $quantity);
            $this->log(null, sprintf('Rental %d cancelled', $rentalId));
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }    public function returnBook($rentalId, $returnDate = null)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rentals WHERE id = :id AND status = "active"');
        $stmt->execute(['id'=>$rentalId]);
        $r = $stmt->fetch();
        if (!$r) throw new Exception('Rental not active or not found');

        $returnDateObj = $returnDate ? new DateTime($returnDate) : new DateTime();
        $rentDateObj = new DateTime($r['rent_date']);
        if ($returnDateObj < $rentDateObj) throw new Exception('Return date cannot be earlier than rent date');

        $dueDateObj = new DateTime($r['due_date']);
        $overdueDays = 0;
        if ($returnDateObj > $dueDateObj) {
            $interval = $dueDateObj->diff($returnDateObj);
            $overdueDays = (int)$interval->days;
        }

        $this->pdo->beginTransaction();
        try {
            // create penalty if overdue and enabled
            $config = require __DIR__ . '/../config.php';
            $penaltyId = null;
            $penaltyAmount = 0;
            if ($overdueDays > 0 && $config['settings']['penalties_enabled']) {
                $penaltyId = $this->penaltyModel->createForRental($r['id'], $r['user_id'], $overdueDays);
                $penaltyAmount = 10.00 * $overdueDays;
                // Record penalty transaction (handles missing table gracefully)
                $this->userModel->recordTransaction($r['user_id'], 'penalty', "Penalty for rental ID: $rentalId (overdue: $overdueDays days)", $penaltyAmount, $penaltyId);
            }

            // Try updating with all fields
            try {
                $stmt = $this->pdo->prepare('UPDATE rentals SET return_date = :rd, status = :st, penalty_id = :pid WHERE id = :id');
                $status = $overdueDays > 0 ? 'overdue' : 'returned';
                $stmt->execute(['rd'=>$returnDateObj->format('Y-m-d H:i:s'), 'st'=>$status, 'pid'=>$penaltyId, 'id'=>$rentalId]);
            } catch (Exception $e) {
                // Try without penalty_id if it doesn't exist
                $stmt = $this->pdo->prepare('UPDATE rentals SET return_date = :rd, status = :st WHERE id = :id');
                $status = $overdueDays > 0 ? 'overdue' : 'returned';
                $stmt->execute(['rd'=>$returnDateObj->format('Y-m-d H:i:s'), 'st'=>$status, 'id'=>$rentalId]);
            }

            // update book availability (by quantity rented)
            $quantity = (int)($r['quantity'] ?? 1);
            $this->bookModel->markReturned($r['book_id'], $quantity);

            // update user stats
            $this->userModel->incrementStatsAfterReturn($r['user_id'], $overdueDays > 0);

            // Record return transaction
            $this->userModel->recordTransaction($r['user_id'], 'return', "Returned rental ID: $rentalId", 0, $rentalId);

            $this->log(null, sprintf('Rental %d returned (overdue days: %d)', $rentalId, $overdueDays));

            $this->pdo->commit();
            return ['overdue_days'=>$overdueDays, 'penalty_id'=>$penaltyId, 'penalty_amount'=>$penaltyAmount];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getRentalsForUser($userId, $statusFilter = null)
    {
        $sql = 'SELECT r.*, b.title as book_title, b.price, b.isbn FROM rentals r JOIN books b ON r.book_id = b.id WHERE r.user_id = :uid';
        $params = ['uid'=>$userId];
        
        if ($statusFilter) {
            $sql .= ' AND r.status = :status';
            $params['status'] = $statusFilter;
        }
        
        $sql .= ' ORDER BY r.rent_date DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function log($userId, $action, $context = null)
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (user_id, action, context, ip) VALUES (:uid, :action, :context, :ip)');
        $stmt->execute(['uid'=>$userId, 'action'=>$action, 'context'=>$context, 'ip'=>$_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public function getRentalsOverTime($days = 30)
    {
        $stmt = $this->pdo->prepare("SELECT DATE(rent_date) as date, COUNT(*) as count FROM rentals WHERE rent_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY DATE(rent_date) ORDER BY date");
        $stmt->execute(['days'=>$days]);
        $data = $stmt->fetchAll();
        $active = $this->pdo->query("SELECT COUNT(*) as active_rentals FROM rentals WHERE status = 'active'")->fetch()['active_rentals'];
        return ['data'=>$data, 'active_rentals'=>$active];
    }

    /**
     * Record payment for a rental in tbl_payments
     * @param int $rentalId
     * @param int $userId
     * @param float $amountCharged - Total amount to be charged
     * @param string $paymentMethod - cash, card, online, check, or other
     * @param float|null $cashReceived - Amount of cash received (for cash payments)
     * @param array $cardDetails - Array with card_number, card_holder, card_expiry, card_cvv
     * @param string|null $onlineTxn - Online transaction number
     * @return bool - True if payment recorded successfully, false if table doesn't exist
     */
    public function recordPayment($rentalId, $userId, $amountCharged, $paymentMethod = null, $cashReceived = null, $cardDetails = [], $onlineTxn = null)
    {
        try {
            // Check if tbl_payments table exists
            $checkTable = $this->pdo->query("SHOW TABLES LIKE 'tbl_payments'")->fetch();
            if (!$checkTable) {
                // Table doesn't exist, log and return false gracefully
                error_log("tbl_payments table not found. Payment recording skipped for rental $rentalId");
                return false;
            }

            $paymentDate = (new DateTime())->format('Y-m-d H:i:s');
            $changeAmount = null;
            $paymentStatus = 'completed'; // Default to completed since we already validated payment

            // Calculate change if cash was provided
            if ($cashReceived !== null && $amountCharged > 0) {
                $changeAmount = (float)$cashReceived - (float)$amountCharged;
                if ($changeAmount < 0) $changeAmount = 0; // No negative change
            }

            // Prepare payment data based on payment method
            $paymentData = [
                'rental_id' => $rentalId,
                'user_id' => $userId,
                'amount_charged' => (float)$amountCharged,
                'amount_received' => $cashReceived ? (float)$cashReceived : (float)$amountCharged,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod ?? 'cash',
                'payment_status' => $paymentStatus,
                'payment_date' => $paymentDate,
                'cash_received' => ($paymentMethod === 'cash') ? (float)$cashReceived : null,
                'card_number' => ($paymentMethod === 'card') ? ($cardDetails['card_number'] ?? null) : null,
                'card_holder' => ($paymentMethod === 'card') ? ($cardDetails['card_holder'] ?? null) : null,
                'card_expiry' => ($paymentMethod === 'card') ? ($cardDetails['card_expiry'] ?? null) : null,
                'card_cvv' => ($paymentMethod === 'card') ? ($cardDetails['card_cvv'] ?? null) : null,
                'online_transaction_no' => ($paymentMethod === 'online') ? $onlineTxn : null,
                'online_gateway' => ($paymentMethod === 'online') ? 'payment_gateway' : null,
                'received_by' => null // Can be set to current admin if needed
            ];

            // Clean card number - keep last 4 digits only for security
            if ($paymentData['card_number']) {
                $paymentData['card_last_four'] = substr(str_replace(' ', '', $paymentData['card_number']), -4);
            }

            // Build the INSERT query
            $columns = [];
            $placeholders = [];
            $values = [];

            foreach ($paymentData as $key => $value) {
                if ($value !== null) {
                    $columns[] = $key;
                    $placeholder = ':' . $key;
                    $placeholders[] = $placeholder;
                    $values[$placeholder] = $value;
                }
            }

            $sql = 'INSERT INTO tbl_payments (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            error_log("Payment recorded for rental $rentalId: " . (float)$amountCharged . " via $paymentMethod");
            return true;

        } catch (Exception $e) {
            // Log the error but don't fail the rental creation
            error_log("Error recording payment for rental $rentalId: " . $e->getMessage());
            return false;
        }
    }
}