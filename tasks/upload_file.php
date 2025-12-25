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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Global Exception Handler for JSON
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
    exit;
});

$task_id = (int)($_POST['task_id'] ?? 0);
if (!$task_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Task ID']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

// Security Validation using global helper
if (!validate_upload($file)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type or dangerous content detected.']);
    exit;
}

$upload_dir = __DIR__ . '/../assets/uploads/tasks/';

// Create dir if not exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit;
    }
    // Secure the directory
    file_put_contents($upload_dir . '.htaccess', "Deny from all\n<FilesMatch \"\.(jpg|jpeg|png|gif|pdf|docx|txt|zip)$\">\nAllow from all\n</FilesMatch>\nphp_flag engine off");
}

// Max size 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large (Max 10MB).']);
    exit;
}

// Generate Secure Name
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$new_name = $task_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$target_path = $upload_dir . $new_name;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Save to DB
    $user_id = $_SESSION['user_id'];
    $original_name = escape($file['name']);
    $file_path = 'assets/uploads/tasks/' . $new_name;
    $file_size = $file['size'];
    
    // Get MIME for DB
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_mime = finfo_file($finfo, $target_path);
    finfo_close($finfo);
    
    $sql = "INSERT INTO task_files (task_id, user_id, file_path, original_name, file_size, file_type) 
            VALUES ($task_id, $user_id, '$file_path', '$original_name', $file_size, '$file_mime')";
    
    if (db_query($sql)) {
         $new_file_id = mysqli_insert_id($conn);
        
        // Log Activity
        log_system_activity('File Upload', "Uploaded file: $original_name for task #$task_id");
        
        echo json_encode(['success' => true, 'file' => [
            'id' => $new_file_id,
            'name' => $original_name,
            'url' => APP_URL . '/' . $file_path,
            'date' => date('M d, H:i')
        ]]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database Error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
}
?>
