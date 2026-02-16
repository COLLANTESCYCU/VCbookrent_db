<?php
require_once __DIR__ . '/../Models/Rental.php';

class RentalController
{
    private $rental;
    public function __construct()
    {
        $this->rental = new Rental();
    }

    public function getAll()
    {
        // Join rentals, books, users for full info - use LEFT JOIN for robustness
        $pdo = \Database::getInstance()->pdo();
        
        // Build query with proper column selection
        // Note: users table has 'name' column (and optionally 'fullname'), contact is not contact_no
        $sql = '
            SELECT 
                r.id,
                r.user_id,
                r.book_id,
                r.rent_date,
                r.due_date,
                r.return_date,
                r.status,
                r.duration_days,
                r.quantity,
                r.payment_method,
                r.cash_received,
                r.change_amount,
                b.title as book_title,
                b.isbn,
                b.price,
                COALESCE(u.fullname, "Unknown") as user_name,
                COALESCE(u.email, "") as email,
                COALESCE(u.contact_no, "") as contact_no,
                COALESCE(u.address, "") as address
            FROM rentals r 
            LEFT JOIN books b ON r.book_id = b.id 
            LEFT JOIN users u ON r.user_id = u.id 
            ORDER BY r.rent_date DESC
        ';
        
        try {
            $stmt = $pdo->query($sql);
            $rentals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("RentalController::getAll() - Query successful, returned " . count($rentals) . " rentals");
            if (!empty($rentals)) {
                error_log("First rental status: " . ($rentals[0]['status'] ?? 'MISSING'));
                error_log("First rental user: " . ($rentals[0]['user_name'] ?? 'MISSING'));
            }
            
            return $rentals;
        } catch (\Exception $e) {
            error_log("RentalController::getAll() - Query failed: " . $e->getMessage());
            
            // Fallback: include user name even if join fails
            $sqlFallback = 'SELECT r.*, b.title as book_title, b.isbn, COALESCE(u.fullname, "Unknown") as user_name FROM rentals r LEFT JOIN books b ON r.book_id = b.id LEFT JOIN users u ON r.user_id = u.id ORDER BY r.rent_date DESC';
            $stmt = $pdo->query($sqlFallback);
            $rentals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("RentalController::getAll() - Fallback query returned " . count($rentals) . " rentals");
            
            return $rentals;
        }
    }

    public function rent($userId, $bookId, $duration, $quantity = 1, $cashReceived = null)
    {
        // Accept payment method and details
        $paymentMethod = $_POST['payment_method'] ?? null;
        $cardDetails = [
            'card_number' => $_POST['card_number'] ?? null,
            'card_holder' => $_POST['card_holder'] ?? null,
            'card_expiry' => $_POST['card_expiry'] ?? null,
            'card_cvv' => $_POST['card_cvv'] ?? null
        ];
        $onlineTxn = $_POST['online_transaction_no'] ?? null;
        return $this->rental->rentBook($userId, $bookId, $duration, $quantity, $cashReceived, $paymentMethod, $cardDetails, $onlineTxn);
    }

    public function activeForUser($userId)
    {
        return $this->rental->getActiveRentalsForUser($userId);
    }

    public function cancel($rentalId)
    {
        return $this->rental->cancelRental($rentalId);
    }

    public function doReturn($rentalId, $returnDate = null)
    {
        return $this->rental->returnBook($rentalId, $returnDate);
    }
}