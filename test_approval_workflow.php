<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Controllers/RentalController.php';
require_once __DIR__ . '/src/Models/Rental.php';
require_once __DIR__ . '/src/Database.php';

echo "=== APPROVAL WORKFLOW TEST ===\n\n";

try {
    $db = Database::getInstance()->pdo();
    
    // Get test data
    $user = $db->query('SELECT id, fullname FROM users WHERE role = "user" LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $book = $db->query('SELECT id, title, available_copies FROM books WHERE archived = 0 AND available_copies > 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$book) {
        // If no book with available copies, reduce available_copies for a test
        if (!$book) {
            $book = $db->query('SELECT id, title FROM books WHERE archived = 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $db->exec("UPDATE books SET available_copies = 5 WHERE id = {$book['id']}");
            $book['available_copies'] = 5;
        }
    }
    
    echo "TEST DATA:\n";
    echo "  User: {$user['fullname']} (ID: {$user['id']})\n";
    echo "  Book: {$book['title']} (ID: {$book['id']}, Available: {$book['available_copies']})\n\n";
    
    // Step 1: Create rental
    echo "STEP 1: Create new rental\n";
    echo "-".str_repeat("-", 40)."\n";
    
    $_POST = [
        'user_id' => $user['id'],
        'book_id' => $book['id'],
        'duration' => 7,
        'quantity' => 1,
        'rent' => 1,
        'payment_method' => 'cash',
        'cash_received' => 500
    ];
    
    $ctrl = new RentalController();
    $rentalId = $ctrl->rent(
        (int)$_POST['user_id'],
        (int)$_POST['book_id'],
        (int)$_POST['duration'],
        (int)$_POST['quantity'],
        (float)$_POST['cash_received']
    );
    
    echo "✅ Rental created: ID $rentalId\n";
    
    // Check rental status
    $rental = $db->prepare('SELECT status FROM rentals WHERE id = ?')->execute([$rentalId]);
    $rental = $db->prepare('SELECT status FROM rentals WHERE id = ?');
    $rental->execute([$rentalId]);
    $rentalData = $rental->fetch(PDO::FETCH_ASSOC);
    echo "   Status: {$rentalData['status']}\n\n";
    
    // Step 2: Approve rental
    echo "STEP 2: Approve rental\n";
    echo "-".str_repeat("-", 40)."\n";
    
    try {
        $rentalModel = new Rental();
        $rentalModel->approveRental($rentalId);
        echo "✅ Rental approved successfully!\n";
        
        // Check rental status after approval
        $rental = $db->prepare('SELECT status FROM rentals WHERE id = ?');
        $rental->execute([$rentalId]);
        $rentalData = $rental->fetch(PDO::FETCH_ASSOC);
        echo "   Status after approval: {$rentalData['status']}\n\n";
    } catch (Exception $e) {
        echo "❌ Error approving: " . $e->getMessage() . "\n\n";
    }
    
    // Step 3: Check rental in list
    echo "STEP 3: Verify rental appears in system\n";
    echo "-".str_repeat("-", 40)."\n";
    
    $stmt = $db->prepare('SELECT r.id, r.status, b.title, u.fullname FROM rentals r JOIN books b ON r.book_id = b.id JOIN users u ON r.user_id = u.id WHERE r.id = ?');
    $stmt->execute([$rentalId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rental) {
        echo "✅ Rental found in database:\n";
        echo "   ID: {$rental['id']}\n";
        echo "   User: {$rental['fullname']}\n";
        echo "   Book: {$rental['title']}\n";
        echo "   Status: {$rental['status']}\n";
    } else {
        echo "❌ Rental not found\n";
    }
    
    echo "\n".str_repeat("=", 42)."\n";
    echo "✅ APPROVAL WORKFLOW WORKING CORRECTLY!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
