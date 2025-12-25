<?php
require_once 'config.php';

// disable buffering
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

echo "<h1>Updating Database for Advanced Features...</h1>";
echo "<pre>";

// 1. Comments Table
$sql = "CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NULL,
    task_id INT NULL,
    user_id INT NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (project_id),
    INDEX (task_id)
)";
if (mysqli_query($conn, $sql)) {
    echo "✔ Comments table checked/created.\n";
} else {
    echo "❌ Error creating comments table: " . mysqli_error($conn) . "\n";
}

// 2. Activity Log Table
$sql = "CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50),
    resource_type VARCHAR(50), 
    resource_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (mysqli_query($conn, $sql)) {
    echo "✔ Activity Log table checked/created.\n";
} else {
    echo "❌ Error creating activity_log table: " . mysqli_error($conn) . "\n";
}

// 3. Workflows Table
$sql = "CREATE TABLE IF NOT EXISTS workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    trigger_event VARCHAR(50),
    condition_logic TEXT,
    action_type VARCHAR(50),
    action_payload TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (mysqli_query($conn, $sql)) {
    echo "✔ Workflows table checked/created.\n";
} else {
    echo "❌ Error creating workflows table: " . mysqli_error($conn) . "\n";
}

// 4. Notifications Table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100),
    message TEXT,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
)";
if (mysqli_query($conn, $sql)) {
    echo "✔ Notifications table checked/created.\n";
} else {
    echo "❌ Error creating notifications table: " . mysqli_error($conn) . "\n";
}

// 5. Update Project Files (Add description/uploader if missing)
$columns = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM project_files");
while($row = mysqli_fetch_assoc($res)) {
    $columns[] = $row['Field'];
}

if (!in_array('uploaded_by', $columns)) {
    mysqli_query($conn, "ALTER TABLE project_files ADD COLUMN uploaded_by INT NULL");
    echo "✔ Added 'uploaded_by' to project_files.\n";
}

if (!in_array('description', $columns)) {
    mysqli_query($conn, "ALTER TABLE project_files ADD COLUMN description TEXT NULL");
    echo "✔ Added 'description' to project_files.\n";
}

echo "\nDone! Database updated.";
echo "</pre>";
echo "<div style='margin-top: 20px;'><a href='index.php'>Go Back to Dashboard</a></div>";
?>
