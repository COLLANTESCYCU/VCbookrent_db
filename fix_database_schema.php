<?php
/**
 * Database Schema Fix Migration
 * Ensures all required columns exist in the users table
 * Run this from the browser or command line
 */

require_once __DIR__ . '/src/bootstrap.php';

$pdo = Database::getInstance()->pdo();
$fixes_applied = [];
$errors = [];

try {
    // Check if role column exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='role'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo "⚠️  Role column missing. Adding...\n";
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN `role` ENUM('admin','staff','user') DEFAULT 'user'");
            $fixes_applied[] = "✅ Added 'role' column to users table";
        } catch (Exception $e) {
            $errors[] = "❌ Failed to add role column: " . $e->getMessage();
        }
    } else {
        $fixes_applied[] = "✅ Role column already exists";
    }
    
    // Check if status column exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='status'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo "⚠️  Status column missing. Adding...\n";
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN `status` ENUM('active','inactive') DEFAULT 'active'");
            $fixes_applied[] = "✅ Added 'status' column to users table";
        } catch (Exception $e) {
            $errors[] = "❌ Failed to add status column: " . $e->getMessage();
        }
    } else {
        $fixes_applied[] = "✅ Status column already exists";
    }
    
    // Check if fullname column exists (should exist from migration_users_info_update)
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='fullname'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $fixes_applied[] = "⚠️  Warning: 'fullname' column not found. You may need to rename 'name' column.";
    } else {
        $fixes_applied[] = "✅ Fullname column exists";
    }
    
    // Check if contact_no column exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='contact_no'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        // Try to rename contact if it exists
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='contact'");
        $stmt->execute();
        
        if ($stmt->fetch()) {
            echo "⚠️  Renaming 'contact' to 'contact_no'...\n";
            try {
                $pdo->exec("ALTER TABLE users CHANGE COLUMN `contact` `contact_no` VARCHAR(255) DEFAULT NULL");
                $fixes_applied[] = "✅ Renamed 'contact' column to 'contact_no'";
            } catch (Exception $e) {
                $errors[] = "❌ Failed to rename contact column: " . $e->getMessage();
            }
        }
    } else {
        $fixes_applied[] = "✅ Contact_no column exists";
    }
    
    // Check if password_hash column exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='password_hash'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        // Try to rename password column if it exists
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='password'");
        $stmt->execute();
        
        if ($stmt->fetch()) {
            echo "⚠️  Renaming 'password' to 'password_hash'...\n";
            try {
                $pdo->exec("ALTER TABLE users CHANGE COLUMN `password` `password_hash` VARCHAR(255) NOT NULL");
                $fixes_applied[] = "✅ Renamed 'password' column to 'password_hash'";
            } catch (Exception $e) {
                $errors[] = "❌ Failed to rename password column: " . $e->getMessage();
            }
        }
    } else {
        $fixes_applied[] = "✅ Password_hash column exists";
    }
    
    // Display results
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "DATABASE SCHEMA MIGRATION RESULTS\n";
    echo str_repeat("=", 50) . "\n\n";
    
    foreach ($fixes_applied as $msg) {
        echo $msg . "\n";
    }
    
    if (!empty($errors)) {
        echo "\nERRORS:\n";
        foreach ($errors as $err) {
            echo $err . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "CURRENT USERS TABLE STRUCTURE:\n";
    echo str_repeat("=", 50) . "\n\n";
    
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        $type = $col['Type'];
        $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = !empty($col['Default']) ? "DEFAULT {$col['Default']}" : '';
        echo sprintf("%-20s %-25s %-10s %s\n", $col['Field'], $type, $null, $default);
    }
    
    echo "\n✅ Migration complete! You can now login to the system.\n";
    
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
