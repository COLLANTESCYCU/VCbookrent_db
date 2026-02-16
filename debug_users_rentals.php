<?php
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/Database.php';

$db = \Database::getInstance();
$pdo = $db->pdo();

echo "=== USER AND RENTAL DEBUG ===\n\n";

// Get all users
$users = $pdo->query('SELECT id, username, email, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
echo "Users in system:\n";
foreach ($users as $u) {
    echo "  ID: {$u['id']}, Username: {$u['username']}, Email: {$u['email']}, Role: {$u['role']}\n";
}

echo "\nRentals with user info:\n";
$rentals = $pdo->query('
    SELECT r.id, r.user_id, u.username, r.book_id, b.title, r.status
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN books b ON r.book_id = b.id
')->fetchAll(PDO::FETCH_ASSOC);

foreach ($rentals as $r) {
    echo "  ID: {$r['id']}, User: {$r['username']} (ID:{$r['user_id']}), Book: {$r['title']}, Status: {$r['status']}\n";
}

echo "\n=== KEY ISSUE ===\n";
echo "If you login as a user with NO rentals assigned, the receipt system will have no data!\n";
echo "The rental_history.php page filters by: WHERE r.user_id = :user_id\n";
echo "\nTo test the receipt system:\n";
echo "1. Find a user with rentals (from list above)\n";
echo "2. Login as that user\n";
echo "3. Go to rental_history.php\n";
echo "4. Click 'View Receipt'\n";
echo "\nOr create rentals for the logged-in user\n";
?>
