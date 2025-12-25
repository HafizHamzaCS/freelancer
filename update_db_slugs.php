<?php
require_once 'config.php';

// Add slug column to clients table
$sql_clients = "ALTER TABLE clients ADD COLUMN slug VARCHAR(255) UNIQUE AFTER name";
if (mysqli_query($conn, $sql_clients)) {
    echo "Added slug column to clients table.<br>";
} else {
    echo "Error adding slug column to clients table: " . mysqli_error($conn) . "<br>";
}

// Add slug column to projects table
$sql_projects = "ALTER TABLE projects ADD COLUMN slug VARCHAR(255) UNIQUE AFTER name";
if (mysqli_query($conn, $sql_projects)) {
    echo "Added slug column to projects table.<br>";
} else {
    echo "Error adding slug column to projects table: " . mysqli_error($conn) . "<br>";
}

echo "Database update complete.";
?>
