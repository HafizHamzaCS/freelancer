<?php
// config.php - Database & Settings

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'u399471847_freelance');
define('DB_PASS', 'hZ??^F0&C');
define('DB_NAME', 'u399471847_freelance_empi');

// App Settings
define('APP_URL', 'https://freelancer.cyprusautobazaar.com');
define('APP_NAME', 'Freelance Empire');

// Connect to Database
// Connect to Database
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
} catch (Exception $e) {
    die("<h1>Database Connection Error</h1><p>" . $e->getMessage() . "</p><p>Please check your config.php settings.</p>");
}

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create DB if not exists
$db_check = mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS " . DB_NAME);
if (!$db_check) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select Database
mysqli_select_db($conn, DB_NAME);

// Create Tables (Auto-setup)
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        role VARCHAR(20) DEFAULT 'admin',
        login_token VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "clients" => "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        slug VARCHAR(255),
        email VARCHAR(100),
        password VARCHAR(255),
        login_token VARCHAR(64),
        phone VARCHAR(50),
        status VARCHAR(20) DEFAULT 'Active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "projects" => "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT,
        name VARCHAR(200),
        slug VARCHAR(255),
        status VARCHAR(50) DEFAULT 'In Progress',
        source VARCHAR(50) DEFAULT 'Direct',
        start_date DATE,
        deadline DATE,
        budget DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "promotions" => "CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200),
        content TEXT,
        status VARCHAR(20) DEFAULT 'Draft',
        scheduled_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "tasks" => "CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        title VARCHAR(200),
        description TEXT,
        assigned_to INT,
        priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
        status VARCHAR(20) DEFAULT 'Todo',
        due_date DATE,
        parent_id INT DEFAULT NULL,
        dependencies TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "task_comments" => "CREATE TABLE IF NOT EXISTS task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT,
        user_id INT,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "time_entries" => "CREATE TABLE IF NOT EXISTS time_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        start_time DATETIME,
        end_time DATETIME NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "settings" => "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "email_logs" => "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT,
        subject VARCHAR(200),
        body TEXT,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "milestones" => "CREATE TABLE IF NOT EXISTS milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        title VARCHAR(200),
        status VARCHAR(20) DEFAULT 'Pending',
        due_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "client_stats" => "CREATE TABLE IF NOT EXISTS client_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT UNIQUE,
        total_orders INT DEFAULT 0,
        avg_price DECIMAL(10,2) DEFAULT 0,
        avg_rating DECIMAL(3,2) DEFAULT 0,
        last_order_date DATE,
        preferred_service VARCHAR(100),
        platform_orders INT DEFAULT 0,
        repeat_rate DECIMAL(5,2) DEFAULT 0,
        platform_last_order DATE,
        pro_score INT DEFAULT 0,
        badges TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "project_files" => "CREATE TABLE IF NOT EXISTS project_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        filename VARCHAR(255),
        filepath VARCHAR(255),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "task_files" => "CREATE TABLE IF NOT EXISTS task_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT,
        user_id INT,
        file_path VARCHAR(255),
        original_name VARCHAR(255),
        file_size INT,
        file_type VARCHAR(50),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "messages" => "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        user_id INT,
        sender_type VARCHAR(20) DEFAULT 'admin', -- 'admin' or 'client'
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
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
    )",
    "project_members" => "CREATE TABLE IF NOT EXISTS project_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        user_id INT,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "events" => "CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "workflows" => "CREATE TABLE IF NOT EXISTS workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trigger_event VARCHAR(100),
        action_type VARCHAR(50),
        action_payload TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "notifications" => "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(200),
        message TEXT,
        link VARCHAR(255),
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $name => $sql) {
    if (!mysqli_query($conn, $sql)) {
        die("Error creating table $name: " . mysqli_error($conn));
    }
}

// Add new columns to clients table if they don't exist
$client_cols = mysqli_query($conn, "SHOW COLUMNS FROM clients");
$cols = [];
while ($row = mysqli_fetch_assoc($client_cols)) {
    $cols[] = $row['Field'];
}

if (!in_array('last_contacted', $cols)) {
    mysqli_query($conn, "ALTER TABLE clients ADD COLUMN last_contacted DATE NULL");
}
if (!in_array('slug', $cols)) {
    mysqli_query($conn, "ALTER TABLE clients ADD COLUMN slug VARCHAR(255)");
}

