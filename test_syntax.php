<?php
require_once 'src/bootstrap.php';

$pages = [
    'public/books.php',
    'public/rentals.php',
    'public/login.php',
    'public/register.php',
    'public/dashboard.php',
    'public/inventory.php',
    'public/penalties.php',
    'public/reports.php',
    'public/transactions.php',
];

echo "Testing PHP files for syntax errors...\n\n";

$errors = 0;
foreach ($pages as $page) {
    $output = shell_exec("php -l " . escapeshellarg($page) . " 2>&1");
    if (strpos($output, 'No syntax errors') === false) {
        echo "✗ $page\n$output\n";
        $errors++;
    } else {
        echo "✓ $page\n";
    }
}

if ($errors === 0) {
    echo "\n✅ All pages are syntactically correct!\n";
} else {
    echo "\n❌ Found $errors files with errors\n";
}
?>
