<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids_json = $_POST['ids'] ?? '[]';
    $ids = json_decode($ids_json, true);
    $action_raw = $_POST['action'] ?? '';

    if (empty($ids) || empty($action_raw)) {
        set_flash('error', 'No tasks selected.');
        redirect('tasks/index.php');
    }

    $id_list = implode(',', array_map('intval', $ids));

    if ($action_raw === 'delete') {
        db_query("DELETE FROM tasks WHERE id IN ($id_list)");
        set_flash('success', count($ids) . ' tasks deleted.');
    } else {
        $parts = explode('|', $action_raw);
        $field = escape($parts[0]);
        $value = escape($parts[1]);

        if (in_array($field, ['status', 'priority'])) {
            db_query("UPDATE tasks SET $field = '$value' WHERE id IN ($id_list)");
            set_flash('success', count($ids) . " tasks updated: $field set to $value.");
        }
    }

    redirect('tasks/index.php');
}
