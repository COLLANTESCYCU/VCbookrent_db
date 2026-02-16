<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    
    $pdo = Database::getInstance()->pdo();
    
    echo "=== DATABASE SCHEMA VERIFICATION ===\n\n";
    
    // Check rentals table structure
    echo "1. RENTALS TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE rentals");
    $rentalsSchema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rentalsSchema as $col) {
        $default = !empty($col['Default']) ? " [default: {$col['Default']}]" : "";
        echo "  - {$col['Field']}: {$col['Type']}{$default}\n";
    }
    
    // Check books table structure  
    echo "\n2. BOOKS TABLE STRUCTURE (sample columns):\n";
    $stmt = $pdo->query("DESCRIBE books");
    $booksSchema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $bookCols = ['id', 'title', 'isbn', 'price', 'available_copies'];
    foreach ($booksSchema as $col) {
        if (in_array($col['Field'], $bookCols)) {
            echo "  - {$col['Field']}: {$col['Type']}\n";
        }
    }
    
    // Check users table structure
    echo "\n3. USERS TABLE STRUCTURE (name columns):\n";
    $stmt = $pdo->query("DESCRIBE users");
    $usersSchema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userCols = ['id', 'name', 'fullname', 'email', 'contact_no', 'contact'];
    foreach ($usersSchema as $col) {
        if (in_array($col['Field'], $userCols)) {
            echo "  - {$col['Field']}: {$col['Type']}\n";
        }
    }
    
    // Check sample rental data
    echo "\n4. SAMPLE RENTAL DATA:\n";
    $stmt = $pdo->query("SELECT id, user_id, book_id, status, rent_date FROM rentals LIMIT 1");
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rental) {
        echo json_encode($rental, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No rentals found\n";
    }
    
    // Try the JOIN query manually
    echo "\n5. TESTING MANUAL JOIN QUERY:\n";
    $sql = 'SELECT r.id, r.status, b.title as book_title, u.id as user_id_check, u.name, u.fullname FROM rentals r LEFT JOIN books b ON r.book_id = b.id LEFT JOIN users u ON r.user_id = u.id LIMIT 1';
    echo "Query: " . $sql . "\n\n";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "Result:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No result\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>