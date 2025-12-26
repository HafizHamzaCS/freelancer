<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

// Ensure user is admin/manager
if (!in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$data = $input['data'] ?? [];

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'No action provided']);
    exit;
}

try {
    switch ($action) {
        case 'create_task':
            $project_id = (int)($data['project_id'] ?? 0);
            $title = escape($data['title'] ?? 'New Task');
            $assigned_to = (int)($data['assigned_to'] ?? 0);
            $priority = escape($data['priority'] ?? 'Medium');
            
            // If assigned_to is a name (string), try to find ID
            if ($assigned_to == 0 && !empty($data['assigned_to'])) {
                $name = escape($data['assigned_to']);
                $user = db_fetch_one("SELECT id FROM users WHERE name LIKE '%$name%' LIMIT 1");
                if ($user) $assigned_to = $user['id'];
            }

            db_query("INSERT INTO tasks (project_id, title, assigned_to, priority, status) 
                      VALUES ($project_id, '$title', $assigned_to, '$priority', 'Todo')");
            
            log_system_activity('AI Action', "Created task: $title");
            echo json_encode(['success' => true, 'message' => 'Task created successfully!']);
            break;

        case 'notify_user':
            $user_id = (int)($data['user_id'] ?? 0);
            $title = escape($data['title'] ?? 'AI Notification');
            $message = escape($data['message'] ?? '');
            
            if ($user_id == 0 && !empty($data['user_name'])) {
                $name = escape($data['user_name']);
                $user = db_fetch_one("SELECT id FROM users WHERE name LIKE '%$name%' LIMIT 1");
                if ($user) $user_id = $user['id'];
            }

            if ($user_id > 0) {
                db_query("INSERT INTO notifications (user_id, title, message) VALUES ($user_id, '$title', '$message')");
                echo json_encode(['success' => true, 'message' => 'Notification sent!']);
            } else {
                echo json_encode(['success' => false, 'error' => 'User not found for notification.']);
            }
            break;

        case 'learn_rule':
            $rule = escape($data['rule'] ?? '');
            if ($rule) {
                // Persistent storage in .toon file for context efficiency
                $memory_file = AI_Service::$memory_path;
                $current = file_exists($memory_file) ? file_get_contents($memory_file) : "";
                $new_memory = $current . "\n- Rule: " . $rule;
                file_put_contents($memory_file, $new_memory);
                
                // Backup in DB for auditing
                db_query("INSERT INTO ai_learned_rules (rule_content) VALUES ('$rule')");
                
                echo json_encode(['success' => true, 'message' => 'I have learned this new rule and updated my memory!']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No rule content provided.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unsupported AI action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
