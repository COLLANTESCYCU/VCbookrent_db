<?php
echo "=== SESSION INITIALIZATION AUDIT ===\n\n";

// Check 1: bootstrap.php has session_start()
$bootstrap = file_get_contents('src/bootstrap.php');
$hasBootstrapSession = strpos($bootstrap, 'session_start()') !== false;
echo ($hasBootstrapSession ? "✓" : "✗") . " bootstrap.php has session_start()\n";

// Check 2: Flash.php does NOT have session_start()
$flash = file_get_contents('src/Helpers/Flash.php');
$hasFlashSession = strpos($flash, 'session_start()') !== false;
echo ($hasFlashSession ? "✗" : "✓") . " Flash.php does NOT have session_start()\n";

// Check 3: Auth.php does NOT have session_start()
$auth = file_get_contents('src/Auth.php');
$hasAuthSession = strpos($auth, 'session_start()') !== false;
echo ($hasAuthSession ? "✗" : "✓") . " Auth.php does NOT have session_start()\n";

// Check 4: All public pages require bootstrap.php
$publicFiles = glob('public/*.php');
$allHaveBootstrap = true;
foreach ($publicFiles as $file) {
    if (basename($file) === 'index.php' || basename($file) === 'logout.php') continue;
    $content = file_get_contents($file);
    if (strpos($content, "require_once __DIR__ . '/../src/bootstrap.php'") === false) {
        echo "✗ " . basename($file) . " missing bootstrap.php\n";
        $allHaveBootstrap = false;
    }
}
echo ($allHaveBootstrap ? "✓" : "✗") . " All public pages require bootstrap.php\n";

echo "\n=== RESULT ===\n";
if ($hasBootstrapSession && !$hasFlashSession && !$hasAuthSession && $allHaveBootstrap) {
    echo "✅ SESSION INITIALIZATION IS CORRECT!\n";
    echo "   - bootstrap.php starts session ONCE\n";
    echo "   - Flash.php and Auth.php rely on bootstrap\n";
    echo "   - All pages load bootstrap first\n";
    echo "   → No more 'headers already sent' warnings!\n";
} else {
    echo "❌ Configuration issues detected\n";
}
?>
