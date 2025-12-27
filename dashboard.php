<?php
/**
 * FIXED DASHBOARD WITH COMPREHENSIVE ERROR HANDLING
 * Place this code at the TOP of your dashboard.php file
 */

// ============================================
// STEP 1: ERROR HANDLER (Must be first!)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/dashboard-errors.log');

// Custom error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_msg = "Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_msg);
    
    if (defined('WP_DEBUG') || (isset($_GET['debug']) && $_GET['debug'] === '1')) {
        echo "<div style='background:#fee;border:2px solid #c33;padding:20px;margin:20px;border-radius:8px;font-family:monospace;'>";
        echo "<h3 style='color:#c33;margin:0 0 10px 0;'>⚠️ Error Detected</h3>";
        echo "<p><strong>Type:</strong> $errno</p>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($errstr) . "</p>";
        echo "<p><strong>File:</strong> $errfile</p>";
        echo "<p><strong>Line:</strong> $errline</p>";
        echo "</div>";
    }
    return true;
});

// ============================================
// STEP 2: SAFE REQUIRE WITH VALIDATION
// ============================================
function safe_require($file, $required = true) {
    $filepath = __DIR__ . '/' . $file;
    
    if (!file_exists($filepath)) {
        $msg = "Required file not found: $file (looked in: $filepath)";
        error_log($msg);
        
        if ($required) {
            die("<div style='background:#fee;padding:20px;margin:20px;border:2px solid #c33;border-radius:8px;'>
                <h2 style='color:#c33;'>Configuration Error</h2>
                <p><strong>Missing file:</strong> $file</p>
                <p><strong>Expected location:</strong> $filepath</p>
                <p><strong>Solution:</strong> Ensure $file exists in the same directory as dashboard.php</p>
                </div>");
        }
        return false;
    }
    
    require_once $filepath;
    return true;
}

// Load header (this should start session)
safe_require('header.php', true);

// ============================================
// STEP 3: SESSION MANAGEMENT
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate session data
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log("Dashboard accessed without valid session");
    header('Location: login.php');
    exit;
}

// ============================================
// STEP 4: HELPER FUNCTIONS WITH VALIDATION
// ============================================

