<?php
/**
 * COMPREHENSIVE FIX - Run this ONCE to fix all issues
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== RUNNING COMPREHENSIVE FIX ===\n\n";

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    
    $pdo = Database::getInstance()->pdo();
    
    // STEP 1: Ensure rentals table has status column
    echo "STEP 1: Checking rentals.status column...\n";
    try {
        $pdo->query("MODIFY COLUMN status ENUM('pending','active','returned','overdue','cancelled') DEFAULT 'pending'");
        echo "  ✓ Status column type verified\n";
    } catch (Exception $e) {
        try {
            $pdo->query("ALTER TABLE rentals ADD COLUMN status ENUM('pending','active','returned','overdue','cancelled') DEFAULT 'pending'");
            echo "  ✓ Status column added\n";
        } catch (Exception $e2) {
            if (strpos($e2->getMessage(), 'already exists') === false) {
                echo "  ! Status column add failed: " . $e2->getMessage() . "\n";
            } else {
                echo "  ✓ Status column already exists\n";
            }
        }
    }
    
    // STEP 2: Set status='pending' for any NULL/empty status values
    echo "\nSTEP 2: Fixing NULL/empty status values...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM rentals WHERE status IS NULL OR status = ''");
    $nullCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    if ($nullCount > 0) {
        $pdo->query("UPDATE rentals SET status = 'pending' WHERE status IS NULL OR status = ''");
        echo "  ✓ Set $nullCount rental(s) to 'pending'\n";
    } else {
        echo "  ✓ No NULL/empty status values found\n";
    }
    
    // STEP 3: Ensure users table has fullname column
    echo "\nSTEP 3: Checking users.fullname column...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasFullname = false;
    $hasName = false;
    
    foreach ($schema as $col) {
        if ($col['Field'] === 'fullname') $hasFullname = true;
        if ($col['Field'] === 'name') $hasName = true;
    }
    
    if (!$hasFullname && $hasName) {
        try {
            $pdo->query("ALTER TABLE users ADD COLUMN fullname VARCHAR(255) AFTER id");
            $pdo->query("UPDATE users SET fullname = name WHERE fullname IS NULL OR fullname = ''");
            echo "  ✓ Added fullname column and populated from name\n";
        } catch (Exception $e) {
            echo "  ! Could not add fullname:" . $e->getMessage() . "\n";
        }
    } elseif ($hasFullname) {
        // Ensure fullname is populated
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE fullname IS NULL OR fullname = ''");
        $emptyCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        if ($emptyCount > 0 && $hasName) {
            $pdo->query("UPDATE users SET fullname = name WHERE (fullname IS NULL OR fullname = '') AND name IS NOT NULL");
            echo "  ✓ Populated $emptyCount fullname(s) from name\n";
        } else {
            echo "  ✓ Fullname column exists and is populated\n";
        }
    } else {
        echo "  ✗ Neither fullname nor name column found!\n";
    }
    
    // STEP 4: Verify books table has price column
    echo "\nSTEP 4: Checking books.price column...\n";
    $stmt = $pdo->query("DESCRIBE books");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasPrice = false;
    foreach ($schema as $col) {
        if ($col['Field'] === 'price') {
            $hasPrice = true;
            break;
        }
    }
    
    if (!$hasPrice) {
        $pdo->query("ALTER TABLE books ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00");
        echo "  ✓ Added price column\n";
    } else {
        echo "  ✓ Price column exists\n";
    }
    
    // STEP 5: Verify all payment columns in rentals
    echo "\nSTEP 5: Checking payment columns in rentals...\n";
    $paymentCols = [
        'quantity' => 'INT DEFAULT 1',
        'cash_received' => 'DECIMAL(10,2) DEFAULT NULL',
        'change_amount' => 'DECIMAL(10,2) DEFAULT NULL',
        'payment_method' => "ENUM('cash','card','online') DEFAULT NULL",
        'card_number' => 'VARCHAR(19) DEFAULT NULL',
        'card_holder' => 'VARCHAR(100) DEFAULT NULL',
        'card_expiry' => 'VARCHAR(7) DEFAULT NULL',
        'card_cvv' => 'VARCHAR(4) DEFAULT NULL',
        'online_transaction_no' => 'VARCHAR(100) DEFAULT NULL'
    ];
    
    foreach ($paymentCols as $col => $def) {
        try {
            $pdo->query("ALTER TABLE rentals ADD COLUMN `$col` $def");
            echo "   ✓ Added $col\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                // Column already exists, skip
            } else {
                echo "   ! Error adding $col: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // FINAL: Show summary
    echo "\n=== SUMMARY ===\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM rentals");
    $totalRentals = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "Total rentals: $totalRentals\n";
    
    if ($totalRentals > 0) {
        $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM rentals GROUP BY status");
        $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nRentals by status:\n";
        foreach ($byStatus as $row) {
            echo "  - " . ($row['status'] ?? 'NULL') . ": {$row['cnt']}\n";
        }
        
        // Show sample rental
        $stmt = $pdo->query("SELECT r.id, r.status, r.user_id, u.fullname, u.name, b.title FROM rentals r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN books b ON r.book_id = b.id LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\nSample rental:\n";
        echo "  - ID: {$sample['id']}\n";
        echo "  - Status: {$sample['status']}\n";
        echo "  - User: " . ($sample['fullname'] ?? $sample['name'] ?? 'Unknown') . "\n";
        echo "  - Book: {$sample['title']}\n";
    }
    
    echo "\n✅ FIX COMPLETE\n";
    echo "\nNow:\n";
    echo "1. Refresh http://localhost:80/bookrent_db/public/rentals.php\n";
    echo "2. You should see status badges (Pending/Active) and appropriate buttons\n";
    echo "3. User names should display correctly\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>