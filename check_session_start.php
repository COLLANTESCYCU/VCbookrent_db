<?php
// Search for all session_start() calls
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src'));
$count = 0;

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file);
        if (preg_match('/session_start\s*\(\s*\)/', $content)) {
            echo "✗ " . str_replace('\\', '/', $file->getRealPath()) . "\n";
            $count++;
        }
    }
}

if ($count === 0) {
    echo "✓ No conflicting session_start() calls found in src/\n";
} else {
    echo "\n❌ Found $count files with session_start() - should only be in bootstrap.php!\n";
}
?>
