<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    color VARCHAR(20) DEFAULT 'primary',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "Table 'events' created successfully.";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

// Data Migration (Optional: Add defaults if empty)
$check = mysqli_query($conn, "SELECT * FROM events");
if (mysqli_num_rows($check) == 0) {
    $defaults = [
        ['Black Friday', '2025-11-28', '2025-11-30', 'Inactive'],
        ['Cyber Monday', '2025-12-01', '2025-12-01', 'Inactive'],
        ['New Year', '2025-12-31', '2026-01-01', 'Inactive']
    ];
    
    foreach ($defaults as $evt) {
        $title = $evt[0];
        $start = $evt[1];
        $end = $evt[2];
        $status = $evt[3];
        mysqli_query($conn, "INSERT INTO events (title, start_date, end_date, status) VALUES ('$title', '$start', '$end', '$status')");
    }
    echo "\nDefault events added.";
}
?>

then i want you to add the 