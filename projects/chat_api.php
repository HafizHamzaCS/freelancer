<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

// Ensure user is logged in
$current_user = current_user();
if (!$current_user) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Auto-migration: Ensure messages table has sender_type
$cols = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM messages");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $cols[] = $row['Field'];
    }
    if (!in_array('sender_type', $cols)) {
        mysqli_query($conn, "ALTER TABLE messages ADD COLUMN sender_type VARCHAR(20) DEFAULT 'admin'");
    }
}

// Determine sender type and ID
$sender_type = $current_user['role']; // 'admin', 'member', or 'client'
$sender_id = $current_user['id'];

// Handle POST (Send Message)
// Handle POST (Send Message)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = 0;
    $message = '';
    $attachment_path = null;
    $attachment_type = null;

    // Check Content Type
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $project_id = isset($input['project_id']) ? (int)$input['project_id'] : 0;
        $message = isset($input['message']) ? escape($input['message']) : '';
    } else {
        // Form Data
        $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        $message = isset($_POST['message']) ? escape($_POST['message']) : '';
        
        // Handle File Upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'txt'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = '../assets/uploads/chat/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                $destination = $upload_dir . $new_name;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
                    $attachment_path = 'assets/uploads/chat/' . $new_name;
                    $attachment_type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file';
                }
            }
        }
    }
    
    if (!$project_id || (empty($message) && !$attachment_path)) {
        echo json_encode(['success' => false, 'message' => 'Missing fields or empty message']);
        exit;
    }

    $sql = "INSERT INTO messages (project_id, user_id, sender_type, message, attachment_path, attachment_type) 
            VALUES ($project_id, $sender_id, '$sender_type', '$message', " . 
            ($attachment_path ? "'$attachment_path'" : "NULL") . ", " . 
            ($attachment_type ? "'$attachment_type'" : "NULL") . ")";
    
    if (db_query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit;
}

// Handle GET (Fetch Messages)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['project_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing project_id']);
        exit;
    }

    $project_id = (int)$_GET['project_id'];
    
    $messages = db_fetch_all("SELECT * FROM messages WHERE project_id = $project_id ORDER BY created_at ASC");
    
    $enriched_messages = [];
    foreach ($messages as $msg) {
        $sender_name = 'Unknown';
        
        if ($msg['sender_type'] == 'client') {
            $client = db_fetch_one("SELECT name FROM clients WHERE id = " . $msg['user_id']);
            $sender_name = $client ? $client['name'] : 'Client';
        } else {
            // Admin or Member
            $user = db_fetch_one("SELECT name FROM users WHERE id = " . $msg['user_id']);
            $sender_name = $user ? $user['name'] : ucfirst($msg['sender_type']);
        }
        
        $msg['sender_name'] = $sender_name;
        $msg['is_me'] = ($msg['sender_type'] == $sender_type && $msg['user_id'] == $sender_id);
        $msg['formatted_time'] = date('H:i', strtotime($msg['created_at'])); // WhatsApp style time
        $enriched_messages[] = $msg;
    }

    echo json_encode(['success' => true, 'messages' => $enriched_messages]);
    exit;
}
?>
