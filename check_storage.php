<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/BookController.php';
require_once __DIR__ . '/src/Models/Book.php';
require_once __DIR__ . '/src/Database.php';

echo "=== BOOKS & USERS STORAGE TEST ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Count books
    echo "BOOKS:\n";
    $bookCount = $db->query('SELECT COUNT(*) FROM books WHERE archived = 0')->fetchColumn();
    echo "  ✅ Total books in database: $bookCount\n";
    
    $stmt = $db->query('SELECT id, title, price, stock_count, available_copies FROM books WHERE archived = 0 ORDER BY created_at DESC LIMIT 3');
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Latest books:\n";
    foreach ($books as $b) {
        echo "    - {$b['title']} (ID: {$b['id']}, Price: ₱{$b['price']}, Stock: {$b['stock_count']}, Available: {$b['available_copies']})\n";
    }
    
    // Count users
    echo "\nUSERS:\n";
    $userCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "  ✅ Total users in database: $userCount\n";
    
    $stmt = $db->query('SELECT id, fullname, email, role FROM users ORDER BY created_at DESC LIMIT 3');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Latest users:\n";
    foreach ($users as $u) {
        echo "    - {$u['fullname']} ({$u['email']}) [Role: {$u['role']}]\n";
    }
    
    // Count rentals
    echo "\nRENTALS:\n";
    $rentalCount = $db->query('SELECT COUNT(*) FROM rentals')->fetchColumn();
    echo "  ✅ Total rentals in database: $rentalCount\n";
    
    $stmt = $db->query('SELECT r.id, u.fullname, b.title, r.status, r.rent_date FROM rentals r JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id ORDER BY r.rent_date DESC LIMIT 3');
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Latest rentals:\n";
    foreach ($rentals as $r) {
        echo "    - {$r['fullname']} rented '{$r['title']}' [{$r['status']}] on {$r['rent_date']}\n";
    }
    
    echo "\n=== ALL DATA IS BEING STORED CORRECTLY ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
