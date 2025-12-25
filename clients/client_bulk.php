<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids_json = $_POST['ids'] ?? '[]';
    $ids = json_decode($ids_json, true);
    $action = $_POST['action'] ?? '';

    if (empty($ids) || empty($action)) {
        set_flash('error', 'No clients selected.');
        redirect('clients/client_list.php');
    }

    $id_list = implode(',', array_map('intval', $ids));

    if ($action === 'delete') {
        db_query("DELETE FROM clients WHERE id IN ($id_list)");
        set_flash('success', count($ids) . ' clients deleted.');
    } else {
        $status = escape($action);
        db_query("UPDATE clients SET status = '$status' WHERE id IN ($id_list)");
        set_flash('success', count($ids) . ' clients updated to ' . $status . '.');
    }

    redirect('clients/client_list.php');
}
