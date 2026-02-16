<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Models/Rental.php';
require_once __DIR__ . '/src/Models/Book.php';
require_once __DIR__ . '/src/Models/User.php';

echo "=== TESTING PAYMENT RECORDING FUNCTIONALITY ===\n\n";

try {
    $pdo = Database::getInstance()->pdo();
    $rentalModel = new Rental();
    $bookModel = new Book();
    $userModel = new User();
    
    // Get a test user and book
    echo "1. Getting test data...\n";
    $users = $pdo->query("SELECT id, fullname, role FROM users WHERE role = 'user' LIMIT 1")->fetchAll();
    $books = $pdo->query("SELECT id, title, price, available_copies FROM books WHERE available_copies > 0 LIMIT 1")->fetchAll();
    
    if (empty($users) || empty($books)) {
        echo "   ✗ Not enough test data available\n";
        echo "   Users found: " . count($users) . "\n";
        echo "   Books found: " . count($books) . "\n";
        exit(1);
    }
    
    $testUser = $users[0];
    $testBook = $books[0];
    echo "   ✓ Test User: {$testUser['fullname']} (ID: {$testUser['id']})\n";
    echo "   ✓ Test Book: {$testBook['title']} (ID: {$testBook['id']}, Price: ₱{$testBook['price']})\n";
    
    // Clear old test payments
    echo "\n2. Cleaning up old test data...\n";
    $stmt = $pdo->prepare("DELETE FROM tbl_payments WHERE amount_charged = ?");
    $stmt->execute([999.99]);
    echo "   ✓ Old test payments removed\n";
    
    // Test case 1: Cash payment
    echo "\n3. Test Case 1: Creating rental with CASH payment...\n";
    $paymentsBefore = $pdo->query("SELECT COUNT(*) as count FROM tbl_payments")->fetch()['count'];
    
    try {
        // Simulate creating a rental directly using the recordPayment method
        echo "   - Recording payment record directly...\n";
        
        // Create a test rental first
        $_POST['payment_method'] = 'cash';
        $_POST['card_number'] = '';
        $_POST['card_holder'] = '';
        $_POST['card_expiry'] = '';
        $_POST['card_cvv'] = '';
        $_POST['online_transaction_no'] = '';
        
        // For testing, we'll manually test the recordPayment method
        $testRentalId = 999;  // Fake ID for testing
        $testAmount = 999.99;
        
        $result = $rentalModel->recordPayment(
            $testRentalId,
            $testUser['id'],
            $testAmount,
            'cash',
            1000.00,  // cash received
            [],
            null
        );
        
        echo "   - Payment recording result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        
        $paymentsAfter = $pdo->query("SELECT COUNT(*) as count FROM tbl_payments")->fetch()['count'];
        echo "   - Payments before: $paymentsBefore\n";
        echo "   - Payments after: $paymentsAfter\n";
        
        if ($paymentsAfter > $paymentsBefore) {
            echo "   ✓ Payment recorded successfully\n";
            
            // Verify the payment details
            $stmt = $pdo->prepare("SELECT * FROM tbl_payments WHERE rental_id = ? AND amount_charged = ?");
            $stmt->execute([$testRentalId, $testAmount]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                echo "   ✓ Payment details verified:\n";
                echo "     - Rental ID: {$payment['rental_id']}\n";
                echo "     - User ID: {$payment['user_id']}\n";
                echo "     - Amount Charged: ₱" . number_format($payment['amount_charged'], 2) . "\n";
                echo "     - Amount Received: ₱" . number_format($payment['amount_received'], 2) . "\n";
                echo "     - Change: ₱" . number_format($payment['change_amount'], 2) . "\n";
                echo "     - Payment Method: {$payment['payment_method']}\n";
                echo "     - Payment Status: {$payment['payment_status']}\n";
                echo "     - Cash Received: ₱" . number_format($payment['cash_received'], 2) . "\n";
            }
        } else {
            echo "   ✗ Payment not recorded\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
    
    // Test case 2: Card payment
    echo "\n4. Test Case 2: Recording CARD payment...\n";
    try {
        $result = $rentalModel->recordPayment(
            998,  // Different test rental ID
            $testUser['id'],
            999.99,
            'card',
            null,  // no cash
            ['card_number' => '4111111111111111', 'card_holder' => 'Test User', 'card_expiry' => '12/25', 'card_cvv' => '123'],
            null
        );
        
        echo "   - Payment recording result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        
        $stmt = $pdo->prepare("SELECT * FROM tbl_payments WHERE rental_id = ? AND amount_charged = 999.99");
        $stmt->execute([998]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            echo "   ✓ Card payment recorded:\n";
            echo "     - Payment Method: {$payment['payment_method']}\n";
            echo "     - Card Last 4: {$payment['card_last_four']}\n";
            echo "     - Card Holder: {$payment['card_holder']}\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
    
    // Test case 3: Online payment
    echo "\n5. Test Case 3: Recording ONLINE payment...\n";
    try {
        $result = $rentalModel->recordPayment(
            997,  // Different test rental ID
            $testUser['id'],
            999.99,
            'online',
            null,  // no cash
            [],
            'TXN123456789'
        );
        
        echo "   - Payment recording result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        
        $stmt = $pdo->prepare("SELECT * FROM tbl_payments WHERE rental_id = ? AND amount_charged = 999.99");
        $stmt->execute([997]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            echo "   ✓ Online payment recorded:\n";
            echo "     - Payment Method: {$payment['payment_method']}\n";
            echo "     - Transaction No: {$payment['online_transaction_no']}\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
    
    // Verify integration in actual rental flow
    echo "\n6. Checking integration with rental creation...\n";
    $stmt = $pdo->query("SELECT * FROM rentals ORDER BY id DESC LIMIT 1");
    $latestRental = $stmt->fetch();
    
    if ($latestRental) {
        echo "   - Latest rental: ID {$latestRental['id']}\n";
        echo "   - Payment method: {$latestRental['payment_method']}\n";
        
        // Check if payment exists for this rental
        $stmt = $pdo->prepare("SELECT * FROM tbl_payments WHERE rental_id = ?");
        $stmt->execute([$latestRental['id']]);
        $rentalPayment = $stmt->fetch();
        
        if ($rentalPayment) {
            echo "   ✓ Payment record found for this rental\n";
        } else {
            echo "   ℹ No payment record (rental might be old, created before feature was added)\n";
        }
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
