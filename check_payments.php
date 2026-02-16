<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

echo "=== PAYMENT STORAGE CHECK ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Check rentals table schema
    echo "RENTALS TABLE COLUMNS:\n";
    $columns = $db->query("DESCRIBE rentals")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        $colName = $col['Field'] ?? '';
        $colType = $col['Type'] ?? '';
        echo "  - $colName ($colType)\n";
    }
    
    // Check if payment fields exist
    echo "\nCHECKING FOR PAYMENT FIELDS:\n";
    $paymentFields = ['payment_method', 'cash_received', 'change_amount', 'card_number', 'card_holder', 'card_expiry', 'card_cvv', 'online_transaction_no'];
    
    $columnNames = array_map(function($col) {
        return $col['Field'];
    }, $columns);
    
    foreach ($paymentFields as $field) {
        if (in_array($field, $columnNames)) {
            echo "  ✅ $field exists\n";
        } else {
            echo "  ❌ $field MISSING\n";
        }
    }
    
    // Check actual data in rentals table
    echo "\nCHECKING ACTUAL PAYMENT DATA IN DATABASE:\n";
    $rental = $db->query('SELECT id, payment_method, cash_received, change_amount, card_number FROM rentals LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    
    if ($rental) {
        echo "  Rental ID: {$rental['id']}\n";
        echo "  Payment Method: " . ($rental['payment_method'] ?? 'NULL') . "\n";
        echo "  Cash Received: " . ($rental['cash_received'] ?? 'NULL') . "\n";
        echo "  Change Amount: " . ($rental['change_amount'] ?? 'NULL') . "\n";
        echo "  Card Number: " . ($rental['card_number'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
