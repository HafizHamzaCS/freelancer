<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Security: Verify CSRF Token
verify_csrf_token();

$action = $_POST['action'] ?? '';
$task_id = (int)($_POST['task_id'] ?? 0);

if (!$task_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Task ID']);
    exit;
}

switch ($action) {
    case 'send_chat':
        $message = escape($_POST['message']);
        if (!$message) {
            echo json_encode(['success' => false, 'error' => 'Empty message']);
            exit;
        }
        
        $sql = "INSERT INTO task_chat_messages (task_id, user_id, message) VALUES ($task_id, {$_SESSION['user_id']}, '$message')";
        if (db_query($sql)) {
            // Feature: AI Sentiment Analysis & Blocker Detection
            if (get_setting('ai_enabled') && get_setting('ai_sentiment_enabled', '1')) {
                $user_name = get_user_name();
                $task_title = db_fetch_one("SELECT title FROM tasks WHERE id = $task_id")['title'] ?? 'Task';
                
                $ai_prompt = "Analyze this developer message from $user_name on task '$task_title': \"$message\". 
                             If the message indicates a 'blocker', 'frustration', or 'delay', return a JSON object with 'is_alert': true and 'reason': 'short explanation'. 
                             Otherwise return 'is_alert': false.";
                
                $ai_result = AI_Service::call($ai_prompt, 'SentimentAnalysis');
                if ($ai_result['success']) {
                    $res = json_decode(preg_replace('/```json\n?|\n?```/', '', $ai_result['content']), true);
                    if ($res && !empty($res['is_alert'])) {
                        $reason = escape($res['reason']);
                        $admin = db_fetch_one("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                        if ($admin) {
                            $alert_msg = "🚨 Blocker Detected by AI: $user_name is $reason on task #$task_id ($task_title).";
                            db_query("INSERT INTO notifications (user_id, title, message) VALUES ({$admin['id']}, 'AI Manager Alert', '$alert_msg')");
                        }
                    }
                }
            }
            echo json_encode(['success' => true]);
        }
        break;

    case 'get_chat':
        $last_id = (int)($_POST['last_id'] ?? 0);
        $sql = "SELECT cm.*, u.name as user_name FROM task_chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.task_id = $task_id AND cm.id > $last_id ORDER BY cm.created_at ASC";
        $messages = db_fetch_all($sql);
        
        // Format for JSON
        foreach ($messages as &$msg) {
            $msg['time'] = date('H:i', strtotime($msg['created_at']));
            $msg['is_me'] = ($msg['user_id'] == $_SESSION['user_id']);
            
            $sender_name = $msg['user_name'];
            if (isset($_SESSION['is_client']) && $_SESSION['is_client'] && $msg['user_id'] != $_SESSION['user_id']) {
                $sender_name = 'Team Member';
            }
            $msg['user_name'] = $sender_name;
            $msg['avatar_letter'] = substr($sender_name, 0, 1);
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'update_status':
        $status = escape($_POST['status']);
        $progress = (int)$_POST['progress'];
        
        $sql = "UPDATE tasks SET status = '$status', progress = $progress WHERE id = $task_id";
        if (db_query($sql)) {
            // Log
            $log = "Updated status to $status ($progress%)";
            db_query("INSERT INTO task_activity (task_id, user_id, action_type, description) VALUES ($task_id, {$_SESSION['user_id']}, 'update', '$log')");
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'get_activity':
        $sql = "SELECT ta.*, u.name as user_name FROM task_activity ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = $task_id ORDER BY ta.created_at DESC LIMIT 50";
        $logs = db_fetch_all($sql);
        foreach ($logs as &$log) {
            $log['date_formatted'] = date('M d, H:i', strtotime($log['created_at']));
        }
        echo json_encode(['success' => true, 'prop_logs' => $logs]); 
        break;

    case 'get_files':
        $sql = "SELECT * FROM task_files WHERE task_id = $task_id ORDER BY uploaded_at DESC";
        $files = db_fetch_all($sql);
        $files_formatted = array_map(function($f){ 
            return [
                'id' => $f['id'], 
                'name' => $f['original_name'], 
                'size' => $f['file_size'], 
                'url' => APP_URL . '/' . $f['file_path'],
                'date' => date('M d, Y', strtotime($f['uploaded_at']))
            ]; 
        }, $files);
        echo json_encode(['success' => true, 'files' => $files_formatted]);
        break;

    case 'delete_file':
        $file_id = (int)$_POST['file_id'];
        // Fetch file path first
        $file = db_fetch_one("SELECT * FROM task_files WHERE id = $file_id");
        if ($file) {
            $filepath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            db_query("DELETE FROM task_files WHERE id = $file_id");
            
            // Log activity
            $user_name = get_user_name();
            db_query("INSERT INTO task_activity (task_id, user_id, action_type, description) VALUES ($task_id, {$_SESSION['user_id']}, 'delete_file', 'Deleted file: {$file['original_name']}')");
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'File not found']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>
