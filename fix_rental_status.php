<?php
// Fix the rentals table status column to include 'pending'
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

$pdo = Database::getInstance()->pdo();

echo "=== FIXING RENTALS TABLE STATUS COLUMN ===\n\n";

try {
    // Alter the status column to include 'pending'
    $sql = "ALTER TABLE rentals MODIFY COLUMN status ENUM('pending','active','returned','cancelled','overdue') DEFAULT 'pending'";
    
    $pdo->exec($sql);
    echo "✅ Successfully updated rentals table status column to include 'pending'\n";
    
    // Update existing empty status values to 'pending'
    $sql2 = "UPDATE rentals SET status = 'pending' WHERE status = '' OR status IS NULL";
    $result = $pdo->exec($sql2);
    echo "✅ Updated $result rentals with empty/NULL status to 'pending'\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== CHECKING RENTALS ===\n\n";

$stmt = $pdo->query('SELECT id, status FROM rentals ORDER BY id DESC LIMIT 5');
$rentals = $stmt->fetchAll();
foreach ($rentals as $r) {
    echo "Rental #{$r['id']}: status='{$r['status']}'\n";
}
?>
