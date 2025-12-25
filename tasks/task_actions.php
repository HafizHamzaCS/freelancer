<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

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