// Add new columns to projects table if they don't exist
$project_cols = mysqli_query($conn, "SHOW COLUMNS FROM projects");
$p_cols = [];
while ($row = mysqli_fetch_assoc($project_cols)) {
    $p_cols[] = $row['Field'];
}

if (!in_array('slug', $p_cols)) {
    mysqli_query($conn, "ALTER TABLE projects ADD COLUMN slug VARCHAR(255)");
}

// Create Default User if not exists
$user_check = mysqli_query($conn, "SELECT * FROM users LIMIT 1");
if (mysqli_num_rows($user_check) == 0) {
    $password = password_hash("password", PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@example.com', '$password', 'admin')");
}

// Auth Migration: Add columns to users if missing
$user_cols = mysqli_query($conn, "SHOW COLUMNS FROM users");
$u_cols = [];
while ($row = mysqli_fetch_assoc($user_cols)) {
    $u_cols[] = $row['Field'];
}
if (!in_array('role', $u_cols)) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'admin'");
}
if (!in_array('login_token', $u_cols)) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN login_token VARCHAR(64)");
}

// Auth Migration: Add columns to clients if missing
if (!in_array('password', $cols)) { // $cols is clients columns from above
    mysqli_query($conn, "ALTER TABLE clients ADD COLUMN password VARCHAR(255)");
}
if (!in_array('login_token', $cols)) {
    mysqli_query($conn, "ALTER TABLE clients ADD COLUMN login_token VARCHAR(64)");
}

// Project Migration: Add source column if missing
$proj_cols = mysqli_query($conn, "SHOW COLUMNS FROM projects");
$p_cols = [];
while ($row = mysqli_fetch_assoc($proj_cols)) {
    $p_cols[] = $row['Field'];
}
if (!in_array('source', $p_cols)) {
    mysqli_query($conn, "ALTER TABLE projects ADD COLUMN source VARCHAR(50) DEFAULT 'Direct'");
}
if (!in_array('team_id', $p_cols)) {
    mysqli_query($conn, "ALTER TABLE projects ADD COLUMN team_id INT DEFAULT NULL");
}

// Chat Migration: Add attachment columns to messages if missing
$msg_cols = mysqli_query($conn, "SHOW COLUMNS FROM messages");
$m_cols = [];
while ($row = mysqli_fetch_assoc($msg_cols)) {
    $m_cols[] = $row['Field'];
}
if (!in_array('attachment_path', $m_cols)) {
    mysqli_query($conn, "ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) NULL");
}
if (!in_array('attachment_type', $m_cols)) {
    mysqli_query($conn, "ALTER TABLE messages ADD COLUMN attachment_type VARCHAR(50) NULL");
}

// Task Migration: Add new columns if missing
$task_cols = mysqli_query($conn, "SHOW COLUMNS FROM tasks");
$t_cols = [];
while ($row = mysqli_fetch_assoc($task_cols)) {
    $t_cols[] = $row['Field'];
}

if (!in_array('priority', $t_cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium'");
}
if (!in_array('assigned_to', $t_cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN assigned_to INT");
}
if (!in_array('description', $t_cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN description TEXT");
}
if (!in_array('parent_id', $t_cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN parent_id INT DEFAULT NULL");
}
if (!in_array('dependencies', $t_cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN dependencies TEXT");
}
if (!in_array('created_by', $t_cols)) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN created_by INT");
}

// Task Files Migration: Add missing columns
$tf_cols = mysqli_query($conn, "SHOW COLUMNS FROM task_files");
if ($tf_cols) {
    $tf_columns = [];
    while ($row = mysqli_fetch_assoc($tf_cols)) {
        $tf_columns[] = $row['Field'];
    }
    
    if (!in_array('file_type', $tf_columns)) {
        mysqli_query($conn, "ALTER TABLE task_files ADD COLUMN file_type VARCHAR(50)");
    }
    
    if (!in_array('user_id', $tf_columns)) {
        mysqli_query($conn, "ALTER TABLE task_files ADD COLUMN user_id INT");
    }

    if (!in_array('original_name', $tf_columns)) {
        mysqli_query($conn, "ALTER TABLE task_files ADD COLUMN original_name VARCHAR(255)");
    }
}

// --- Security & Session Hardening ---
// Disable error display to prevent information leakage
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Harden Session Cookies
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// Set Samesite attribute
ini_set('session.cookie_samesite', 'Lax');

// Start Session
session_start();
?>
