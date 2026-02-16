<?php
require_once __DIR__ . '/src/bootstrap.php';
$pdo = Database::getInstance()->pdo();

echo "Users table columns:\n";
$stmt = $pdo->query('DESCRIBE users');
$cols = $stmt->fetchAll();
foreach($cols as $c) {
    echo "- " . $c['Field'] . " (" . $c['Type'] . ")\n";
}
?>
