<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';

$auth = Auth::getInstance();
$currentUser = $auth->currentUser();

echo "=== RENTAL HISTORY PAGE DEBUG ===\n\n";

if (!$currentUser) {
    echo "❌ NOT LOGGED IN\n";
    echo "   You must login first\n";
    echo "   Go to: login.php\n";
    echo "\n   Test users with rentals:\n";
    echo "   - cyra@gmail.com (role: user, has rental 31)\n";
    echo "   - sabrina@gmail.com (role: user, has rental 32)\n";
    exit;
}

echo "✓ LOGGED IN\n";
echo "  Email: " . $currentUser['email'] . "\n";
echo "  Role: " . $currentUser['role'] . "\n";
echo "  User ID: " . $currentUser['id'] . "\n\n";

// Check role
if ($currentUser['role'] !== 'user') {
    echo "❌ ROLE CHECK FAILED\n";
    echo "   Your role is: " . $currentUser['role'] . "\n";
    echo "   rental_history.php only allows role='user'\n";
    echo "   You'll be redirected to dashboard.php\n";
    exit;
}

echo "✓ ROLE CHECK PASSED\n";
echo "   Your role is 'user', you can view rental_history.php\n\n";

// Check rentals
$db = \Database::getInstance();
$pdo = $db->pdo();

$stmt = $pdo->prepare('
    SELECT COUNT(*) FROM rentals WHERE user_id = ?
');
$stmt->execute([$currentUser['id']]);
$rentalCount = $stmt->fetchColumn();

echo "Rentals for your user:\n";
echo "  Total count: " . $rentalCount . "\n";

if ($rentalCount == 0) {
    echo "\n❌ NO RENTALS FOUND\n";
    echo "   You don't have any rentals assigned\n";
    echo "   The receipt buttons won't appear because there's no rental data\n";
    echo "   Go rent a book first!\n";
    exit;
}

echo "   ✓ Found " . $rentalCount . " rental(s)\n\n";

// Get actual rentals
$stmt = $pdo->prepare('
    SELECT 
        r.id, r.status, b.title
    FROM rentals r
    JOIN books b ON r.book_id = b.id
    WHERE r.user_id = ?
    ORDER BY r.rent_date DESC
');
$stmt->execute([$currentUser['id']]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Your rentals:\n";
foreach ($rentals as $rental) {
    echo "  - Rental #" . $rental['id'] . ": " . $rental['title'] . " (" . $rental['status'] . ")\n";
}

echo "\n✅ SUCCESS\n";
echo "   Go to: rental_history.php\n";
echo "   You should see these rentals with 'View Receipt' buttons\n";
echo "   Click the buttons to test the receipt modal\n";
?>
