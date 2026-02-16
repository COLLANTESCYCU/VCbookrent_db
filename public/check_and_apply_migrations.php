<?php
/**
 * Comprehensive Migration Check & Apply
 * Ensures all required database columns and tables exist
 * Safe to run multiple times
 */

require_once __DIR__ . '/../src/Database.php';

$pdo = Database::getInstance()->pdo();
$output = [];
$hasErrors = false;

try {
    $output[] = "🔍 Starting comprehensive migration check...";
    $output[] = "";
    
    // Check 1: quantity column in rentals
    $output[] = "▶ Checking rentals.quantity column...";
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rentals' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='quantity'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $output[] = "  ❌ Missing quantity column, adding...";
        try {
            $pdo->exec("ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`");
            $output[] = "  ✅ quantity column added";
            try {
                $pdo->exec("CREATE INDEX idx_rental_quantity ON rentals(quantity)");
            } catch (Exception $e) {
                $output[] = "  ℹ️  Index already exists or skipped";
            }
        } catch (Exception $e) {
            $output[] = "  ❌ Error: " . $e->getMessage();
            $hasErrors = true;
        }
    } else {
        $output[] = "  ✅ quantity column exists";
    }
    $output[] = "";
    
    // Check 2: Payment-related columns in rentals
    $paymentColumns = [
        'cash_received' => 'DECIMAL(10,2)',
        'change_amount' => 'DECIMAL(10,2)',
        'payment_method' => 'VARCHAR(50)',
        'card_number' => 'VARCHAR(19)',
        'card_holder' => 'VARCHAR(100)',
        'card_expiry' => 'VARCHAR(7)',
        'card_cvv' => 'VARCHAR(4)',
        'online_transaction_no' => 'VARCHAR(100)'
    ];
    
    foreach ($paymentColumns as $col => $type) {
        $output[] = "▶ Checking rentals.$col column...";
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rentals' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME=?");
        $stmt->execute([$col]);
        
        if (!$stmt->fetch()) {
            $output[] = "  ❌ Missing $col column, adding...";
            try {
                $pdo->exec("ALTER TABLE rentals ADD COLUMN `$col` $type DEFAULT NULL");
                $output[] = "  ✅ $col column added";
            } catch (Exception $e) {
                $output[] = "  ❌ Error: " . $e->getMessage();
                $hasErrors = true;
            }
        } else {
            $output[] = "  ✅ $col column exists";
        }
        $output[] = "";
    }
    
    // Check 3: status enum in rentals includes 'pending'
    $output[] = "▶ Checking rentals.status ENUM...";
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rentals' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='status'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && strpos($result['COLUMN_TYPE'], 'pending') === false) {
            $output[] = "  ❌ status ENUM doesn't include 'pending', updating...";
            try {
                $pdo->exec("ALTER TABLE rentals MODIFY COLUMN status ENUM('pending','active','returned','overdue','cancelled') DEFAULT 'pending'");
                $output[] = "  ✅ status ENUM updated to include 'pending'";
            } catch (Exception $e) {
                $output[] = "  ❌ Error: " . $e->getMessage();
                $hasErrors = true;
            }
        } else {
            $output[] = "  ✅ status ENUM includes 'pending'";
        }
    } catch (Exception $e) {
        $output[] = "  ❌ Error checking status: " . $e->getMessage();
        $hasErrors = true;
    }
    $output[] = "";
    
    // Check 4: price column in books
    $output[] = "▶ Checking books.price column...";
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='books' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='price'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $output[] = "  ❌ Missing price column, adding...";
        try {
            $pdo->exec("ALTER TABLE books ADD COLUMN `price` DECIMAL(10,2) DEFAULT 0.00 AFTER `author`");
            $output[] = "  ✅ price column added";
        } catch (Exception $e) {
            $output[] = "  ❌ Error: " . $e->getMessage();
            $hasErrors = true;
        }
    } else {
        $output[] = "  ✅ price column exists";
    }
    $output[] = "";
    
    // Check 5: genre column in books (should be genre_id with foreign key, but check for string version)
    $output[] = "▶ Checking books.genre column...";
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='books' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='genre'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $output[] = "  ℹ️  No 'genre' column (using genre_id is correct)";
    } else {
        $output[] = "  ✅ genre column exists";
    }
    $output[] = "";
    
    // Check 6: book_authors table for multiple authors
    $output[] = "▶ Checking book_authors table...";
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='book_authors'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $output[] = "  ❌ Missing book_authors table, creating...";
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS book_authors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    book_id INT NOT NULL,
                    author_name VARCHAR(255) NOT NULL,
                    author_order INT DEFAULT 0,
                    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_author (book_id, author_order)
                )
            ");
            $output[] = "  ✅ book_authors table created";
        } catch (Exception $e) {
            $output[] = "  ❌ Error: " . $e->getMessage();
            $hasErrors = true;
        }
    } else {
        $output[] = "  ✅ book_authors table exists";
    }
    $output[] = "";
    
    // Final status
    if ($hasErrors) {
        $output[] = "⚠️  Some migrations had errors. Please check above.";
    } else {
        $output[] = "✅ All migrations completed successfully!";
    }
    
} catch (Exception $e) {
    $hasErrors = true;
    $output[] = "❌ FATAL ERROR: " . $e->getMessage();
}

// Display results
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Database Migration Check</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #333; 
            font-size: 24px;
            margin: 0 0 20px 0;
        }
        .log { 
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-left: 4px solid #667eea; 
            padding: 20px; 
            border-radius: 4px;
            font-family: 'Courier New', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
        }
        .log div { 
            color: #333;
            margin: 4px 0;
        }
        .nav { 
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .nav a, .nav button { 
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .nav .btn-primary { 
            background: #667eea;
            color: white;
        }
        .nav .btn-primary:hover { 
            background: #5568d3;
        }
        .nav .btn-secondary { 
            background: #f0f0f0;
            color: #333;
        }
        .nav .btn-secondary:hover { 
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= $hasErrors ? '⚠️ Migration Check Complete (with warnings)' : '✅ Migration Check Complete' ?></h1>
        <div class="log">
            <?php foreach ($output as $line): ?>
                <div><?=htmlspecialchars($line)?></div>
            <?php endforeach; ?>
        </div>
        <div class="nav">
            <a href="home.php" class="btn-primary">← Back to Home</a>
            <a href="rental_history.php" class="btn-primary">View Rentals</a>
        </div>
    </div>
</body>
</html>
