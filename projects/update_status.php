<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['id']) && isset($input['status'])) {
    $id = (int)$input['id'];
    $status = escape($input['status']);
    
    // Validate status
    // Validate status
    $valid_statuses = ['Pending', 'In Progress', 'Review', 'Changes Requested', 'On Hold', 'Completed'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $sql = "UPDATE projects SET status = '$status' WHERE id = $id";
    if (db_query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
}
?>
