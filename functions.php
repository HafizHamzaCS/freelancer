<?php
// functions.php - Helper Functions

require_once 'auth.php';

function redirect($url) {
    if (strpos($url, 'http') === 0) {
        header("Location: " . $url);
    } else {
        header("Location: " . APP_URL . "/" . $url);
    }
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function check_auth() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    
    // Global CSRF Protection for all authenticated POST requests
    verify_csrf_token();
    
    check_lockout();
    check_session_timeout();
    check_daily_developer_alert();
}

function check_daily_developer_alert() {
    global $conn;
    
    if (!is_logged_in()) return;
    
    $user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'];
    $user_name = $_SESSION['user_name'];

    // Only for members/developers
    if (!in_array($role, ['member', 'developer'])) {
        return;
    }

    // Check if notification already exists for today (last 24h)
    $title = "Daily Check-in";
    $title_esc = escape($title);
    
    $check = db_fetch_one("SELECT id FROM notifications 
                           WHERE user_id = $user_id 
                           AND title = '$title_esc' 
                           AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    
    if (!$check) {
        // AI-Enhanced Message
        $message = "Hi $user_name, what have you done today? Please update your task progress.";
        
        if (get_setting('ai_enabled')) {
            $recent_tasks = db_fetch_all("SELECT title FROM tasks WHERE assigned_to = $user_id AND status != 'Done' LIMIT 3");
            $task_list = implode(', ', array_column($recent_tasks, 'title'));
            $ai_prompt = "Generate a short, friendly daily check-in message for developer $user_name. They are working on tasks: [$task_list]. Ask for progress naturally.";
            $ai_result = AI_Service::call($ai_prompt, 'AutomatedCheckIn');
            if ($ai_result['success']) {
                $message = $ai_result['content'];
            }
        }

        $message_esc = escape($message);
        $link = APP_URL . "/tasks/index.php";
        $link_esc = escape($link);
        
        db_query("INSERT INTO notifications (user_id, title, message, link) 
                  VALUES ($user_id, '$title_esc', '$message_esc', '$link_esc')");
    }
}

function format_money($amount) {
    return '$' . number_format($amount, 2);
}

function get_user_name() {
    return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
}

function db_query($sql) {
    global $conn;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Query Failed: " . mysqli_error($conn));
    }
    return $result;
}

function db_fetch_all($sql) {
    $result = db_query($sql);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function db_fetch_one($sql) {
    $result = db_query($sql);
    return mysqli_fetch_assoc($result);
}

function escape($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

function get_setting($key, $default = '') {
    global $conn;
    $key = escape($key);
    $result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key'");
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

function send_email($to, $subject, $body, $client_id = 0) {
    // In a real app with Composer, we'd use PHPMailer here.
    // For "dead simple" PHP, we use mail() or just log it if no SMTP.
    // We will simulate success and log it.
    
    global $conn;
    
    // Simulate SMTP delay
    // sleep(1); 
    
    // Log it
    $subject_esc = escape($subject);
    $body_esc = escape($body);
    $client_id = (int)$client_id;
    
    mysqli_query($conn, "INSERT INTO email_logs (client_id, subject, body) VALUES ($client_id, '$subject_esc', '$body_esc')");
    
    // Update last contact
    if ($client_id > 0) {
        mysqli_query($conn, "UPDATE clients SET last_contacted = CURDATE() WHERE id = $client_id");
    }
    
    return true;
}

class AI_Service {
    private static $context_path = __DIR__ . '/ai/ai_context.toon';
    private static $memory_path = __DIR__ . '/ai/ai_memory.toon';

    public static function call($prompt, $action_type = 'General', $force_premium = false) {
        $enabled = get_setting('ai_enabled', '0');
        $api_key = get_setting('ai_api_key');
        if (!$enabled || !$api_key) return ["success" => false, "error" => "AI not enabled or configured."];

        $model = $force_premium ? 'gpt-4o' : get_setting('ai_model', 'gpt-4o-mini');
        $context = file_exists(self::$context_path) ? file_get_contents(self::$context_path) : "";
        $memory = file_exists(self::$memory_path) ? file_get_contents(self::$memory_path) : "";

        $full_prompt = "--- APPLICATION CONTEXT (TOON) ---\n$context\n\n--- RECURSIVE MEMORY ---\n$memory\n\n--- USER COMMAND ---\n$prompt";

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $full_prompt]],
            'temperature' => 0.7
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) return ["success" => false, "error" => curl_error($ch)];
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['error'])) return ["success" => false, "error" => $result['error']['message']];

        // Log usage
        self::log_usage($result, $model, $action_type);

        return [
            "success" => true,
            "content" => $result['choices'][0]['message']['content'],
            "usage" => $result['usage']
        ];
    }

    private static function log_usage($result, $model, $action_type) {
        global $conn;
        $user_id = (int)($_SESSION['user_id'] ?? 0);
        $prompt_tokens = (int)$result['usage']['prompt_tokens'];
        $completion_tokens = (int)$result['usage']['completion_tokens'];
        $total_tokens = (int)$result['usage']['total_tokens'];
        $model_esc = escape($model);
        $action_esc = escape($action_type);

        db_query("INSERT INTO ai_usage (user_id, model, prompt_tokens, completion_tokens, total_tokens, action_type) 
                  VALUES ($user_id, '$model_esc', $prompt_tokens, $completion_tokens, $total_tokens, '$action_esc')");
    }

    public static function learn($context, $error, $resolution) {
        $memory = file_get_contents(self::$memory_path);
        $entry = "\n# " . date('Y-m-d') . " | $context | $error | $resolution";
        file_put_contents(self::$memory_path, $memory . $entry);
    }
}

