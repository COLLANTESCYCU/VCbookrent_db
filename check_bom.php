<?php
// Detect BOM and leading whitespace in PHP files
$dirs = ['src', 'public'];

function checkFile($filePath) {
    $content = file_get_contents($filePath);
    $issues = [];
    
    // Check for UTF-8 BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $issues[] = 'UTF-8 BOM detected';
    }
    
    // Check for leading whitespace before <?php
    if (preg_match('/^\s+<\?php/', $content)) {
        $issues[] = 'Leading whitespace before <?php';
    }
    
    // Check for output before first PHP tag
    if (preg_match('/^[^<]*?(?=<\?php)/', $content, $m) && trim($m[0]) !== '') {
        $issues[] = 'Content before <?php: ' . var_export($m[0], true);
    }
    
    if (!empty($issues)) {
        echo "✗ " . basename($filePath) . "\n";
        foreach ($issues as $issue) {
            echo "  - $issue\n";
        }
        return true;
    }
    return false;
}

function walkDir($dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $found = 0;
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            if (checkFile($file->getRealPath())) {
                $found++;
            }
        }
    }
    return $found;
}

$total = 0;
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $total += walkDir($dir);
    }
}

if ($total === 0) {
    echo "✓ No BOM or leading whitespace issues found!\n";
} else {
    echo "\n✗ Found $total files with issues\n";
}
?>
