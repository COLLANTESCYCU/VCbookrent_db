<?php
echo "TEST START\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=bookrent_db', 'root', '');
    echo "Connected to database\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals");
    $count = $stmt->fetch();
    echo "Total rentals: " . $count['total'] . "\n";
    
    if ($count['total'] > 0) {
        $stmt = $pdo->query("SELECT * FROM rentals LIMIT 1");
        $r = $stmt->fetch();
        print_r($r);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "TEST END\n";
?>