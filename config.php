<?php
// config.php - Database & Settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- STEP 1: Session Configuration BEFORE Starting ---
// This MUST be done before session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

ini_set('session.cookie_samesite', 'Lax');

// Start Session ONLY if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- STEP 2: Error Handlers ---
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log_file = __DIR__ . '/error.log';
    $error_msg = date('[Y-m-d H:i:s]') . " Error [$errno]: $errstr in $errfile on line $errline\n";
    error_log($error_msg, 3, $log_file);
    return false; 
});

set_exception_handler(function($e) {
    $log_file = __DIR__ . '/error.log';
    $error_msg = date('[Y-m-d H:i:s]') . " Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    error_log($error_msg, 3, $log_file);
    
    // Show user-friendly error
    if (defined('WP_DEBUG') || isset($_GET['debug'])) {
        die("<div style='background:#fee;border:2px solid #c33;padding:20px;margin:20px;border-radius:8px;'>
            <h2 style='color:#c33;margin:0 0 10px 0;'>Fatal Error</h2>
            <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>
            <p><strong>Line:</strong> " . $e->getLine() . "</p>
            <p><strong>Check:</strong> {$log_file} for details</p>
            </div>");
    } else {
        die("<h1>A fatal error occurred.</h1><p>Please contact the administrator.</p>");
    }
});

// --- STEP 3: Database Credentials ---
define('DB_HOST', 'localhost');
define('DB_USER', 'u399471847_freelance');
define('DB_PASS', 'hZ??^F0&C');
define('DB_NAME', 'u399471847_freelance_empi');

// --- STEP 4: App Settings ---
define('APP_URL', 'https://freelancer.cyprusautobazaar.com');
define('APP_NAME', 'Freelance Empire');

// --- STEP 5: Database Connection ---
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Create database if not exists
    $db_check = mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    if (!$db_check) {
        throw new Exception("Error creating database: " . mysqli_error($conn));
    }
    
    // Select database
    if (!mysqli_select_db($conn, DB_NAME)) {
        throw new Exception("Error selecting database: " . mysqli_error($conn));
    }
    
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("<div style='background:#fee;border:2px solid #c33;padding:20px;margin:20px;border-radius:8px;'>
        <h2 style='color:#c33;'>Database Connection Error</h2>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check your config.php settings.</p>
        </div>");
}

// --- STEP 6: Create Tables ---
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
        last_contacted DATE NULL,
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
        team_id INT DEFAULT NULL,
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
        deleted_at TIMESTAMP NULL,
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
        sender_type VARCHAR(20) DEFAULT 'admin',
        message TEXT,
        attachment_path VARCHAR(255) NULL,
        attachment_type VARCHAR(50) NULL,
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

// Create tables with error handling
foreach ($tables as $name => $sql) {
    if (!mysqli_query($conn, $sql)) {
        error_log("Error creating table $name: " . mysqli_error($conn));
    }
}

// --- STEP 7: Column Migrations (Safe) ---
function add_column_if_not_exists($table, $column, $definition) {
    global $conn;
    
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if (mysqli_num_rows($check) == 0) {
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        if (!mysqli_query($conn, $sql)) {
            error_log("Error adding column {$column} to {$table}: " . mysqli_error($conn));
        }
    }
}

// Add missing columns safely
add_column_if_not_exists('clients', 'last_contacted', 'DATE NULL');
add_column_if_not_exists('clients', 'slug', 'VARCHAR(255)');
add_column_if_not_exists('clients', 'password', 'VARCHAR(255)');
add_column_if_not_exists('clients', 'login_token', 'VARCHAR(64)');

add_column_if_not_exists('projects', 'slug', 'VARCHAR(255)');
add_column_if_not_exists('projects', 'source', "VARCHAR(50) DEFAULT 'Direct'");
add_column_if_not_exists('projects', 'team_id', 'INT DEFAULT NULL');

add_column_if_not_exists('users', 'role', "VARCHAR(20) DEFAULT 'admin'");
add_column_if_not_exists('users', 'login_token', 'VARCHAR(64)');

add_column_if_not_exists('messages', 'attachment_path', 'VARCHAR(255) NULL');
add_column_if_not_exists('messages', 'attachment_type', 'VARCHAR(50) NULL');

add_column_if_not_exists('tasks', 'priority', "ENUM('Low', 'Medium', 'High') DEFAULT 'Medium'");
add_column_if_not_exists('tasks', 'assigned_to', 'INT');
add_column_if_not_exists('tasks', 'description', 'TEXT');
add_column_if_not_exists('tasks', 'parent_id', 'INT DEFAULT NULL');
add_column_if_not_exists('tasks', 'dependencies', 'TEXT');
add_column_if_not_exists('tasks', 'created_by', 'INT');
add_column_if_not_exists('tasks', 'deleted_at', 'TIMESTAMP NULL');

add_column_if_not_exists('task_files', 'file_type', 'VARCHAR(50)');
add_column_if_not_exists('task_files', 'user_id', 'INT');
add_column_if_not_exists('task_files', 'original_name', 'VARCHAR(255)');

// --- STEP 8: Create Default Admin User ---
$user_check = mysqli_query($conn, "SELECT * FROM users LIMIT 1");
if (mysqli_num_rows($user_check) == 0) {
    $password = password_hash("password", PASSWORD_DEFAULT);
    $insert_user = "INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@example.com', ?, 'admin')";
    $stmt = mysqli_prepare($conn, $insert_user);
    mysqli_stmt_bind_param($stmt, 's', $password);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Configuration complete
?>
