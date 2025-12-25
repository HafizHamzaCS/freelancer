<?php
require_once 'config.php';
$result = mysqli_query($conn, "SHOW COLUMNS FROM clients");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . "<br>";
}
?>
