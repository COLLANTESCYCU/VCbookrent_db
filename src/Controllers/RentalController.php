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
        // Join rentals, books, users for full info
        $pdo = \Database::getInstance()->pdo();
        $sql = 'SELECT r.*, b.title as book_title, u.fullname as user_name, u.contact_no, u.email, u.address FROM rentals r JOIN books b ON r.book_id = b.id JOIN users u ON r.user_id = u.id ORDER BY r.rent_date DESC';
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
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