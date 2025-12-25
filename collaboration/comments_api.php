<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_name = get_user_name();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'post_comment') {
    $content = escape($_POST['content']);
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 'NULL';
    $task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 'NULL';

    if (!$content) {
        echo json_encode(['error' => 'Content required']);
        exit;
    }

    $sql = "INSERT INTO comments (project_id, task_id, user_id, content) VALUES ($project_id, $task_id, $user_id, '$content')";
    
    if (db_query($sql)) {
        // Log Activity
        $target_type = $task_id !== 'NULL' ? 'Task' : 'Project';
        $target_id = $task_id !== 'NULL' ? $task_id : $project_id;
        $details = "User posted a comment on $target_type #$target_id";
        
        db_query("INSERT INTO activity_log (user_id, action, resource_type, resource_id, details) 
                  VALUES ($user_id, 'comment', '$target_type', $target_id, '$details')");

        echo json_encode(['success' => true, 'user' => $user_name, 'date' => date('M d, H:i')]);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_comments') {
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
    
    $where = "1=0";
    if ($project_id) $where = "project_id = $project_id";
    if ($task_id) $where = "task_id = $task_id";

    $sql = "SELECT c.*, u.name as user_name, u.role 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE $where 
            ORDER BY c.created_at DESC";
    
    $comments = db_fetch_all($sql);
    echo json_encode(['comments' => $comments]);
    exit;
}
?>
