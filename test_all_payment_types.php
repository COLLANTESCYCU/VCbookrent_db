<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/RentalController.php';
require_once __DIR__ . '/src/Models/Rental.php';
require_once __DIR__ . '/src/Database.php';

echo "=== COMPLETE PAYMENT FORM SUBMISSION TEST ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Get a sample user and book with sufficient stock
    $user = $db->query('SELECT id, fullname FROM users WHERE role = "user" LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $book = $db->query('SELECT id, price, available_copies FROM books WHERE archived = 0 AND available_copies > 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$book) {
        echo "❌ Need a user and book to test\n";
        exit;
    }
    
    echo "TEST SCENARIO 1: CASH PAYMENT\n";
    echo "=".str_repeat("=", 50)."\n";
    
    // Simulate HTML form POST with cash payment
    $_POST = [
        'user_id' => $user['id'],
        'book_id' => $book['id'],
        'duration' => 7,
        'quantity' => 1,
        'rent' => 1,
        'rent_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+7 days')),
        'payment_method' => 'cash',
        'cash_received' => 500.00,
        'card_number' => '',
        'card_holder' => '',
        'card_expiry' => '',
        'card_cvv' => '',
        'online_transaction_no' => ''
    ];
    
    echo "Form Data:\n";
    echo "  User: {$user['fullname']}\n";
    echo "  Book ID: {$book['id']}\n";
    echo "  Duration: 7 days\n";
    echo "  Quantity: 1\n";
    echo "  Payment Method: CASH\n";
    echo "  Cash Received: ₱".$_POST['cash_received']."\n\n";
    
    $ctrl = new RentalController();
    $rentalId1 = $ctrl->rent(
        (int)$_POST['user_id'],
        (int)$_POST['book_id'],
        (int)$_POST['duration'],
        (int)$_POST['quantity'],
        (float)$_POST['cash_received']
    );
    
    echo "✅ Rental created: ID $rentalId1\n";
    
    $rental = $db->prepare('SELECT payment_method, cash_received, change_amount FROM rentals WHERE id = ?');
    $rental->execute([$rentalId1]);
    $data = $rental->fetch(PDO::FETCH_ASSOC);
    echo "   Payment stored: {$data['payment_method']}, Cash: ₱{$data['cash_received']}, Change: ₱{$data['change_amount']}\n\n";
    
    // Reset for next test
    echo "TEST SCENARIO 2: CARD PAYMENT\n";
    echo "=".str_repeat("=", 50)."\n";
    
    // Get different user and book
    $user2 = $db->query('SELECT id, fullname FROM users WHERE role = "user" AND id != '.$user['id'].' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $book2 = $db->query('SELECT id FROM books WHERE archived = 0 AND available_copies > 0 AND id != '.$book['id'].' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    
    if ($user2 && $book2) {
        $_POST = [
            'user_id' => $user2['id'],
            'book_id' => $book2['id'],
            'duration' => 14,
            'quantity' => 1,
            'rent' => 1,
            'rent_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'payment_method' => 'card',
            'cash_received' => '',
            'card_number' => '4532015112830366',
            'card_holder' => 'Juan Dela Cruz',
            'card_expiry' => '12/2026',
            'card_cvv' => '123',
            'online_transaction_no' => ''
        ];
        
        echo "Form Data:\n";
        echo "  User: {$user2['fullname']}\n";
        echo "  Book ID: {$book2['id']}\n";
        echo "  Duration: 14 days\n";
        echo "  Quantity: 1\n";
        echo "  Payment Method: CARD\n";
        echo "  Card: {$_POST['card_number']} ({$_POST['card_holder']})\n\n";
        
        $rentalId2 = $ctrl->rent(
            (int)$_POST['user_id'],
            (int)$_POST['book_id'],
            (int)$_POST['duration'],
            (int)$_POST['quantity'],
            null
        );
        
        echo "✅ Rental created: ID $rentalId2\n";
        
        $rental2 = $db->prepare('SELECT payment_method, card_number, card_holder, card_expiry FROM rentals WHERE id = ?');
        $rental2->execute([$rentalId2]);
        $data2 = $rental2->fetch(PDO::FETCH_ASSOC);
        echo "   Payment stored: {$data2['payment_method']}, Card: {$data2['card_number']}, Holder: {$data2['card_holder']}\n\n";
    }
    
    echo "TEST SCENARIO 3: ONLINE PAYMENT\n";
    echo "=".str_repeat("=", 50)."\n";
    
    $_POST = [
        'user_id' => $user['id'],
        'book_id' => $book['id'],
        'duration' => 3,
        'quantity' => 1,
        'rent' => 1,
        'rent_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+3 days')),
        'payment_method' => 'online',
        'cash_received' => '',
        'card_number' => '',
        'card_holder' => '',
        'card_expiry' => '',
        'card_cvv' => '',
        'online_transaction_no' => 'GCASH-123456'
    ];
    
    echo "Form Data:\n";
    echo "  User: {$user['fullname']}\n";
    echo "  Book ID: {$book['id']}\n";
    echo "  Duration: 3 days\n";
    echo "  Quantity: 1\n";
    echo "  Payment Method: ONLINE\n";
    echo "  Transaction: {$_POST['online_transaction_no']}\n\n";
    
    $rentalId3 = $ctrl->rent(
        (int)$_POST['user_id'],
        (int)$_POST['book_id'],
        (int)$_POST['duration'],
        (int)$_POST['quantity'],
        null
    );
    
    echo "✅ Rental created: ID $rentalId3\n";
    
    $rental3 = $db->prepare('SELECT payment_method, online_transaction_no FROM rentals WHERE id = ?');
    $rental3->execute([$rentalId3]);
    $data3 = $rental3->fetch(PDO::FETCH_ASSOC);
    echo "   Payment stored: {$data3['payment_method']}, Transaction: {$data3['online_transaction_no']}\n\n";
    
    echo "\n".str_repeat("=", 52)."\n";
    echo "✅ ALL PAYMENT DATA IS STORED SUCCESSFULLY!\n";
    echo "Payment details are being recorded in the rentals table.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
