<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Info</h1>";
echo "PHP Version: " . phpversion() . "<br>";

try {
    echo "Including config.php...<br>";
    require_once 'config.php';
    echo "config.php included successfully.<br>";

    echo "Including functions.php...<br>";
    require_once 'functions.php';
    echo "functions.php included successfully.<br>";

    echo "Including header.php...<br>";
    include 'header.php'; // Use include to avoid exit on error if possible
    echo "header.php included successfully.<br>";

} catch (Error $e) {
    echo "<h2>Caught Error:</h2>";
    echo $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
} catch (Exception $e) {
    echo "<h2>Caught Exception:</h2>";
    echo $e->getMessage();
}

echo "<h2>Done</h2>";
?>
