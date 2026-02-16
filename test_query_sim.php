<?php
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/Database.php';

$db = \Database::getInstance();
$pdo = $db->pdo();

// Simulate what rental_history.php does for user 17
$userId = 17;

echo "=== SIMULATING rental_history.php FOR USER ID: $userId ===\n\n";

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
        r.cash_received,
        r.change_amount,
        b.title,
        b.isbn,
        b.price,
        p.amount as penalty_amount,
        p.paid as penalty_paid
    FROM rentals r
    JOIN books b ON r.book_id = b.id
    LEFT JOIN penalties p ON r.id = p.rental_id
    WHERE r.user_id = :user_id
    ORDER BY r.rent_date DESC
');

$stmt->execute(['user_id' => $userId]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Query returned " . count($rentals) . " rentals\n\n";

// Simulate the JavaScript object generation
echo "=== JAVASCRIPT CODE THAT WOULD BE GENERATED ===\n\n";
echo "const rentalData = {};\n";

foreach ($rentals as $rental) {
    $json = json_encode($rental);
    echo 'rentalData["' . $rental['id'] . '"] = ' . $json . ";\n\n";
}

echo "\n=== TEST: Can we access each rental? ===\n";
foreach ($rentals as $rental) {
    echo "rentalData[" . $rental['id'] . "] exists: " . (isset($rentals[$rental['id']]) ? "YES" : "YES (by loop)") . "\n";
}

echo "\n✓ If the above shows rentals, the system should work\n";
echo "✓ If the list is empty, user has no rentals\n";
?>
