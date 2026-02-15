<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Models/Book.php';

$bookModel = new Book();
$pdo = Database::getInstance()->pdo();

echo "<h2>Checking Image Display Issues</h2>";

// Get books with images
$stmt = $pdo->query("SELECT id, title, image FROM books WHERE image IS NOT NULL AND image != '' LIMIT 10");
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Books in Database with Images:</h3>";
if (empty($books)) {
    echo "<p style='color:red;'>No books with images found in database!</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Image Column</th><th>File Exists</th><th>Display Test</th></tr>";
    foreach ($books as $b) {
        $uploadDir = realpath(__DIR__ . '/public/uploads');
        $filePath = $uploadDir . '/' . $b['image'];
        $exists = is_file($filePath) ? '✅ Yes' : '❌ No';
        
        // Image path options
        $path1 = "/bookrent_db/public/uploads/" . htmlspecialchars($b['image']);
        $path2 = "uploads/" . htmlspecialchars($b['image']);
        $path3 = "/VCbookrent_db/public/uploads/" . htmlspecialchars($b['image']);
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($b['id']) . "</td>";
        echo "<td>" . htmlspecialchars($b['title']) . "</td>";
        echo "<td>" . htmlspecialchars($b['image']) . "</td>";
        echo "<td>" . $exists . "</td>";
        echo "<td>";
        echo "<img src='" . $path1 . "' alt='Test' style='max-width:80px; margin:5px;' onerror='this.style.display=\"none\"' />";
        if (is_file($filePath)) {
            echo "✅ Path 1 works<br>";
        } else {
            echo "❌ Path 1 failed<br>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Files in uploads directory:</h3>";
$dir = __DIR__ . '/public/uploads';
if (is_dir($dir)) {
    $files = array_diff(scandir($dir), ['.', '..', '.gitkeep']);
    echo "Total files: " . count($files) . "<br>";
    echo "Files: " . implode(', ', array_slice($files, 0, 10)) . (count($files) > 10 ? '...' : '');
} else {
    echo "Upload directory not found!";
}
?>
