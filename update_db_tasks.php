<?php
require_once 'config.php';

echo "<h1>Updating Tasks Table...</h1>";

// 1. Add 'description'
$cols = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM tasks");
while ($row = mysqli_fetch_assoc($res)) {
    $cols[] = $row['Field'];
}

if (!in_array('description', $cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN description TEXT NULL");
    echo "Added 'description' column.<br>";
}

// 2. Add 'priority'
if (!in_array('priority', $cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium'");
    echo "Added 'priority' column.<br>";
}

// 3. Add 'assigned_to'
if (!in_array('assigned_to', $cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN assigned_to INT NULL");
    echo "Added 'assigned_to' column.<br>";
}

// 4. Add 'created_by'
if (!in_array('created_by', $cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN created_by INT NULL");
    echo "Added 'created_by' column.<br>";
}

// 5. Update 'status' enum/varchar
// Since it's likely a VARCHAR(20) from config.php, we don't strictly need to change the type if we just store strings.
// But we want to ensure it's long enough or change to ENUM if strict. 
// Existing: status VARCHAR(20) DEFAULT 'Todo'
// We will just leave it as VARCHAR to accept 'In Progress', 'On Hold', 'Blocked', 'Done'.
// 'In Progress' is 11 chars. 'Blocked' is 7. All fit in 20. 
// Let's modify it to 50 just in case.
mysqli_query($conn, "ALTER TABLE tasks MODIFY COLUMN status VARCHAR(50) DEFAULT 'Todo'");
echo "Updated 'status' column length.<br>";


// 6. Add 'dependencies'
if (!in_array('dependencies', $cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN dependencies TEXT NULL COMMENT 'Comma separated task IDs'");
    echo "Added 'dependencies' column.<br>";
}

echo "<h3>Update Complete!</h3>";
echo "<a href='index.php'>Go to Home</a>";
?>
