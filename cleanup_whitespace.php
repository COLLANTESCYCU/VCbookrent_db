<?php
// Script to remove trailing whitespace from all PHP files
$dirs = ['src', 'public'];

function cleanPhpFile($filePath) {
    $content = file_get_contents($filePath);
    
    // Remove closing PHP tags and trailing whitespace
    $content = preg_replace('/\?>\s*$/', '', $content);
    // Remove trailing whitespace before the final closing brace
    $content = rtrim($content);
    
    file_put_contents($filePath, $content);
    echo "✓ Cleaned: $filePath\n";
}

function walkDir($dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            cleanPhpFile($file->getRealPath());
        }
    }
}

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        walkDir($dir);
    }
}

echo "\n✓ All PHP files cleaned!\n";
?>
