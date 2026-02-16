<?php
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/Database.php';

$db = \Database::getInstance();
$pdo = $db->pdo();

echo "=== SCHEMA AND DATA DEBUG ===\n\n";

// Check users table schema
$result = $pdo->query('DESCRIBE users');
echo "Users table columns:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} ({$row['Type']})\n";
}

// Get actual users
echo "\nUsers data:\n";
$result = $pdo->query('SELECT * FROM users LIMIT 5');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, Email: {$row['email']}, Role: {$row['role']}\n";
}

// Get rentals with actual columns
echo "\nRentals data:\n";
$result = $pdo->query('
    SELECT r.id, r.user_id, r.status
    FROM rentals r
    LIMIT 5
');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  Rental ID: {$row['id']}, User ID: {$row['user_id']}, Status: {$row['status']}\n";
}

echo "\n✓ Check which users have rentals above\n";
echo "✓ Login as one of those users to test receipt system\n";
?>
