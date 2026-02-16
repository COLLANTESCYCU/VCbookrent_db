<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

echo "=== CHECKING BOOKS TABLE SCHEMA ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    $columns = $db->query("DESCRIBE books")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Books Table Columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\nChecking for these specific columns:\n";
    $columnNames = array_column($columns, 'Field');
    $checkFields = ['times_rented', 'last_rented_at', 'available_copies', 'stock_count'];
    
    foreach ($checkFields as $field) {
        if (in_array($field, $columnNames)) {
            echo "  ✅ $field exists\n";
        } else {
            echo "  ❌ $field MISSING\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
