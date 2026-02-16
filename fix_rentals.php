<?php
/**
 * Fix existing rentals - ensure all have status='pending'
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    
    $pdo = Database::getInstance()->pdo();
    
    echo "=== FIXING EXISTING RENTALS ===\n\n";
    
    // 1. Check if status column exists and has default
    echo "1. Checking rentals table status column...\n";
    $stmt = $pdo->query("DESCRIBE rentals");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statusCol = null;
    foreach ($schema as $col) {
        if ($col['Field'] === 'status') {
            $statusCol = $col;
            break;
        }
    }
    
    if (!$statusCol) {
        echo "   Adding status column...\n";
        $pdo->query("ALTER TABLE rentals ADD COLUMN status ENUM('pending','active','returned','overdue','cancelled') DEFAULT 'pending'");
        echo "   ✓ Status column added\n";
    } else {
        echo "   ✓ Status column exists\n";
        echo "     Type: {$statusCol['Type']}\n";
        echo "     Null: {$statusCol['Null']}\n";
        echo "     Default: {$statusCol['Default']}\n";
    }
    
    // 2. Update any NULL status values to 'pending'
    echo "\n2. Fixing NULL status values...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rentals WHERE status IS NULL OR status = ''");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nullCount = $result['count'];
    
    if ($nullCount > 0) {
        echo "   Found $nullCount rentals with NULL/empty status\n";
        $pdo->query("UPDATE rentals SET status = 'pending' WHERE status IS NULL OR status = ''");
        echo "   ✓ Updated to 'pending'\n";
    } else {
        echo "   ✓ No NULL status values found\n";
    }
    
    // 3. Show current status distribution
    echo "\n3. Current rental status distribution:\n";
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM rentals GROUP BY status");
    $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($byStatus as $row) {
        echo "   - {$row['status']}: {$row['count']}\n";
    }
    
    // 4. Verify users table has name fields
    echo "\n4. Checking users table name columns...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasName = false;
    $hasFullname = false;
    foreach ($schema as $col) {
        if ($col['Field'] === 'name') $hasName = true;
        if ($col['Field'] === 'fullname') $hasFullname = true;
    }
    
    if ($hasName) echo "   ✓ 'name' column exists\n";
    if ($hasFullname) echo "   ✓ 'fullname' column exists\n";
    
    if (!$hasFullname && $hasName) {
        echo "\n   Adding 'fullname' as copy/alias of 'name'...\n";
        try {
            $pdo->query("ALTER TABLE users ADD COLUMN fullname VARCHAR(255) AFTER id");
            $pdo->query("UPDATE users SET fullname = name WHERE fullname IS NULL");
            echo "   ✓ Fullname column created and populated\n";
        } catch (Exception $e) {
            echo "   ~ Fullname column may already exist or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== FIX COMPLETE ===\n";
    echo "\nNow refresh rentals.php to see the changes\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>