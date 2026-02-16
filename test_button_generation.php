<?php
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/Database.php';

$db = \Database::getInstance();
$pdo = $db->pdo();

// Get a sample rental to see HTML generation
$stmt = $pdo->prepare('
    SELECT id FROM rentals LIMIT 1
');
$stmt->execute();
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== SAMPLE HTML BUTTON GENERATION ===\n\n";

echo "For Rental ID: " . $rental['id'] . "\n\n";

echo "Button HTML that would be generated:\n";
echo '<button type="button" class="btn btn-sm btn-info text-white" onclick="showReceipt(' .($rental['id']) . ')" title="View Receipt" style="cursor: pointer;"><i class="bi bi-receipt me-1"></i>View</button>' . "\n\n";

echo "JavaScript that would be in page:\n";
echo 'rentalData["' . $rental['id'] . '"] = {...};' . "\n\n";

echo "When button clicked:\n";
echo 'showReceipt(' . ($rental['id']) . ') is called' . "\n\n";

echo "Inside function:\n";
echo 'const r = rentalData[' . ($rental['id']) . '];' . "\n";
echo '// This converts to: rentalData["' . $rental['id'] . '"]' . "\n";

echo "\n✓ The keys should match\n";
?>
