<?php
// Quick test to verify receipt system
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'src/bootstrap.php';

$db = new Database();

// Check if rentals exist
$result = $db->query('SELECT COUNT(*) as count FROM rentals');
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Total rentals in database: " . $row['count'] . "\n\n";

// Get first 3 rentals
$rentals = $db->query('
    SELECT 
        r.id, 
        r.rent_date, 
        r.due_date, 
        r.return_date,
        r.status,
        r.duration_days,
        r.quantity,
        r.payment_method,
        b.title,
        b.isbn,
        b.price,
        p.penalty_amount,
        p.penalty_paid
    FROM rentals r
    LEFT JOIN books b ON r.book_id = b.id
    LEFT JOIN penalties p ON r.id = p.rental_id
    LIMIT 3
')->fetchAll(PDO::FETCH_ASSOC);

echo "Sample rental data:\n";
foreach ($rentals as $rental) {
    echo "\nRental ID: " . $rental['id'] . "\n";
    echo "Book: " . $rental['title'] . "\n";
    echo "Status: " . $rental['status'] . "\n";
    echo "JSON: " . json_encode($rental) . "\n";
}

echo "\n\nTesting rentalData object initialization:\n";
echo "const rentalData = {};\n";
foreach ($rentals as $rental) {
    echo "rentalData[\"" . $rental['id'] . "\"] = " . json_encode($rental) . ";\n";
}
?>
