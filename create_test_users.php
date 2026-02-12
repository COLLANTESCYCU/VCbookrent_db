<?php
require_once 'src/Database.php';

try {
    $pdo = Database::getInstance()->pdo();
    
    // Delete existing test users first
    $pdo->exec("DELETE FROM users WHERE email IN ('admin@test.com', 'staff@test.com', 'user@test.com')");
    
    // Try with username column - generate based on email
    $users = [
        ['Admin User', 'admin@test.com', 'admin_user', '$2y$10$RFCUEwTLBo.g9lWZdCo3GusZWgLKvL7Iq6q6DIxKHVN0tXEZaVVAe', 'admin', 'active', '09123456789'],
        ['Staff User', 'staff@test.com', 'staff_user', '$2y$10$nPYRIk2X5c6r9sL3mQvV0eVmZjVLF6F9bN0cP7hJ1fW5dT2xK1LFK', 'staff', 'active', '09123456788'],
        ['Regular User', 'user@test.com', 'regular_user', '$2y$10$XHqYrBUn5xQzF2pGs8kVJOp7mD.9D7n4jJ5hK2wL1vM3oN6aR9sB.', 'user', 'active', '09123456787'],
    ];
    
    try {
        // Try with username column
        $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password_hash, role, status, contact) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($users as $user) {
            $stmt->execute($user);
        }
    } catch (Exception $e) {
        // Try without username if it fails
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status, contact) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($users as $user) {
            array_splice($user, 2, 1); // Remove username
            $stmt->execute($user);
        }
    }
    
    echo "✓ Test users created successfully!\n\n";
    
    // Verify
    $stmt = $pdo->query("SELECT name, email, role FROM users WHERE email IN ('admin@test.com', 'staff@test.com', 'user@test.com') ORDER BY role DESC");
    $users_result = $stmt->fetchAll();
    
    foreach ($users_result as $user) {
        echo "✓ {$user['name']} ({$user['role']}): {$user['email']}\n";
    }
    
    echo "\n=== LOGIN CREDENTIALS ===\n";
    echo "Admin:  admin@test.com / admin123\n";
    echo "Staff:  staff@test.com / staff123\n";
    echo "User:   user@test.com / user123\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