// Safe HTML escape
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Safe redirect function
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// Validate database functions exist
if (!function_exists('db_fetch_all')) {
    die("<div style='background:#fee;padding:20px;margin:20px;border:2px solid #c33;border-radius:8px;'>
        <h2 style='color:#c33;'>Database Error</h2>
        <p><strong>Missing function:</strong> db_fetch_all()</p>
        <p><strong>Solution:</strong> Ensure your database configuration file (db.php or config.php) is loaded in header.php</p>
        </div>");
}

if (!function_exists('db_fetch_one')) {
    die("<div style='background:#fee;padding:20px;margin:20px;border:2px solid #c33;border-radius:8px;'>
        <h2 style='color:#c33;'>Database Error</h2>
        <p><strong>Missing function:</strong> db_fetch_one()</p>
        <p><strong>Solution:</strong> Ensure your database configuration file is loaded in header.php</p>
        </div>");
}

if (!function_exists('get_user_name')) {
    function get_user_name() {
        return $_SESSION['username'] ?? $_SESSION['name'] ?? 'User';
    }
}

// ============================================
// STEP 5: DATABASE QUERY WRAPPER
// ============================================
function safe_db_fetch_all($sql, $default = []) {
    try {
        if (empty($sql)) {
            error_log("Empty SQL query provided");
            return $default;
        }
        
        $result = db_fetch_all($sql);
        return is_array($result) ? $result : $default;
    } catch (Exception $e) {
        error_log("DB Query Error: " . $e->getMessage() . " | SQL: $sql");
        return $default;
    }
}

function safe_db_fetch_one($sql, $default = null) {
    try {
        if (empty($sql)) {
            error_log("Empty SQL query provided");
            return $default;
        }
        
        $result = db_fetch_one($sql);
        return $result ?: $default;
    } catch (Exception $e) {
        error_log("DB Query Error: " . $e->getMessage() . " | SQL: $sql");
        return $default;
    }
}

// ============================================
// STEP 6: MIGRATION TRIGGER (Optional)
// ============================================
if (isset($_GET['migrate'])) {
    if (safe_require('update_db_security.php', false)) {
        exit;
    } else {
        echo "<div style='background:#ffe;padding:20px;margin:20px;border:2px solid #fa0;'>Migration file not found.</div>";
    }
}

// ============================================
// STEP 7: ACCESS CONTROL
// ============================================
if (!empty($_SESSION['is_client'])) {
    redirect('projects/project_list.php');
}

// ============================================
// STEP 8: USER CONTEXT & ROLE
// ============================================
$role = $_SESSION['role'] ?? 'guest';
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Validate user ID
if ($uid === 0) {
    error_log("Invalid user ID in session");
    redirect('login.php');
}

// User context filter for SQL
$user_context_filter = "1=1";
if (!in_array($role, ['admin', 'manager'])) {
    $user_context_filter = "EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = {$uid})";
}

// ============================================
// STEP 9: FETCH DASHBOARD DATA SAFELY
// ============================================

// Projects Queue
$queue_sql = "SELECT p.*, c.name as client_name 
              FROM projects p 
              JOIN clients c ON p.client_id = c.id 
              WHERE p.status = 'Pending' 
              AND {$user_context_filter} 
              ORDER BY p.created_at ASC";
$queue_projects = safe_db_fetch_all($queue_sql);

// Active Projects Count
$projects_sql = "SELECT COUNT(*) as total 
                 FROM projects p 
                 WHERE p.status = 'In Progress' 
                 AND {$user_context_filter}";
$row = safe_db_fetch_one($projects_sql);
$active_projects = $row ? (int)$row['total'] : 0;

// Overdue Projects Count
$overdue_sql = "SELECT COUNT(*) as total 
                FROM projects p 
                WHERE p.deadline < CURDATE() 
                AND p.status != 'Completed' 
                AND {$user_context_filter}";
$row = safe_db_fetch_one($overdue_sql);
$overdue_projects = $row ? (int)$row['total'] : 0;

// Follow Up Clients (Random 4 Active)
$clients_sql = "SELECT * FROM clients WHERE status = 'Active' ORDER BY RAND() LIMIT 4";
$follow_up_clients = safe_db_fetch_all($clients_sql);

// Upcoming Promotion
$promo_sql = "SELECT * FROM promotions WHERE status != 'Sent' ORDER BY scheduled_at ASC LIMIT 1";
$next_promo = safe_db_fetch_one($promo_sql);

// Projects in Queue Count
$queue_count = count($queue_projects);

// Total Clients
$clients_count_sql = "SELECT COUNT(*) as total FROM clients WHERE status = 'Active'";
$row = safe_db_fetch_one($clients_count_sql);
$total_clients = $row ? (int)$row['total'] : 0;

// Completed Projects
$completed_sql = "SELECT COUNT(*) as total 
                  FROM projects p 
                  WHERE p.status = 'Completed' 
                  AND {$user_context_filter}";
$row = safe_db_fetch_one($completed_sql);
$completed_projects = $row ? (int)$row['total'] : 0;

// Upcoming Events (Milestones)
$events_sql = "SELECT m.*, p.name as project_name 
               FROM milestones m 
               JOIN projects p ON m.project_id = p.id 
               WHERE m.status != 'Completed' 
               AND m.due_date >= CURDATE() 
               AND {$user_context_filter} 
               ORDER BY m.due_date ASC 
               LIMIT 5";
$upcoming_events = safe_db_fetch_all($events_sql);

// Delayed Projects Details
$overdue_details_sql = "SELECT p.*, c.name as client_name 
                        FROM projects p 
                        JOIN clients c ON p.client_id = c.id 
                        WHERE p.deadline < CURDATE() 
                        AND p.status != 'Completed' 
                        AND {$user_context_filter} 
                        ORDER BY p.deadline ASC";
$overdue_details = safe_db_fetch_all($overdue_details_sql);

// My Upcoming Tasks (For Members)
$my_tasks = [];
if ($role === 'member') {
    $my_tasks_sql = "SELECT t.*, p.name as project_name 
                     FROM tasks t 
                     JOIN projects p ON t.project_id = p.id 
                     WHERE t.assigned_to = {$uid} 
                     AND t.status != 'Done' 
                     AND t.deleted_at IS NULL 
                     AND t.due_date >= CURDATE()
                     ORDER BY t.due_date ASC 
                     LIMIT 10";
    $my_tasks = safe_db_fetch_all($my_tasks_sql);
}

// Chart Data - Project Status
$status_sql = "SELECT p.status, COUNT(*) as count 
               FROM projects p 
               WHERE {$user_context_filter} 
               GROUP BY p.status";
$status_res = safe_db_fetch_all($status_sql);
$status_labels = [];
$status_counts = [];
foreach ($status_res as $row) {
    $status_labels[] = $row['status'];
    $status_counts[] = (int)$row['count'];
}

// Urgent Clients (No contact in 30+ days)
$urgent_sql = "SELECT * FROM clients 
               WHERE status = 'Active' 
               AND (last_contacted IS NULL OR last_contacted < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) 
               LIMIT 5";
$urgent_clients = safe_db_fetch_all($urgent_sql);

// ============================================
// STEP 10: RENDER DASHBOARD HTML
// ============================================
?>

<!-- Hero Section -->
<div class="mb-10">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Dashboard</h1>
            <p class="text-base-content/70">Welcome back, <?php echo e(get_user_name()); ?>!</p>
        </div>
    </div>

    <!-- Hero Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <!-- Delayed Projects Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-red-100 rounded-xl group-hover:bg-red-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Delayed Projects</p>
                    <h3 class="text-2xl font-black text-red-500 mt-1"><?php echo $overdue_projects; ?></h3>
                </div>
            </div>
        </div>

        <!-- Active Projects Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-purple-100 rounded-xl group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Active Projects</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $active_projects; ?></h3>
                </div>
            </div>
        </div>

        <!-- Completed Projects Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-orange-100 rounded-xl group-hover:bg-orange-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Completed Projects</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $completed_projects; ?></h3>
                </div>
            </div>
        </div>

        <!-- Total Clients Card (Hidden for Members) -->
        <?php if (in_array($role, ['admin', 'manager'])): ?>
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-emerald-100 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Total Clients</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $total_clients; ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Events Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-rose-100 rounded-xl group-hover:bg-rose-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Upcoming Events</p>
                    <?php if (empty($upcoming_events)): ?>
                        <div class="text-xs text-base-content/50 mt-1">No upcoming events</div>
                    <?php else: ?>
                        <div class="space-y-2 mt-2">
                        <?php foreach (array_slice($upcoming_events, 0, 3) as $event): ?>
                            <div class="flex justify-between items-center text-xs">
                                <span class="truncate font-bold max-w-[120px]" title="<?php echo e($event['title']); ?>">
                                    <?php echo e($event['title']); ?>
                                </span>
                                <span class="opacity-70"><?php echo date('M d', strtotime($event['due_date'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Projects Queue Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-blue-100 rounded-xl group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Projects in Queue</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $queue_count; ?></h3>
                </div>
            </div>
        </div>

    </div>

    <!-- REST OF YOUR HTML CODE CONTINUES UNCHANGED -->
    <!-- Copy the rest of your original HTML from line 128 onwards -->

<?php
// At the very end, load footer
safe_require('footer.php', true);
?> 
