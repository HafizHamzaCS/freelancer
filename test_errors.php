<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h1>Debug Mode</h1>";

// Test DB Connection
if ($conn) {
    echo "<p style='color:green'>Database Connected</p>";
} else {
    die("<p style='color:red'>Database Connection Failed: " . mysqli_connect_error() . "</p>");
}

// Test Events Table
echo "<h2>Testing Events Table</h2>";
$sql = "SELECT * FROM events LIMIT 1";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo "<p style='color:green'>Query Success. Table exists.</p>";
    $row = mysqli_fetch_assoc($result);
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p style='color:red'>Query Failed: " . mysqli_error($conn) . "</p>";
}

// Check Dashboard Logic
echo "<h2>Testing Dashboard Logic</h2>";
$date_check = date('Y-m-d');
echo "Current Date: $date_check<br>";

$events_sql = "SELECT * FROM events WHERE status = 'Upcoming' AND start_date >= CURDATE() ORDER BY start_date ASC LIMIT 3";
$res2 = mysqli_query($conn, $events_sql);
if ($res2) {
    echo "<p style='color:green'>Dashboard Query Success.</p>";
    while($r = mysqli_fetch_assoc($res2)) {
        echo "Event: " . $r['title'] . "<br>";
    }
} else {
    echo "<p style='color:red'>Dashboard Query Failed: " . mysqli_error($conn) . "</p>";
}
?>
