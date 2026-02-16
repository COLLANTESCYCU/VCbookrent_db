<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

try {
    $pdo = Database::getInstance()->pdo();
    echo "✓ Database connected\n";
    
    // Test simple query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals");
    $count = $stmt->fetch();
    echo "Total rentals: " . $count['total'] . "\n";
    
    if ($count['total'] > 0) {
        echo "\n--- All rentals in database ---\n";
        $stmt = $pdo->query("SELECT id, user_id, book_id, status, rent_date FROM rentals");
        $all = $stmt->fetchAll();
        foreach ($all as $r) {
            echo "ID: " . $r['id'] . " | Status: " . $r['status'] . " | User: " . $r['user_id'] . " | Book: " . $r['book_id'] . " | Date: " . $r['rent_date'] . "\n";
        }
        
        echo "\n--- First rental full details ---\n";
        $stmt = $pdo->query("SELECT * FROM rentals LIMIT 1");
        $rental = $stmt->fetch();
        foreach ($rental as $key => $val) {
            echo "$key: $val\n";
        }
    } else {
        echo "❌ NO RENTALS IN DATABASE!\n";
    }
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>