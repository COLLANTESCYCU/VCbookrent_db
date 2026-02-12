<?php
// Comprehensive check for all pages
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$pages = glob('public/*.php');
sort($pages);

echo "Checking all public PHP files for bootstrap.php...\n\n";

foreach ($pages as $page) {
    $content = file_get_contents($page);
    
    // Check if bootstrap.php is included
    $hasBootstrap = strpos($content, "require_once __DIR__ . '/../src/bootstrap.php'") !== false;
    $hasSessionStart = preg_match('/session_start\s*\(\s*\)/', $content);
    
    $marker = $hasBootstrap ? '✓' : '✗';
    echo "$marker " . basename($page);
    
    if (!$hasBootstrap && $hasSessionStart) {
        echo " [WARNING: has session_start() but no bootstrap]";
    } elseif ($hasBootstrap && $hasSessionStart) {
        echo " [DOUBLE: both bootstrap AND session_start()]";
    }
    
    echo "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Summary:\n";
echo "✓ = Has bootstrap.php (GOOD)\n";
echo "✗ = Missing bootstrap.php (needs fixing)\n";
echo "[WARNING] = Has session_start() without bootstrap\n";
echo "[DOUBLE] = Has both bootstrap AND session_start() (redundant)\n";
?>
