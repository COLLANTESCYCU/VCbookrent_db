<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/src/bootstrap.php';
    
    $pdo = Database::getInstance()->pdo();
    
    // Try a test query with quantity
    $stmt = $pdo->prepare("INSERT INTO rentals (user_id, book_id, rent_date, due_date, duration_days, quantity) VALUES (:u, :b, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 7, 1)");
    
    echo "✅ Column exists! INSERT with quantity column works.\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        echo "❌ Quantity column does not exist.\n";
        echo "Attempting to add column...\n";
        
        try {
            $pdo = Database::getInstance()->pdo();
            $pdo->exec("ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`");
            echo "✅ Column added successfully!\n";
        } catch (Exception $e2) {
            echo "❌ Failed to add column: " . $e2->getMessage() . "\n";
        }
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
