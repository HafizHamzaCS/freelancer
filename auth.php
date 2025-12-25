<?php
require_once 'config.php';

function login($email, $password) {
    global $conn;
    
    $email = escape($email);
    
    // 1. Check Users (Admin/Team)
    $user_sql = "SELECT * FROM users WHERE email = '$email'";
    $user = db_fetch_one($user_sql);
    
    if ($user && password_verify($password, $user['password'])) {
        // Generate Login Token for Single-Device Lock (ONLY for Admin)
        $token = null;
        $user_id = $user['id'];
        
        if ($user['role'] === 'admin') {
            $token = bin2hex(random_bytes(32));
            // Update Token in DB
            mysqli_query($conn, "UPDATE users SET login_token = '$token' WHERE id = $user_id");
        }
        
        // Set Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role']; // admin, member, or client
        $_SESSION['login_token'] = $token;
        $_SESSION['is_client'] = ($user['role'] === 'client');
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_type'] = 'user';
        $_SESSION['last_activity'] = time();
        
        log_system_activity('Login', "User logged in: {$user['name']}");
        
        return true;
    }
    
    // 2. Check Clients
    $client_sql = "SELECT * FROM clients WHERE email = '$email'";
    $client = db_fetch_one($client_sql);
    
    if ($client && $client['password'] && password_verify($password, $client['password'])) {
        // Clients don't strictly need single-device lock, but we can add it if needed.
        // For now, simple login.
        
        $_SESSION['user_id'] = $client['id'];
        $_SESSION['user_name'] = $client['name'];
        $_SESSION['role'] = 'client';
        $_SESSION['is_client'] = true;
        $_SESSION['user_email'] = $client['email'];
        $_SESSION['user_type'] = 'client';
        
        $_SESSION['last_activity'] = time(); // Initialize last_activity for clients too
        
        log_system_activity('Login', "Client logged in: {$client['name']}");
        
        return true;
    }
    
    log_system_activity('Login Failed', "Attempted login for email: $email");
    return false;
}



function check_lockout() {
    global $conn;
    
    // Only check single-session lockout for admin role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        return; 
    }
    
    $user_id = $_SESSION['user_id'];
    $current_token = $_SESSION['login_token'] ?? '';
    
    $user = db_fetch_one("SELECT login_token FROM users WHERE id = $user_id");
    
    if ($user && $user['login_token'] !== $current_token) {
        // Token mismatch = logged in elsewhere
        session_destroy();
        header("Location: " . APP_URL . "/login.php?error=lockout");
        exit;
    }
}

function check_session_timeout() {
    if (isset($_SESSION['last_activity']) && isset($_SESSION['user_id'])) {
        $timeout_minutes = (int)get_setting('session_timeout', '30');
        if ($timeout_minutes <= 0) $timeout_minutes = 30; // Default
        
        $idle_time = time() - $_SESSION['last_activity'];
        
        if ($idle_time > ($timeout_minutes * 60)) {
            // Expired
            log_system_activity('Logout', "Session timed out after {$idle_time}s");
            session_destroy();
            header("Location: " . APP_URL . "/login.php?error=timeout");
            exit;
        }
    }
    $_SESSION['last_activity'] = time(); // Update activity
}

function current_user() {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['role'],
        'is_client' => $_SESSION['is_client'] ?? false
    ];
}

function has_permission($action) {
    $user = current_user();
    if (!$user) return false;
    
    $role = $user['role']; // admin, manager, developer, member, client, viewer
    
    if ($role === 'admin' || $role === 'manager') return true; // Full access
    
    switch ($action) {
        case 'view_project':
            return true; // Everyone can view
        case 'edit_project':
        case 'delete_project':
            return false; // Devs/Viewers cannot Edit Project Settings
        case 'create_task':
        case 'edit_task':
            return $role === 'developer' || $role === 'member';
        case 'delete_task':
            return $role === 'developer' || $role === 'member'; // Devs can delete tasks (soft delete)
        case 'view_finance':
            return false; // Only admins/managers
        default:
            return false;
    }
}

function require_role($roles) {
    if (!is_array($roles)) $roles = [$roles];
    $user = current_user();
    
    if (!$user || !in_array($user['role'], $roles)) {
        redirect('login.php');
    }
}
?>
