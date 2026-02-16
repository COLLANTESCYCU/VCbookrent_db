<?php
/**
 * COMPLETE DIAGNOSTIC - Run this to identify exact issues
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== RENTAL SYSTEM DIAGNOSTIC ===\n\n";

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/Controllers/RentalController.php';
    
    $pdo = Database::getInstance()->pdo();
    $rctrl = new RentalController();
    
    // 1. Check rentals table has all required columns
    echo "1. RENTALS TABLE SCHEMA:\n";
    $stmt = $pdo->query("DESCRIBE rentals");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $requiredCols = ['id', 'user_id', 'book_id', 'status', 'rent_date', 'due_date'];
    $missingCols = [];
    
    foreach ($requiredCols as $col) {
        $exists = false;
        foreach ($schema as $s) {
            if ($s['Field'] === $col) {
                $exists = true;
                echo "   ✓ $col\n";
                break;
            }
        }
        if (!$exists) {
            echo "   ✗ MISSING: $col\n";
            $missingCols[] = $col;
        }
    }
    
    // 2. Check users table has name columns
    echo "\n2. USERS TABLE NAME COLUMNS:\n";
    $stmt = $pdo->query("DESCRIBE users");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userNameCols = [];
    foreach ($schema as $col) {
        if (in_array($col['Field'], ['name', 'fullname', 'username'])) {
            echo "   ✓ {$col['Field']}\n";
            $userNameCols[] = $col['Field'];
        }
    }
    
    if (empty($userNameCols)) {
        echo "   ✗ NO NAME COLUMNS FOUND!\n";
    }
    
    // 3. Direct database query test
    echo "\n3. TESTING DIRECT DATABASE QUERY:\n";
    $rawSql = 'SELECT r.id, r.status, r.user_id, r.book_id, b.title, u.name FROM rentals r LEFT JOIN books b ON r.book_id = b.id LEFT JOIN users u ON r.user_id = u.id LIMIT 1';
    $stmt = $pdo->query($rawSql);
    $raw = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($raw) {
        echo "   Raw query returned:\n";
        foreach ($raw as $k => $v) {
            echo "     - $k: " . json_encode($v) . "\n";
        }
    } else {
        echo "   ✗ No results from direct query\n";
    }
    
    // 4. Test RentalController query
    echo "\n4. TESTING RENTALCONTROLLER::GETALL():\n";
    $rentals = $rctrl->getAll();
    echo "   Count: " . count($rentals) . "\n";
    
    if (!empty($rentals)) {
        $r = $rentals[0];
        echo "   First rental data:\n";
        echo "     - id: {$r['id']}\n";
        echo "     - status: " . ($r['status'] ?? 'MISSING') . "\n";
        echo "     - user_name: " . ($r['user_name'] ?? 'MISSING') . "\n";
        echo "     - book_title: " . ($r['book_title'] ?? 'MISSING') . "\n";
        echo "     - user_id: " . ($r['user_id'] ?? 'MISSING') . "\n";
        
        // Check if status is NULL
        if (!isset($r['status']) || $r['status'] === null) {
            echo "\n   ⚠️  STATUS IS NULL/MISSING - need to run fix_rentals.php\n";
        }
        
        // Check if user_name is NULL
        if (!isset($r['user_name']) || $r['user_name'] === 'Unknown') {
            echo "   ⚠️  USER NAME IS UNKNOWN - check users table columns\n";
        }
    }
    
    // 5. Show status distribution
    echo "\n5. RENTAL STATUS DISTRIBUTION:\n";
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM rentals GROUP BY status ORDER BY status");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($statuses)) {
        foreach ($statuses as $row) {
            echo "   - " . ($row['status'] ?? 'NULL') . ": {$row['count']}\n";
        }
    } else {
        echo "   No status data (no rentals?)\n";
    }
    
    // 6. Show sample users
    echo "\n6. SAMPLE USERS:\n";
    $stmt = $pdo->query("SELECT id, name, fullname FROM users LIMIT 2");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($users)) {
        foreach ($users as $u) {
            echo "   - ID {$u['id']}: name=" . ($u['name'] ?? 'NULL') . ", fullname=" . ($u['fullname'] ?? 'NULL') . "\n";
        }
    }
    
    echo "\n=== RECOMMENDATIONS ===\n";
    if (!empty($missingCols)) {
        echo "1. Run: php fix_rentals.php (to add missing columns)\n";
    }
    if (!empty($rentals)) {
        $first = $rentals[0];
        if (!isset($first['status']) || $first['status'] === null) {
            echo "2. Run: php fix_rentals.php (to set status for existing rentals)\n";
        }
        if (!isset($first['user_name']) || $first['user_name'] === 'Unknown') {
            echo "3. Check users table has 'name' or 'fullname' column\n";
        }
    }
    echo "4. Then refresh: /bookrent_db/public/rentals.php\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>