<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

require_role('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        die("You cannot delete yourself.");
    }

    $sql = "DELETE FROM users WHERE id = $id";
    db_query($sql);
}

redirect('users/index.php');
?>
