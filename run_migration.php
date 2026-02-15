<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/bootstrap.php';

$pdo = Database::getInstance()->pdo();

try {
    // Check if quantity column exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rentals' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='quantity'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if (!$result) {
        echo "Quantity column not found. Adding it now...\n";
        
        // Add the column
        $pdo->exec("ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`");
        echo "✓ Column added successfully\n";
        
        // Add index
        $pdo->exec("CREATE INDEX idx_rental_quantity ON rentals(quantity)");
        echo "✓ Index created successfully\n";
        
        echo "\n✅ Migration complete!\n";
    } else {
        echo "✅ Quantity column already exists\n";
    }
    
    // Show table structure
    echo "\nRentals table structure:\n";
    $stmt = $pdo->query("DESCRIBE rentals");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
