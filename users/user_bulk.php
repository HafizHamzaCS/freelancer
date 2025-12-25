<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids_json = $_POST['ids'] ?? '[]';
    $ids = json_decode($ids_json, true);
    $action_raw = $_POST['action'] ?? '';

    if (empty($ids) || empty($action_raw)) {
        set_flash('error', 'No users selected.');
        redirect('users/index.php');
    }

    // Protection: User cannot delete themselves
    $me = $_SESSION['user_id'];
    $ids = array_filter($ids, function($id) use ($me) {
        return (int)$id !== (int)$me;
    });

    if (empty($ids)) {
        set_flash('error', 'Current user cannot be deleted via bulk action.');
        redirect('users/index.php');
    }

    $id_list = implode(',', array_map('intval', $ids));

    if ($action_raw === 'delete') {
        db_query("DELETE FROM users WHERE id IN ($id_list)");
        set_flash('success', count($ids) . ' users deleted.');
    } else {
        $parts = explode('|', $action_raw);
        $field = escape($parts[0]);
        $value = escape($parts[1]);

        if ($field === 'role') {
            db_query("UPDATE users SET role = '$value' WHERE id IN ($id_list)");
            set_flash('success', count($ids) . " users updated to role: $value.");
        }
    }

    redirect('users/index.php');
}
