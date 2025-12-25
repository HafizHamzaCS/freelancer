<?php
require_once 'config.php';

echo "<h2>Running Database Updates...</h2>";

// 1. Create New Tables
$new_tables = [
    "teams" => "CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        description TEXT,
        leader_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "team_members" => "CREATE TABLE IF NOT EXISTS team_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT,
        user_id INT,
        role VARCHAR(50) DEFAULT 'Member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($new_tables as $name => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "Table '$name' checked/created successfully.<br>";
    } else {
        echo "Error creating table '$name': " . mysqli_error($conn) . "<br>";
    }
}

// 2. Alter Projects Table
$project_cols = mysqli_query($conn, "SHOW COLUMNS FROM projects");
$p_cols = [];
while ($row = mysqli_fetch_assoc($project_cols)) {
    $p_cols[] = $row['Field'];
}

if (!in_array('team_id', $p_cols)) {
    if (mysqli_query($conn, "ALTER TABLE projects ADD COLUMN team_id INT DEFAULT NULL")) {
        echo "Column 'team_id' added to 'projects' table.<br>";
    } else {
        echo "Error adding 'team_id' to 'projects': " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "Column 'team_id' already exists in 'projects'.<br>";
}

// 3. Update Project Status Enum to include 'Queue'
$status_check = mysqli_query($conn, "SHOW COLUMNS FROM projects LIKE 'status'");
$row = mysqli_fetch_assoc($status_check);
if (strpos($row['Type'], "'Queue'") === false) {
    // Current enum: enum('Pending','In Progress','On Hold','Completed')
    // New enum: enum('Queue','Pending','In Progress','On Hold','Completed')
    $sql = "ALTER TABLE projects MODIFY COLUMN status ENUM('Queue','Pending','In Progress','On Hold','Completed') DEFAULT 'Queue'";
    if (mysqli_query($conn, $sql)) {
        echo "Project status ENUM updated to include 'Queue'.<br>";
    } else {
        echo "Error updating status ENUM: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "Project status ENUM already includes 'Queue'.<br>";
}

echo "<h3>Update Complete!</h3>";
echo "<a href='index.php'>Go to Dashboard</a>";
?>
