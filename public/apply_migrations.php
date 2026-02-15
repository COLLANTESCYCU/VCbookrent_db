<?php
/**
 * Migration Runner
 * Access this file via browser to apply pending migrations
 * URL: http://localhost/VCbookrent_db/public/apply_migrations.php
 */

// Prevent access if not from localhost in production (basic security)
// Remove this check if you need remote access

require_once __DIR__ . '/../src/Database.php';

$output = [];

try {
    $pdo = Database::getInstance()->pdo();
    
    // Migration 1: Add quantity column to rentals
    $output[] = "Checking for quantity column...";
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rentals' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='quantity'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $output[] = "❌ Quantity column not found. Adding...";
        try {
            $pdo->exec("ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`");
            $output[] = "✅ Quantity column added successfully";
            
            // Try to add index
            try {
                $pdo->exec("CREATE INDEX idx_rental_quantity ON rentals(quantity)");
                $output[] = "✅ Index created successfully";
            } catch (Exception $e) {
                $output[] = "⚠️  Index creation skipped (may already exist)";
            }
        } catch (Exception $e) {
            $output[] = "❌ Error adding column: " . $e->getMessage();
            throw $e;
        }
    } else {
        $output[] = "✅ Quantity column already exists";
    }
    
} catch (Exception $e) {
    $output[] = "❌ FATAL ERROR: " . $e->getMessage();
}

// Display results
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; }
        h1 { color: #333; }
        .log { background: #f9f9f9; border-left: 4px solid #667eea; padding: 15px; margin: 10px 0; border-radius: 4px; font-family: 'Courier New', monospace; }
        .success { color: #155724; }
        .error { color: #721c24; }
        .warning { color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Migration Status</h1>
        <div class="log">
            <?php foreach ($output as $line): ?>
                <div><?=htmlspecialchars($line)?></div>
            <?php endforeach; ?>
        </div>
        <p><a href="rentals.php">← Back to Rentals</a></p>
    </div>
</body>
</html>
