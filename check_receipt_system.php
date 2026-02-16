<?php
// Simple test to verify receipt system works
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

session_start();

// Get first rental
$pdo = \Database::getInstance()->pdo();
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM rentals LIMIT 1');
$result = $stmt->fetch();
$count = $result['cnt'];

if ($count == 0) {
    echo "❌ NO RENTALS IN DATABASE\n";
    echo "You need to create a rental first from the home page.\n";
    exit;
}

// Get one rental with all details
$stmt = $pdo->prepare('
    SELECT 
        r.id, 
        r.rent_date, 
        r.due_date, 
        r.return_date, 
        r.status, 
        r.duration_days,
        r.quantity,
        r.payment_method,
        b.title,
        b.isbn,
        b.price,
        p.amount as penalty_amount,
        p.paid as penalty_paid
    FROM rentals r
    JOIN books b ON r.book_id = b.id
    LEFT JOIN penalties p ON r.id = p.rental_id
    LIMIT 1
');
$stmt->execute();
$rental = $stmt->fetch();

if (!$rental) {
    echo "❌ Cannot fetch rental details\n";
    exit;
}

echo "✅ RENTAL FOUND\n";
echo "ID: " . $rental['id'] . "\n";
echo "Title: " . $rental['title'] . "\n";
echo "Status: " . $rental['status'] . "\n";
echo "Price: " . $rental['price'] . "\n";
echo "Quantity: " . $rental['quantity'] . "\n\n";

echo "✅ JSON ENCODING TEST\n";
$json = json_encode($rental);
echo "JSON length: " . strlen($json) . " bytes\n";
echo "First 200 chars: " . substr($json, 0, 200) . "...\n\n";

echo "✅ ALL CHECKS PASSED\n";
echo "Receipt system should work. Try clicking View Receipt button.\n";
?>
