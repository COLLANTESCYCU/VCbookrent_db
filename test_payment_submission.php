<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/RentalController.php';
require_once __DIR__ . '/src/Models/Rental.php';
require_once __DIR__ . '/src/Database.php';

echo "=== PAYMENT DATA SUBMISSION TEST ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Get a sample user and book
    $user = $db->query('SELECT id FROM users WHERE role = "user" LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $book = $db->query('SELECT id, price FROM books WHERE archived = 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$book) {
        echo "❌ Need a user and book to test\n";
        exit;
    }
    
    echo "Testing with User ID: {$user['id']}, Book ID: {$book['id']}\n\n";
    
    // Simulate POST data with payment information
    $_POST = [
        'user_id' => $user['id'],
        'book_id' => $book['id'],
        'duration' => 7,
        'quantity' => 1,
        'rent' => 1,
        'payment_method' => 'cash',
        'cash_received' => 1000.00,
        'card_number' => '4532015112830366',
        'card_holder' => 'Test User',
        'card_expiry' => '12/26',
        'card_cvv' => '123',
        'online_transaction_no' => 'TXN123456'
    ];
    
    echo "Submitting payment data:\n";
    foreach ($_POST as $key => $value) {
        if ($key !== 'card_cvv' && $key !== 'card_number') {
            echo "  - $key: $value\n";
        } else {
            echo "  - $key: [hidden]\n";
        }
    }
    echo "\n";
    
    // Call the rental controller
    $ctrl = new RentalController();
    $rentalId = $ctrl->rent(
        (int)$_POST['user_id'],
        (int)$_POST['book_id'],
        (int)$_POST['duration'],
        (int)$_POST['quantity'],
        (float)$_POST['cash_received']
    );
    
    echo "✅ Rental created with ID: $rentalId\n\n";
    
    // Check what was actually stored
    echo "Checking stored payment data in database:\n";
    $rental = $db->prepare('SELECT payment_method, cash_received, change_amount, card_number, card_holder, card_expiry, online_transaction_no FROM rentals WHERE id = ?');
    $rental->execute([$rentalId]);
    $data = $rental->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        echo "  - payment_method: " . ($data['payment_method'] ?? 'NULL') . "\n";
        echo "  - cash_received: " . ($data['cash_received'] ?? 'NULL') . "\n";
        echo "  - change_amount: " . ($data['change_amount'] ?? 'NULL') . "\n";
        echo "  - card_number: " . ($data['card_number'] ? '[STORED]' : 'NULL') . "\n";
        echo "  - card_holder: " . ($data['card_holder'] ?? 'NULL') . "\n";
        echo "  - card_expiry: " . ($data['card_expiry'] ?? 'NULL') . "\n";
        echo "  - online_transaction_no: " . ($data['online_transaction_no'] ?? 'NULL') . "\n";
        
        if ($data['payment_method'] && $data['cash_received']) {
            echo "\n✅ PAYMENT DATA IS BEING STORED!\n";
        } else {
            echo "\n⚠️  Payment method or cash data missing\n";
            echo "The payment details from the form are NOT being passed to the database.\n";
        }
    } else {
        echo "❌ Rental not found!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
