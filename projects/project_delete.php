<?php
require_once '../config.php';
require_once '../functions.php';

// Access Control: Clients cannot delete projects
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    redirect('projects/project_list.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Optional: Delete related tasks, milestones, etc.
    // For now, simple delete
    $sql = "DELETE FROM projects WHERE id = $id";
    db_query($sql);
}

redirect('projects/project_list.php');
?>
