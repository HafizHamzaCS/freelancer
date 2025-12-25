<?php
require_once 'config.php';

echo "<h1>Applying Security & Integrity Updates...</h1>";

// 1. Create system_activity table
$sql = "CREATE TABLE IF NOT EXISTS system_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "<p style='color:green'>Created table: system_activity</p>";
} else {
    echo "<p style='color:red'>Error creating system_activity: " . mysqli_error($conn) . "</p>";
}

// 2. Add deleted_at to projects
$cols = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM projects");
while($r = mysqli_fetch_assoc($res)) $cols[] = $r['Field'];

if (!in_array('deleted_at', $cols)) {
    if (mysqli_query($conn, "ALTER TABLE projects ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL")) {
        echo "<p style='color:green'>Added column: projects.deleted_at</p>";
    } else {
        echo "<p style='color:red'>Error altering projects: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color:gray'>Column projects.deleted_at already exists.</p>";
}

// 3. Add deleted_at to tasks
$cols = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM tasks");
while($r = mysqli_fetch_assoc($res)) $cols[] = $r['Field'];

if (!in_array('deleted_at', $cols)) {
    if (mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL")) {
        echo "<p style='color:green'>Added column: tasks.deleted_at</p>";
    } else {
        echo "<p style='color:red'>Error altering tasks: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color:gray'>Column tasks.deleted_at already exists.</p>";
}

echo "<h3>Done!</h3>";
?>
