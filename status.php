<?php
/**
 * QUICK STATUS CHECK - Run this to verify everything is working
 * Usage: php status.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== RENTAL SYSTEM STATUS CHECK ===\n\n";

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/Controllers/RentalController.php';
    require_once __DIR__ . '/src/Models/Rental.php';
    
    $pdo = Database::getInstance()->pdo();
    $rctrl = new RentalController();
    
    // 1. Database connection
    echo "✓ Database connected\n";
    
    // 2. Check rentals table exists
    try {
        $stmt = $pdo->query("SELECT 1 FROM rentals LIMIT 1");
        echo "✓ Rentals table exists\n";
    } catch (Exception $e) {
        echo "✗ Rentals table missing!\n";
        exit(1);
    }
    
    // 3. Check columns
    $stmt = $pdo->query("DESCRIBE rentals");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(fn($c) => $c['Field'], $cols);
    
    $required = ['id', 'user_id', 'book_id', 'status', 'rent_date', 'due_date', 'duration_days'];
    $missing = array_diff($required, $colNames);
    
    if (empty($missing)) {
        echo "✓ All required columns present\n";
    } else {
        echo "✗ Missing columns: " . implode(', ', $missing) . "\n";
    }
    
    // 4. Count rentals
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Total rentals in database: $count\n";
    
    // 5. Show rentals by status
    if ($count > 0) {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM rentals GROUP BY status");
        $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\n  Rentals by status:\n";
        foreach ($byStatus as $row) {
            echo "    - {$row['status']}: {$row['count']}\n";
        }
        
        // 6. Show sample rental from RentalController
        echo "\n✓ Sample data from RentalController::getAll():\n";
        $allRentals = $rctrl->getAll();
        if (!empty($allRentals)) {
            $first = $allRentals[0];
            echo "    ID: " . $first['id'] . "\n";
            echo "    Book: " . ($first['book_title'] ?? 'N/A') . "\n";
            echo "    User: " . ($first['user_name'] ?? 'N/A') . "\n";
            echo "    Status: " . ($first['status'] ?? 'N/A') . "\n";
        }
    }
    
    echo "\n=== STATUS: ALL SYSTEMS GO ===\n";
    echo "\nNEXT TEST:\n";
    echo "1. Go to http://localhost:80/bookrent_db/public/home.php\n";
    echo "2. Rent a book (select user, dates, quantity, payment method)\n";
    echo "3. Return here and run: php status.php\n";
    echo "4. You should see the new rental in the count above\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>