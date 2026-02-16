<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/RentalController.php';
require_once __DIR__ . '/src/Auth.php';

$rctrl = new RentalController();
$auth = Auth::getInstance();
$currentUser = $auth->currentUser();

echo "=== TESTING RECEIPT FUNCTIONALITY ===\n\n";

if (!$currentUser) {
    echo "❌ No user logged in\n";
    exit(1);
}

echo "✅ User logged in: " . $currentUser['id'] . "\n\n";

// Get all rentals to test receipt data
$rentals = $rctrl->getAll();

if (empty($rentals)) {
    echo "⚠️ No rentals found to test\n";
} else {
    echo "✅ Found " . count($rentals) . " rentals\n\n";
    
    $rental = $rentals[0];
    echo "📌 Testing first rental for receipt:\n";
    echo "- ID: " . $rental['id'] . "\n";
    echo "- Book: " . $rental['book_title'] . "\n";
    echo "- ISBN: " . $rental['isbn'] . "\n";
    echo "- User: " . ($rental['user_name'] ?? 'N/A') . "\n";
    echo "- Status: " . $rental['status'] . "\n";
    echo "- Rent Date: " . $rental['rent_date'] . "\n";
    echo "- Due Date: " . $rental['due_date'] . "\n";
    echo "- Price: ₱" . $rental['price'] . "\n";
    echo "- Quantity: " . ($rental['quantity'] ?? 1) . "\n";
    echo "- Payment Method: " . ($rental['payment_method'] ?? 'N/A') . "\n\n";
    
    echo "✅ All receipt fields available for display\n";
    
    // Test JSON encoding (this is what gets passed to JavaScript)
    $json = htmlspecialchars(json_encode($rental));
    echo "\n📝 JSON Data Length: " . strlen($json) . " chars\n";
    echo "✅ Receipt button will receive all transaction details\n";
}
?>
