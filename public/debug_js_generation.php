<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';

$auth = Auth::getInstance();
$currentUser = $auth->currentUser();

if (!$currentUser || $currentUser['role'] !== 'user') {
    die('Must be logged in as regular user');
}

$db = \Database::getInstance();
$pdo = $db->pdo();

// Get rental data (same query as rental_history.php)
$stmt = $pdo->prepare('
    SELECT 
        r.id, 
        r.rent_date, 
        r.due_date, 
        r.return_date, 
        r.status, 
        r.duration_days,
        r.quantity,
        r.payment_method,
        r.cash_received,
        r.change_amount,
        b.title,
        b.isbn,
        b.price,
        p.amount as penalty_amount,
        p.paid as penalty_paid
    FROM rentals r
    JOIN books b ON r.book_id = b.id
    LEFT JOIN penalties p ON r.id = p.rental_id
    WHERE r.user_id = :user_id
    ORDER BY r.rent_date DESC
');
$stmt->execute(['user_id' => $currentUser['id']]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== JAVASCRIPT CODE GENERATION DEBUG ===\n\n";
echo "Your user ID: " . $currentUser['id'] . "\n";
echo "Rentals found: " . count($rentals) . "\n\n";

if (count($rentals) === 0) {
    echo "❌ ERROR: No rentals found for your user!\n";
    echo "This means the rental_history.php page will have no empty rentalData object\n";
    echo "And the 'View Receipt' buttons won't be rendered at all\n";
    exit;
}

echo "✓ Found " . count($rentals) . " rentals\n\n";

echo "The following JavaScript will be generated in the <script> tag:\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "const rentalData = {};\n";
foreach ($rentals as $rental) {
    $json = json_encode($rental);
    echo "rentalData[\"" . htmlspecialchars($rental['id']) . "\"] = " . htmlspecialchars($json) . ";\n";
}

echo "\n" . "=" . str_repeat("=", 50) . "\n\n";

echo "Generated code check:\n";
echo "✓ rentalData variable initialized\n";
echo "✓ " . count($rentals) . " rental(s) added to object\n";
echo "✓ Keys are: " . implode(", ", array_map(function($r) { return '"' . $r['id'] . '"'; }, $rentals)) . "\n\n";

echo "When 'View Receipt' button is clicked:\n";
foreach ($rentals as $i => $rental) {
    echo "  " . ($i + 1) . ". Button onclick=\"showReceipt(" . $rental['id'] . ")\" fires\n";
    echo "     → showReceipt(" . $rental['id'] . ") is called\n";
    echo "     → const r = rentalData[" . $rental['id'] . "];\n";
    echo "     → Looks for rentalData[\"" . $rental['id'] . "\"] ← STRING key\n";
    echo "     → ✓ Found (because PHP < ?= ?> outputs string key)\n\n";
}

echo "Potential issues to check:\n";
echo "1. Make sure rental buttons are displayed in rental_history.php\n";
echo "2. Open browser console (F12) when clicking View Receipt\n";
echo "3. Check for JavaScript errors\n";
echo "4. Verify rentalData object is populated: \n";
echo "   → Type: Object.keys(rentalData) in console\n";
echo "   → Should show: [\"" . $rentals[0]['id'] . "\", ...]\n";
?>
