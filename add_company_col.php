<?php
require_once 'config.php';
$sql = "ALTER TABLE clients ADD COLUMN company VARCHAR(255) AFTER phone";
if (mysqli_query($conn, $sql)) {
    echo "Added company column to clients table.";
} else {
    echo "Error adding company column: " . mysqli_error($conn);
}
?>
