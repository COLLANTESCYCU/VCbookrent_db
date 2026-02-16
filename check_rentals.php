<?php
require_once __DIR__ . '/src/Database.php';

$pdo = \Database::getInstance()->pdo();

// Check rentals
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM rentals');
$result = $stmt->fetch();
echo "Total rentals: " . $result['cnt'] . "\n";

// Check first few rentals
$stmt = $pdo->query('SELECT id, status, user_id FROM rentals LIMIT 5');
$rentals = $stmt->fetchAll();
echo "Sample rentals:\n";
print_r($rentals);

// Check if rentalData object would be populated
if (!empty($rentals)) {
    echo "\n\nrentalData JavaScript object would be:\n";
    echo "const rentalData = {\n";
    foreach ($rentals as $rental) {
        echo '  "' . $rental['id'] . '": {...},\n';
    }
    echo "};\n";
}
?>
