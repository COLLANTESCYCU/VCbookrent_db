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
        $sql = 'SELECT r.*, b.title as book_title, u.name as user_name, u.username, u.email FROM rentals r JOIN books b ON r.book_id = b.id JOIN users u ON r.user_id = u.id ORDER BY r.rent_date DESC';
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function rent($userId, $bookId, $duration, $cashReceived = null)
    {
        return $this->rental->rentBook($userId, $bookId, $duration, $cashReceived);
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