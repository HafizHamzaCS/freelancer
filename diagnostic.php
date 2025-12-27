<?php
/**
 * DIAGNOSTIC SCRIPT FOR DASHBOARD
 * Save this as: diagnostic.php
 * Run it: http://yourdomain.com/diagnostic.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Dashboard Diagnostic</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.test { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #ccc; }
.pass { border-left-color: #4caf50; }
.fail { border-left-color: #f44336; background: #fff5f5; }
.warn { border-left-color: #ff9800; background: #fffbf0; }
h1 { color: #333; }
h2 { color: #666; margin-top: 30px; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
.status { font-weight: bold; }
.pass .status { color: #4caf50; }
.fail .status { color: #f44336; }
.warn .status { color: #ff9800; }
</style></head><body>";

echo "<h1>🔍 Dashboard Diagnostic Tool</h1>";
echo "<p>This tool checks for common issues causing 500 errors in your dashboard.</p>";

// Test 1: PHP Version
echo "<h2>1. PHP Environment</h2>";
$php_version = phpversion();
$php_ok = version_compare($php_version, '7.0', '>=');
echo "<div class='test " . ($php_ok ? 'pass' : 'fail') . "'>";
echo "<span class='status'>" . ($php_ok ? "✓ PASS" : "✗ FAIL") . "</span> ";
echo "PHP Version: <strong>$php_version</strong>";
if (!$php_ok) echo " (Minimum required: 7.0)";
echo "</div>";

// Test 2: Required Extensions
echo "<h2>2. PHP Extensions</h2>";
$required_extensions = ['mysqli', 'pdo', 'pdo_mysql', 'session', 'json'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<div class='test " . ($loaded ? 'pass' : 'fail') . "'>";
    echo "<span class='status'>" . ($loaded ? "✓ PASS" : "✗ FAIL") . "</span> ";
    echo "Extension: <code>$ext</code>";
    echo "</div>";
}

// Test 3: File Existence
echo "<h2>3. Required Files</h2>";
$required_files = [
    'header.php',
    'footer.php',
    'dashboard.php'
];

foreach ($required_files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $readable = $exists && is_readable(__DIR__ . '/' . $file);
    echo "<div class='test " . ($readable ? 'pass' : 'fail') . "'>";
    echo "<span class='status'>" . ($readable ? "✓ PASS" : "✗ FAIL") . "</span> ";
    echo "File: <code>$file</code>";
    if ($exists && !$readable) echo " (File exists but not readable - check permissions)";
    if (!$exists) echo " (File not found in: " . __DIR__ . ")";
    echo "</div>";
}

// Test 4: Session
echo "<h2>4. Session Functionality</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['diagnostic_test'] = 'working';
    $session_ok = isset($_SESSION['diagnostic_test']);
    
    echo "<div class='test " . ($session_ok ? 'pass' : 'fail') . "'>";
    echo "<span class='status'>" . ($session_ok ? "✓ PASS" : "✗ FAIL") . "</span> ";
    echo "Session Test";
    echo "</div>";
    
    unset($_SESSION['diagnostic_test']);
} catch (Exception $e) {
    echo "<div class='test fail'>";
    echo "<span class='status'>✗ FAIL</span> Session Test: " . $e->getMessage();
    echo "</div>";
}

// Test 5: Check if header.php loads
echo "<h2>5. Header File Loading</h2>";
try {
    ob_start();
    @include __DIR__ . '/header.php';
    $header_output = ob_get_clean();
    $header_ok = !empty($header_output) || defined('APP_NAME');
    
    echo "<div class='test " . ($header_ok ? 'pass' : 'warn') . "'>";
    echo "<span class='status'>" . ($header_ok ? "✓ PASS" : "⚠ WARNING") . "</span> ";
    echo "Header file loads";
    if (!$header_ok) echo " (May need database connection)";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='test fail'>";
    echo "<span class='status'>✗ FAIL</span> Header loading error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Test 6: Database Functions
echo "<h2>6. Database Functions</h2>";
$db_functions = ['db_fetch_all', 'db_fetch_one', 'db_query'];
foreach ($db_functions as $func) {
    $exists = function_exists($func);
    echo "<div class='test " . ($exists ? 'pass' : 'fail') . "'>";
    echo "<span class='status'>" . ($exists ? "✓ PASS" : "✗ FAIL") . "</span> ";
    echo "Function: <code>$func()</code>";
    if (!$exists) echo " (Ensure database file is loaded in header.php)";
    echo "</div>";
}

// Test 7: Error Log
echo "<h2>7. Error Logging</h2>";
$error_log = ini_get('error_log');
$log_exists = !empty($error_log) && file_exists($error_log);
echo "<div class='test " . ($log_exists ? 'pass' : 'warn') . "'>";
echo "<span class='status'>" . ($log_exists ? "✓" : "⚠") . "</span> ";
echo "Error log location: <code>" . ($error_log ?: 'Not set') . "</code>";
echo "</div>";

// Check for recent errors
if ($log_exists) {
    $recent_errors = @file_get_contents($error_log);
    if ($recent_errors) {
        $lines = explode("\n", $recent_errors);
        $recent = array_slice(array_filter($lines), -10);
        
        if (!empty($recent)) {
            echo "<div class='test warn'>";
            echo "<span class='status'>⚠ WARNING</span> Recent errors found:";
            echo "<pre style='margin-top:10px; background:#f9f9f9; padding:10px; overflow-x:auto;'>";
            echo htmlspecialchars(implode("\n", $recent));
            echo "</pre></div>";
        }
    }
}

// Test 8: Memory Limit
echo "<h2>8. PHP Configuration</h2>";
$memory_limit = ini_get('memory_limit');
$max_execution = ini_get('max_execution_time');

echo "<div class='test pass'>";
echo "<span class='status'>ℹ INFO</span> ";
echo "Memory Limit: <strong>$memory_limit</strong>";
echo "</div>";

echo "<div class='test pass'>";
echo "<span class='status'>ℹ INFO</span> ";
echo "Max Execution Time: <strong>{$max_execution}s</strong>";
echo "</div>";

// Test 9: Syntax Check
echo "<h2>9. Syntax Validation</h2>";
if (file_exists(__DIR__ . '/dashboard.php')) {
    $output = [];
    $return_var = 0;
    @exec('php -l ' . escapeshellarg(__DIR__ . '/dashboard.php') . ' 2>&1', $output, $return_var);
    
    $syntax_ok = $return_var === 0;
    echo "<div class='test " . ($syntax_ok ? 'pass' : 'fail') . "'>";
    echo "<span class='status'>" . ($syntax_ok ? "✓ PASS" : "✗ FAIL") . "</span> ";
    echo "Dashboard syntax check";
    if (!$syntax_ok && !empty($output)) {
        echo "<pre style='margin-top:10px; background:#fff; padding:10px; border:1px solid #f44336;'>";
        echo htmlspecialchars(implode("\n", $output));
        echo "</pre>";
    }
    echo "</div>";
}

// Test 10: Permissions
echo "<h2>10. File Permissions</h2>";
$dashboard_file = __DIR__ . '/dashboard.php';
if (file_exists($dashboard_file)) {
    $perms = fileperms($dashboard_file);
    $perms_octal = substr(sprintf('%o', $perms), -4);
    $is_writable = is_writable($dashboard_file);
    
    echo "<div class='test pass'>";
    echo "<span class='status'>ℹ INFO</span> ";
    echo "Dashboard permissions: <strong>$perms_octal</strong>";
    echo " (Writable: " . ($is_writable ? 'Yes' : 'No') . ")";
    echo "</div>";
}

// Summary
echo "<h2>📋 Summary & Next Steps</h2>";
echo "<div class='test'>";
echo "<ol style='line-height: 1.8;'>";
echo "<li>If any tests failed, fix those issues first</li>";
echo "<li>Check your error log at: <code>$error_log</code></li>";
echo "<li>Enable debug mode: Add <code>?debug=1</code> to dashboard URL</li>";
echo "<li>Check database connection in header.php</li>";
echo "<li>Review the error log created at: <code>" . __DIR__ . "/dashboard-errors.log</code></li>";
echo "</ol>";
echo "</div>";

echo "<div class='test warn'>";
echo "<span class='status'>🔒 SECURITY</span> ";
echo "<strong>Important:</strong> Delete this diagnostic.php file after troubleshooting!";
echo "</div>";

echo "</body></html>";
?>
