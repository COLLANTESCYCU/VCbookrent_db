<?php
/**
 * Direct check of rentals table - bypasses controllers for troubleshooting
 */
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

$pdo = Database::getInstance()->pdo();

echo "<h3>Direct Rental Query Tests</h3>";

// Test 1: Count all rentals
echo "<h4>Test 1: Count all rentals</h4>";
$stmt = $pdo->query("SELECT COUNT(*) as count FROM rentals");
$result = $stmt->fetch();
echo "<p>Total rentals in database: <strong>" . $result['count'] . "</strong></p>";

// Test 2: Show all rentals with basic info
echo "<h4>Test 2: All rentals with book/user info</h4>";
$stmt = $pdo->query("SELECT r.id, r.status, r.book_id, r.user_id, r.rent_date, b.title, u.fullname FROM rentals r LEFT JOIN books b ON r.book_id = b.id LEFT JOIN users u ON r.user_id = u.id LIMIT 20");
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rentals)) {
    echo "<p style='color:red;'><strong>NO RENTALS FOUND!</strong></p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Status</th><th>Book ID</th><th>User ID</th><th>Book Title</th><th>User Name</th><th>Rent Date</th></tr>";
    foreach ($rentals as $r) {
        echo "<tr>";
        echo "<td>" . $r['id'] . "</td>";
        echo "<td>" . $r['status'] . "</td>";
        echo "<td>" . $r['book_id'] . "</td>";
        echo "<td>" . $r['user_id'] . "</td>";
        echo "<td>" . ($r['title'] ?? 'NULL') . "</td>";
        echo "<td>" . ($r['fullname'] ?? 'NULL') . "</td>";
        echo "<td>" . $r['rent_date'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Check books table
echo "<h4>Test 3: Sample books in database</h4>";
$stmt = $pdo->query("SELECT id, title, isbn, available_copies FROM books LIMIT 5");
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($books)) {
    echo "<p style='color:red;'>NO BOOKS FOUND!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>ISBN</th><th>Available</th></tr>";
    foreach ($books as $b) {
        echo "<tr><td>" . $b['id'] . "</td><td>" . $b['title'] . "</td><td>" . $b['isbn'] . "</td><td>" . $b['available_copies'] . "</td></tr>";
    }
    echo "</table>";
}

// Test 4: Check users table
echo "<h4>Test 4: Sample users in database</h4>";
$stmt = $pdo->query("SELECT id, fullname, email, role FROM users LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($users)) {
    echo "<p style='color:red;'>NO USERS FOUND!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>" . $u['id'] . "</td><td>" . $u['fullname'] . "</td><td>" . $u['email'] . "</td><td>" . $u['role'] . "</td></tr>";
    }
    echo "</table>";
}

// Test 5: Check rental table schema
echo "<h4>Test 5: Rental table columns</h4>";
$stmt = $pdo->query("DESCRIBE rentals");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . ($col['Key'] ?? '') . "</td>";
    echo "<td>" . ($col['Default'] ?? '') . "</td>";
    echo "<td>" . ($col['Extra'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

?>