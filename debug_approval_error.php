<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Models/Book.php';
require_once __DIR__ . '/src/Models/Rental.php';
require_once __DIR__ . '/src/Database.php';

echo "=== TESTING APPROVAL ERROR ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Get a pending rental
    $rental = $db->query("SELECT * FROM rentals WHERE status = 'pending' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    if (!$rental) {
        echo "No pending rentals to test\n";
        
        // Create a test rental
        echo "Creating test rental...\n";
        $user = $db->query('SELECT id FROM users LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $book = $db->query('SELECT id FROM books WHERE archived = 0 AND available_copies > 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days, quantity, status, payment_method) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 7, 1, 'pending', 'cash')");
        $stmt->execute([$user['id'], $book['id']]);
        $rentalId = $db->lastInsertId();
        
        $rental = $db->query("SELECT * FROM rentals WHERE id = $rentalId")->fetch(PDO::FETCH_ASSOC);
    }
    
    echo "Found Rental ID: {$rental['id']}\n";
    echo "Book ID: {$rental['book_id']}\n";
    echo "Quantity: {$rental['quantity']}\n";
    echo "Status: {$rental['status']}\n\n";
    
    // Now try to approve it
    echo "Attempting to approve rental...\n\n";
    
    try {
        $rentalModel = new Rental();
        $rentalModel->approveRental($rental['id']);
        echo "✅ Rental approved successfully!\n";
    } catch (Exception $e) {
        echo "❌ Error approving rental: " . $e->getMessage() . "\n";
        echo "Full error: " . $e->getTraceAsString() . "\n";
        
        // Try to debug the markRented call
        echo "\n=== TESTING markRented DIRECTLY ===\n";
        $book = new Book();
        $qty = $rental['quantity'] ?? 1;
        echo "Attempting: bookModel->markRented({$rental['book_id']}, $qty)\n\n";
        
        try {
            $result = $book->markRented($rental['book_id'], $qty);
            echo "Result: " . ($result ? "true" : "false") . "\n";
            
            if (!$result) {
                // Check availability
                $bookData = $db->query("SELECT available_copies FROM books WHERE id = {$rental['book_id']}")->fetch(PDO::FETCH_ASSOC);
                echo "Available copies in DB: {$bookData['available_copies']}\n";
                echo "Trying to mark rented: $qty\n";
                echo "ERROR: Not enough copies available!\n";
            }
        } catch (Exception $e2) {
            echo "Error calling markRented: " . $e2->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
