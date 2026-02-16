<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Models/Book.php';

$book = new Book();
$latest = $book->search('', false);

echo "<h2>All Books in Database</h2>";
echo "<pre>";
echo "Total: " . count($latest) . "\n\n";
foreach ($latest as $b) {
    echo "ID: " . $b['id'] . " | ISBN: " . $b['isbn'] . " | Title: " . $b['title'] . " | Stock: " . $b['stock_count'] . " | Available: " . $b['available_copies'] . "\n";
}
echo "</pre>";
?>
