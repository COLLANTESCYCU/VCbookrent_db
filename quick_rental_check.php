<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    
    $pdo = Database::getInstance()->pdo();
    
    // Test simple query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals", \PDO::FETCH_ASSOC);
    $count = $stmt->fetch();
    echo "Total rentals: " . $count['total'] . "\n";
    
    // Show first rental
    if ($count['total'] > 0) {
        $stmt = $pdo->query("SELECT * FROM rentals LIMIT 1", \PDO::FETCH_ASSOC);
        $rental = $stmt->fetch();
        echo "\nFirst rental:\n";
        echo json_encode($rental, JSON_PRETTY_PRINT) . "\n";
        
        // Check if the book and user exist
        echo "\n--- Checking if related records exist ---\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM books WHERE id = " . intval($rental['book_id']), \PDO::FETCH_ASSOC);
        $check = $stmt->fetch();
        echo "Book (ID " . $rental['book_id'] . ") exists: " . ($check['total'] > 0 ? "YES" : "NO") . "\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE id = " . intval($rental['user_id']), \PDO::FETCH_ASSOC);
        $check = $stmt->fetch();
        echo "User (ID " . $rental['user_id'] . ") exists: " . ($check['total'] > 0 ? "YES" : "NO") . "\n";
    } else {
        echo "No rentals found in database.\n";
    }
    
    // Check users table structure
    echo "\n--- Users table columns ---\n";
    $stmt = $pdo->query("DESCRIBE users", \PDO::FETCH_ASSOC);
    $cols = $stmt->fetchAll();
    foreach ($cols as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>