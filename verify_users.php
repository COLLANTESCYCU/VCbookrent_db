<?php
require_once 'src/Database.php';

try {
    $pdo = Database::getInstance()->pdo();
    $stmt = $pdo->query("SELECT name, email, role FROM users WHERE email IN ('admin@test.com', 'staff@test.com', 'user@test.com') ORDER BY role");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "ERROR: No test users found!\n";
        exit(1);
    }
    
    echo "✓ Test users created successfully:\n\n";
    foreach ($users as $user) {
        echo "  Name: {$user['name']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Role: {$user['role']}\n";
        echo "  ---\n";
    }
    
    echo "\nLogin credentials:\n";
    echo "Admin:  admin@test.com / admin123\n";
    echo "Staff:  staff@test.com / staff123\n";
    echo "User:   user@test.com / user123\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
