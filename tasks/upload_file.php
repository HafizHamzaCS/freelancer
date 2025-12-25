<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Fallback for mime_content_type
if (!function_exists('mime_content_type')) {
    function mime_content_type($filename) {
        return 'application/octet-stream';
    }
}

// Global Exception Handler for JSON
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});

// Error Handler to catch warnings/notices
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return;
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr"]);
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
$upload_dir = __DIR__ . '/../assets/uploads/tasks/';

// Create dir if not exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        throw new Exception("Failed to create upload directory");
    }
    file_put_contents($upload_dir . '.htaccess', "Deny from all\n<FilesMatch \"\.(jpg|jpeg|png|gif|pdf|docx|txt)$\">\nAllow from all\n</FilesMatch>");
}

// Security Validation
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
$file_mime = mime_content_type($file['tmp_name']);

if (!in_array($file_mime, $allowed_types) && $file_mime !== 'application/octet-stream') {
     // Optional: looser check if mime detection fails
     // echo json_encode(['success' => false, 'error' => 'Invalid file type: ' . $file_mime]);
     // exit;
}

// Max size 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large (Max 10MB).']);
    exit;
}

// Generate Name
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$clean_name = generate_slug(pathinfo($file['name'], PATHINFO_FILENAME));
$new_name = $task_id . '_' . time() . '_' . substr(md5(rand()), 0, 8) . '.' . $ext;
$target_path = $upload_dir . $new_name;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Save to DB
    $user_id = $_SESSION['user_id'];
    $original_name = escape($file['name']);
    $file_path = 'assets/uploads/tasks/' . $new_name;
    $file_size = $file['size'];
    
    // Check if column exists or use raw query to avoid db_query die()
    $sql = "INSERT INTO task_files (task_id, user_id, file_path, original_name, file_size, file_type) 
            VALUES ($task_id, $user_id, '$file_path', '$original_name', $file_size, '$file_mime')";
    
    // Direct mysqli_query to avoid 'die' in db_query()
    if (mysqli_query($conn, $sql)) {
         $new_file_id = mysqli_insert_id($conn);
        
        // Log Activity
        $user_name = get_user_name();
        mysqli_query($conn, "INSERT INTO task_activity (task_id, user_id, action_type, description) VALUES ($task_id, $user_id, 'file_upload', 'Uploaded file: $original_name')");
        
        echo json_encode(['success' => true, 'file' => [
            'id' => $new_file_id,
            'name' => $original_name,
            'url' => APP_URL . '/' . $file_path,
            'date' => date('M d, H:i')
        ]]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . mysqli_error($conn)]);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload failed (move_uploaded_file error)']);
}
?>
