<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['theme'])) {
    $_SESSION['theme'] = $data['theme'];
    echo json_encode(['success' => true, 'theme' => $_SESSION['theme']]);
} else {
    echo json_encode(['success' => false]);
}
