<?php
/**
 * Database Setup and Migration Application
 * Ensures all necessary columns and tables exist for the rental approval workflow
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    
    $pdo = Database::getInstance()->pdo();
    
    echo "=== Database Setup and Migration ===\n\n";
    
    // 1. Check and add quantity column if missing
    echo "1. Adding 'quantity' column to rentals table...\n";
    try {
        $pdo->query("ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`");
        echo "   ✓ Added quantity column\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✓ Quantity column already exists\n";
        } else {
            echo "   ! Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Check and add status column if missing (or modify if exists)
    echo "\n2. Ensuring 'status' column in rentals table...\n";
    try {
        $pdo->query("ALTER TABLE rentals MODIFY COLUMN status ENUM('pending','active','returned','overdue','cancelled') DEFAULT 'pending'");
        echo "   ✓ Status column updated with all values\n";
    } catch (Exception $e) {
        try {
            $pdo->query("ALTER TABLE rentals ADD COLUMN status ENUM('pending','active','returned','overdue','cancelled') DEFAULT 'pending'");
            echo "   ✓ Added status column\n";
        } catch (Exception $e2) {
            if (strpos($e2->getMessage(), 'already exists') !== false) {
                echo "   ✓ Status column already exists\n";
            } else {
                echo "   ! Error: " . $e2->getMessage() . "\n";
            }
        }
    }
    
    // 3. Check and add payment-related columns
    echo "\n3. Adding payment columns to rentals table...\n";
    $paymentCols = [
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
            echo "   ✓ Added $col column\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "   ✓ $col column already exists\n";
            } else {
                echo "   ! Error adding $col: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 4. Add price column to books table if missing
    echo "\n4. Checking price column in books table...\n";
    try {
        $pdo->query("ALTER TABLE books ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00");
        echo "   ✓ Added price column to books\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✓ Price column already exists\n";
        } else {
            echo "   ! Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Ensure users table has fullname
    echo "\n5. Checking users table structure...\n";
    try {
        // Check if fullname column exists
        $stmt = $pdo->query("DESCRIBE users");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasFullname = false;
        $hasName = false;
        foreach ($cols as $col) {
            if ($col['Field'] === 'fullname') $hasFullname = true;
            if ($col['Field'] === 'name') $hasName = true;
        }
        
        if (!$hasFullname && $hasName) {
            // Try to add fullname as an alias or rename
            try {
                $pdo->query("ALTER TABLE users ADD COLUMN fullname VARCHAR(255) AFTER id");
                // Copy existing names
                $pdo->query("UPDATE users SET fullname = name WHERE fullname IS NULL");
                echo "   ✓ Added fullname column to users\n";
            } catch (Exception $e) {
                echo "   ! Could not add fullname: " . $e->getMessage() . "\n";
            }
        } elseif ($hasFullname) {
            echo "   ✓ Fullname column exists\n";
        }
    } catch (Exception $e) {
        echo "   ! Error checking users: " . $e->getMessage() . "\n";
    }
    
    // 6. Verify rentals exist and can be displayed
    echo "\n6. Checking rentals in database...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Total rentals: " . $result['total'] . "\n";
        
        if ($result['total'] > 0) {
            // Show structure of first rental
            $stmt = $pdo->query("SELECT * FROM rentals LIMIT 1");
            $firstRental = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "\n   First rental (columns):\n";
            foreach (array_keys($firstRental) as $col) {
                echo "      - $col: " . ($firstRental[$col] ?? 'NULL') . "\n";
            }
        }
    } catch (Exception $e) {
        echo "   ! Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Setup Complete ===\n";
    echo "You can now:\n";
    echo "1. Go to home.php and click Rent on any book\n";
    echo "2. Complete the payment form\n";
    echo "3. Rental will be created with status='pending'\n";
    echo "4. Check rental_history.php to see your pending rental\n";
    echo "5. Go to rentals.php to approve the rental as admin/staff\n";
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>