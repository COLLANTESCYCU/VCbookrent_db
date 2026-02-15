<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Models/Book.php';

$books = new Book();

// Get first 5 books
try {
    $stmt = $books->pdo->query("SELECT id, title, image FROM books LIMIT 5");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>Books in Database</h2>";
    echo "<table border='1'><tr><th>ID</th><th>Title</th><th>Image Column</th><th>Authors</th></tr>";
    foreach ($results as $book) {
        $authors = $books->getAuthors($book['id']);
        echo "<tr>";
        echo "<td>" . $book['id'] . "</td>";
        echo "<td>" . htmlspecialchars($book['title']) . "</td>";
        echo "<td>" . htmlspecialchars($book['image'] ?? 'NULL') . "</td>";
        echo "<td>" . implode(', ', $authors) . " (" . count($authors) . " authors)</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check book_authors table
echo "<h3>book_authors Table Sample</h3>";
try {
    $stmt = $books->pdo->query("SELECT * FROM book_authors LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "<p>No authors found in book_authors table!</p>";
    } else {
        echo "<table border='1'><tr><th>Book ID</th><th>Author Order</th><th>Author Name</th></tr>";
        foreach ($results as $row) {
            echo "<tr><td>" . $row['book_id'] . "</td><td>" . $row['author_order'] . "</td><td>" . htmlspecialchars($row['author_name']) . "</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error querying book_authors: " . $e->getMessage();
}

// Check uploads directory
echo "<h3>Files in uploads directory</h3>";
$uploadDir = __DIR__ . '/public/uploads';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo "<p>Files: " . implode(', ', array_filter($files, fn($f) => $f !== '.' && $f !== '..' && $f !== '.gitkeep')) . "</p>";
} else {
    echo "<p>Upload directory not found</p>";
}
?>