function ai_suggest($prompt) {
    $result = AI_Service::call($prompt, 'Suggestion');
    return $result['success'] ? $result['content'] : $result['error'];
}
function generate_slug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function get_project_url($id_or_row) {
    if (is_array($id_or_row)) {
        $id = $id_or_row['id'];
        $slug = isset($id_or_row['slug']) ? $id_or_row['slug'] : '';
    } else {
        $id = (int)$id_or_row;
        $row = db_fetch_one("SELECT slug FROM projects WHERE id = $id");
        $slug = $row ? $row['slug'] : '';
    }
    
    // Force parameter-based URLs to ensure compatibility
    // if ($slug && get_setting('enable_pretty_urls') == '1') {
    //    return APP_URL . "/project/" . $slug;
    // }
    return APP_URL . "/projects/project_view.php?id=" . $id;
}

function get_client_url($id_or_row) {
    if (is_array($id_or_row)) {
        $id = $id_or_row['id'];
        $slug = isset($id_or_row['slug']) ? $id_or_row['slug'] : '';
    } else {
        $id = (int)$id_or_row;
        $row = db_fetch_one("SELECT slug FROM clients WHERE id = $id");
        $slug = $row ? $row['slug'] : '';
    }
    
    // Force parameter-based URLs to ensure compatibility
    // if ($slug && get_setting('enable_pretty_urls') == '1') {
    //    return APP_URL . "/client/" . $slug;
    // }
    return APP_URL . "/clients/client_view.php?id=" . $id;
}


// --- Tax & Source Helpers ---

function get_tax_rate($source) {
    $source = strtolower($source);
    if ($source === 'fiverr') return 0.22;
    if ($source === 'upwork') return 0.12;
    return 0.00;
}

function calculate_project_net($amount, $source) {
    $tax_rate = get_tax_rate($source);
    return $amount * (1 - $tax_rate);
}

function get_kanban_source_badge($source) {
    return match(strtolower($source)) {
        'fiverr' => 'badge-success',
        'upwork' => 'badge-primary',
        'freelancer' => 'badge-warning',
        'linkedin' => 'badge-info',
        'direct' => 'badge-ghost',
        default => 'badge-ghost'
    };
}

// --- Workflow Automation Helper ---
function trigger_workflow($event, $data) {
    global $conn;
    $event = escape($event);
    
    // Find active workflows for this event
    $sql = "SELECT * FROM workflows WHERE trigger_event = '$event' AND is_active = 1";
    $workflows = db_fetch_all($sql);
    
    foreach ($workflows as $wf) {
        $action = $wf['action_type'];
        $payload = $wf['action_payload'];
        
        // 1. Send Notification
        if ($action === 'notification') {
            // Determine recipient (Default to assigned user if task, or admin)
            $user_id = isset($data['assigned_to']) ? $data['assigned_to'] : 1;
            
            // Replace placeholders (simple implementation)
            $message = str_replace(
                ['{task_title}', '{project_id}'], 
                [$data['title'] ?? 'Task', $data['project_id'] ?? 0], 
                $payload
            );
            
            $title = "Automation: " . $wf['name'];
            $title_esc = escape($title);
            $message_esc = escape($message);
            $link_esc = isset($data['link']) ? escape($data['link']) : '#';
            
            db_query("INSERT INTO notifications (user_id, title, message, link) VALUES ($user_id, '$title_esc', '$message_esc', '$link_esc')");
        }
        
        // 2. Send Email (Mock)
        if ($action === 'email') {
            $parts = explode('|', $payload);
            $subject = trim($parts[0] ?? 'Notification');
            $body = trim($parts[1] ?? 'Automated email');
            
            // In real app, fetch user email. Here we just log/mock.
            send_email('user@example.com', $subject, $body . " (Context: " . json_encode($data) . ")");
        }
    }
}
// --- Flash Message Helper ---
function set_flash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function display_flash() {
    if (isset($_SESSION['flash']) && !empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $msg) {
            $class = match($msg['type']) {
                'success' => 'alert-success',
                'error' => 'alert-error',
                'warning' => 'alert-warning',
                'info' => 'alert-info',
                default => 'alert-info'
            };
            echo '<div class="alert ' . $class . ' mb-4 shadow-lg">';
            echo '<span>' . htmlspecialchars($msg['message']) . '</span>';
            echo '</div>';
        }
        // Clear flash messages
        unset($_SESSION['flash']);
    }
}
// --- System Logging ---
function log_system_activity($action, $description = '') {
    global $conn;
    if (!isset($_SESSION['user_id'])) return;
    
    $user_id = (int)$_SESSION['user_id'];
    $action = escape($action);
    $desc = escape($description);
    $ip = escape($_SERVER['REMOTE_ADDR']);
    
    // Check if table exists first avoiding errors if migration didn't run
    // For simplicity, we assume schema is updated.
    mysqli_query($conn, "INSERT INTO system_activity (user_id, action_type, description, ip_address) VALUES ($user_id, '$action', '$desc', '$ip')");
}

// --- Security Helpers ---

/**
 * Escape output for XSS protection
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token
 */
function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                       (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'CSRF token validation failed.']);
                exit;
            } else {
                die("CSRF token validation failed. Please refresh the page and try again.");
            }
        }
    }
}

/**
 * Output a hidden CSRF input field
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

/**
 * Secure file upload validator
 */
function validate_upload($file, $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip', 'txt']) {
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check extension
    if (!in_array($ext, $allowed_exts)) {
        return false;
    }
    
    // Check MIME type for extra safety if possible
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $dangerous_mimes = ['text/x-php', 'application/x-php', 'text/html', 'application/x-httpd-php'];
    if (in_array($mime, $dangerous_mimes)) {
        return false;
    }
    
    return true;
}
?>
