<?php
require_once 'config.php';

// Connect using native mysqli to ensure we catch errors directly
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "<h1>Database Updater</h1>";

// 1. Add description column to projects table
$sql = "ALTER TABLE projects ADD COLUMN description TEXT AFTER name";

if (mysqli_query($conn, $sql)) {
    echo "<div style='color: green; font-weight: bold;'>Success: 'description' column added to 'projects' table.</div>";
} else {
    $error = mysqli_error($conn);
    if (strpos($error, "Duplicate column name") !== false) {
        echo "<div style='color: orange;'>Notice: Column 'description' already exists.</div>";
    } else {
        echo "<div style='color: red;'>Error (description): " . $error . "</div>";
    }
}

// 2. Create project_members table
$sql_members = "CREATE TABLE IF NOT EXISTS `project_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'member',
  `assigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $sql_members)) {
     echo "<div style='color: green; font-weight: bold;'>Success: 'project_members' table created/verified.</div>";
} else {
     echo "<div style='color: red;'>Error (project_members): " . mysqli_error($conn) . "</div>";
}



echo "<br><a href='projects/project_list.php'>Go Back to Projects</a>";
?>
