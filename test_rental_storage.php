<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/RentalController.php';
require_once __DIR__ . '/src/Models/Book.php';
require_once __DIR__ . '/src/Models/Rental.php';
require_once __DIR__ . '/src/Database.php';

echo "=== RENTAL STORAGE TEST ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Get a sample user and book for testing
    echo "1. Checking if there are users and books in database...\n";
    
    $userStmt = $db->query('SELECT id, fullname FROM users LIMIT 1');
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "   ❌ No users found in database!\n";
        echo "   Please create a user first in the system.\n";
        exit;
    }
    echo "   ✅ Found user: {$user['fullname']} (ID: {$user['id']})\n";
    
    $bookStmt = $db->query('SELECT id, title, available_copies FROM books WHERE archived = 0 LIMIT 1');
    $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        echo "   ❌ No books found in database!\n";
        echo "   Please add a book first in the system.\n";
        exit;
    }
    echo "   ✅ Found book: {$book['title']} (ID: {$book['id']}, Available: {$book['available_copies']})\n\n";
    
    // Simulate a rental creation
    echo "2. Creating test rental...\n";
    
    $rental = new Rental();
    $rentalId = $rental->rentBook(
        userId: $user['id'],
        bookId: $book['id'],
        durationDays: 7,
        quantity: 1,
        cashReceived: null,
        paymentMethod: 'cash',
        cardDetails: [],
        onlineTxn: null
    );
    
    echo "   ✅ Rental created with ID: $rentalId\n\n";
    
    // Verify rental was stored
    echo "3. Verifying rental was stored in database...\n";
    
    $checkStmt = $db->prepare('SELECT * FROM rentals WHERE id = ?');
    $checkStmt->execute([$rentalId]);
    $storedRental = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($storedRental) {
        echo "   ✅ RENTAL IS STORED IN DATABASE!\n";
        echo "   Rental Details:\n";
        echo "   - ID: {$storedRental['id']}\n";
        echo "   - User ID: {$storedRental['user_id']}\n";
        echo "   - Book ID: {$storedRental['book_id']}\n";
        echo "   - Rent Date: {$storedRental['rent_date']}\n";
        echo "   - Due Date: {$storedRental['due_date']}\n";
        echo "   - Status: {$storedRental['status']}\n";
        echo "   - Quantity: {$storedRental['quantity']}\n";
        echo "   - Payment Method: {$storedRental['payment_method']}\n";
    } else {
        echo "   ❌ RENTAL NOT FOUND IN DATABASE!\n";
        echo "   The rental creation failed silently.\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END OF TEST ===\n";
