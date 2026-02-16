<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/src/bootstrap.php';
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/Controllers/RentalController.php';
    
    $rctrl = new RentalController();
    $rentals = $rctrl->getAll();
    
    echo "Total rentals: " . count($rentals) . "\n\n";
    
    if (!empty($rentals)) {
        $first = $rentals[0];
        echo "First rental columns:\n";
        foreach (array_keys($first) as $col) {
            echo "  - $col: " . json_encode($first[$col]) . "\n";
        }
        
        echo "\n\nFirst rental full data:\n";
        echo json_encode($first, JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>