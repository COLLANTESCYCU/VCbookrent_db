<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/RentalController.php';
require_once __DIR__ . '/src/Models/Book.php';

echo "=== PAYMENT RECORDING TEST ===\n\n";

try {
    $pdo = Database::getInstance()->pdo();
    
    // Check if tbl_payments table exists
    echo "1. Checking tbl_payments table...\n";
    $checkTable = $pdo->query("SHOW TABLES LIKE 'tbl_payments'")->fetch();
    if ($checkTable) {
        echo "   ✓ Table exists\n";
    } else {
        echo "   ✗ Table does NOT exist - payments will not be recorded\n";
    }
    
    // Check table structure
    echo "\n2. Verifying table structure...\n";
    $columns = $pdo->query("DESCRIBE tbl_payments")->fetchAll(PDO::FETCH_ASSOC);
    $requiredColumns = ['id', 'rental_id', 'user_id', 'amount_charged', 'payment_method', 'payment_status'];
    foreach ($requiredColumns as $col) {
        $exists = array_search($col, array_column($columns, 'Field')) !== false;
        echo "   " . ($exists ? "✓" : "✗") . " Column '$col' " . ($exists ? "exists" : "missing") . "\n";
    }
    
    // Check recent payments
    echo "\n3. Checking recent payments in database...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_payments");
    $result = $stmt->fetch();
    $paymentCount = $result['count'] ?? 0;
    echo "   Total payments recorded: $paymentCount\n";
    
    if ($paymentCount > 0) {
        echo "\n   Recent payments:\n";
        $stmt = $pdo->query("
            SELECT 
                p.id,
                p.rental_id,
                p.user_id,
                p.amount_charged,
                p.payment_method,
                p.payment_status,
                p.payment_date,
                u.fullname,
                b.title
            FROM tbl_payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN rentals r ON p.rental_id = r.id
            LEFT JOIN books b ON r.book_id = b.id
            ORDER BY p.payment_date DESC
            LIMIT 5
        ");
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($payments as $payment) {
            echo "   ├─ Payment ID: {$payment['id']}\n";
            echo "   │  Rental ID: {$payment['rental_id']}\n";
            echo "   │  User: {$payment['fullname']}\n";
            echo "   │  Amount: ₱" . number_format($payment['amount_charged'], 2) . "\n";
            echo "   │  Method: {$payment['payment_method']}\n";
            echo "   │  Status: {$payment['payment_status']}\n";
            echo "   │  Date: {$payment['payment_date']}\n";
            echo "   │  Book: {$payment['title']}\n";
            echo "   └─\n";
        }
    }
    
    // Check if there are recent rentals with payment data
    echo "\n4. Checking rentals with payment info...\n";
    $stmt = $pdo->query("
        SELECT 
            r.id,
            r.user_id,
            r.book_id,
            r.payment_method,
            r.cash_received,
            r.change_amount,
            r.status,
            r.rent_date,
            u.fullname,
            b.title,
            b.price
        FROM rentals r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN books b ON r.book_id = b.id
        WHERE r.payment_method IS NOT NULL
        ORDER BY r.rent_date DESC
        LIMIT 5
    ");
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Rentals with payment method: " . count($rentals) . "\n";
    
    if (count($rentals) > 0) {
        echo "\n   Recent rentals with payments:\n";
        foreach ($rentals as $rental) {
            echo "   ├─ Rental ID: {$rental['id']}\n";
            echo "   │  User: {$rental['fullname']}\n";
            echo "   │  Book: {$rental['title']} (Price: ₱" . number_format($rental['price'], 2) . ")\n";
            echo "   │  Payment Method: {$rental['payment_method']}\n";
            if ($rental['cash_received']) {
                echo "   │  Cash Received: ₱" . number_format($rental['cash_received'], 2) . "\n";
                echo "   │  Change: ₱" . number_format($rental['change_amount'], 2) . "\n";
            }
            echo "   │  Status: {$rental['status']}\n";
            echo "   │  Date: {$rental['rent_date']}\n";
            echo "   └─\n";
        }
    }
    
    // Verify the recordPayment method exists
    echo "\n5. Checking Rental model...\n";
    require_once __DIR__ . '/src/Models/Rental.php';
    $rental = new Rental();
    if (method_exists($rental, 'recordPayment')) {
        echo "   ✓ recordPayment() method exists\n";
    } else {
        echo "   ✗ recordPayment() method NOT found\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
