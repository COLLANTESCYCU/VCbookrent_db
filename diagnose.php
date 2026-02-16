<?php
// Include config
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/Database.php';

// Get database connection using singleton
$db = \Database::getInstance();
$pdo = $db->pdo();

// Check system status
echo "=== RECEIPT SYSTEM DIAGNOSIS ===\n\n";

// 1. Check if users exist
$users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "✓ Users in database: " . $users . "\n";

if ($users == 0) {
    echo "⚠️  WARNING: No users found. Creating test user...\n";
    $pdo->exec("
        INSERT INTO users (username, email, password, first_name, last_name, role, status) 
        VALUES ('testuser', 'test@test.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Test', 'User', 'customer', 'active')
    ");
    echo "✓ Test user created\n";
}

// 2. Check if books exist
$books = $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
echo "\n✓ Books in database: " . $books . "\n";

// 3. Check rentals
$rentals = $pdo->query('SELECT COUNT(*) FROM rentals')->fetchColumn();
echo "✓ Rentals in database: " . $rentals . "\n";

if ($rentals == 0) {
    echo "⚠️  WARNING: No rentals found. Creating test rental...\n";
    
    // Get test user
    $user = $pdo->query('SELECT id FROM users WHERE username = "testuser"')->fetch(PDO::FETCH_ASSOC);
    $userId = $user['id'];
    
    // Get first book
    $book = $pdo->query('SELECT id, price FROM books LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($book) {
        $bookId = $book['id'];
        $price = $book['price'];
        
        $pdo->exec("
            INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days, quantity, payment_method, status, cash_received, change_amount)
            VALUES ($userId, $bookId, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY), 5, 1, 'CASH', 'active', $price, 0)
        ");
        echo "✓ Test rental created\n";
        
        // Verify
        $rentalId = $pdo->lastInsertId();
        echo "✓ Rental ID: " . $rentalId . "\n";
    } else {
        echo "❌ ERROR: No books in database\n";
        exit(1);
    }
}

// 4. Test rental retrieval
echo "\n=== TESTING DATA RETRIEVAL ===\n";
$rentals = $pdo->query('
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
')->fetchAll(PDO::FETCH_ASSOC);

if ($rentals) {
    echo "\n✓ Sample Rental:\n";
    $r = $rentals[0];
    echo "  ID: " . $r['id'] . "\n";
    echo "  Title: " . $r['title'] . "\n";
    echo "  Price: " . $r['price'] . "\n";
    echo "  Status: " . $r['status'] . "\n";
    echo "\n✓ JSON Encoding Test:\n";
    $json = json_encode($r);
    echo "  Length: " . strlen($json) . " bytes\n";
    echo "  Valid JSON: " . (json_decode($json) ? "YES" : "NO") . "\n";
    echo "  Sample: " . substr($json, 0, 100) . "...\n";
} else {
    echo "❌ ERROR: Cannot retrieve rental data\n";
}

echo "\n=== DIAGNOSIS COMPLETE ===\n";
echo "\n✓ System ready for browser testing\n";
echo "✓ Open your browser to: http://localhost/VCbookrent_db/public/rental_history.php\n";
echo "✓ Open browser console with F12\n";
echo "✓ Click 'View Receipt' button\n";
echo "✓ Watch console for detailed debug logs\n";
?>
