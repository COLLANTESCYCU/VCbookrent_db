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

    public function rentBook($userId, $bookId, $durationDays, $cashReceived = null)
    {
        // Validations
        if (!$this->userModel->canRent($userId)) throw new Exception('User cannot rent at this time');
        if ($this->bookModel->available($bookId) < 1) throw new Exception('Book not available');
        if ($durationDays < 1) throw new Exception('Duration must be >= 1');

        // Get book details for validation
        $book = $this->bookModel->find($bookId);
        if (!$book) throw new Exception('Book not found');
        
        // Validate cash payment if provided
        if ($cashReceived !== null) {
            $bookPrice = (float)($book['price'] ?? 0);
            if ($bookPrice > 0 && (float)$cashReceived < $bookPrice) {
                throw new Exception('Cash amount must be >= ₱' . number_format($bookPrice, 2));
            }
        }

        $this->pdo->beginTransaction();
        try {
            // mark book rented
            if (!$this->bookModel->markRented($bookId)) throw new Exception('Failed to mark book as rented');

            $rentDate = (new DateTime())->format('Y-m-d H:i:s');
            $due = (new DateTime())->modify("+$durationDays days")->format('Y-m-d H:i:s');

            // Calculate change if cash was provided and price column exists
            $changeAmount = null;
            if ($cashReceived !== null) {
                $bookPrice = (float)($book['price'] ?? 0);
                if ($bookPrice > 0) {
                    $changeAmount = (float)$cashReceived - $bookPrice;
                }
            }

            // Try to insert with cash fields if they exist
            try {
                $stmt = $this->pdo->prepare('INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days, cash_received, change_amount) VALUES (:uid, :bid, :r, :d, :dur, :cash, :change)');
                $stmt->execute([
                    'uid'=>$userId, 
                    'bid'=>$bookId, 
                    'r'=>$rentDate, 
                    'd'=>$due, 
                    'dur'=>$durationDays,
                    'cash'=>$cashReceived,
                    'change'=>$changeAmount
                ]);
            } catch (Exception $e) {
                // cash_received/change_amount columns don't exist yet
                $stmt = $this->pdo->prepare('INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days) VALUES (:uid, :bid, :r, :d, :dur)');
                $stmt->execute([
                    'uid'=>$userId, 
                    'bid'=>$bookId, 
                    'r'=>$rentDate, 
                    'd'=>$due, 
                    'dur'=>$durationDays
                ]);
            }

            $rentalId = $this->pdo->lastInsertId();

            // Record transaction (handles missing table gracefully)
            $this->userModel->recordTransaction($userId, 'rent', "Rented book ID: $bookId", (float)($book['price'] ?? 0), $rentalId);

            // audit log
            $this->log(null, sprintf('User %d rented book %d for %d days', $userId, $bookId, $durationDays));

            $this->pdo->commit();
            return $rentalId;
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
        $stmt = $this->pdo->prepare('SELECT r.*, b.title, b.author, u.name as user_name FROM rentals r JOIN books b ON r.book_id = b.id JOIN users u ON u.id = r.user_id WHERE (r.status = "overdue" OR (r.status = "active" AND r.due_date < NOW())) ORDER BY r.due_date ASC');
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
            // restore book
            $this->bookModel->markReturned($r['book_id']);
            $this->log(null, sprintf('Rental %d cancelled', $rentalId));
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function returnBook($rentalId, $returnDate = null)
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

            // update book availability
            $this->bookModel->markReturned($r['book_id']);

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
}