<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids_json = $_POST['ids'] ?? '[]';
    $ids = json_decode($ids_json, true);
    $action = $_POST['action'] ?? '';

    if (empty($ids) || empty($action)) {
        set_flash('error', 'No invoices selected.');
        redirect('invoices/invoice_list.php');
    }

    $id_list = implode(',', array_map('intval', $ids));

    if ($action === 'delete') {
        db_query("DELETE FROM invoices WHERE id IN ($id_list)");
        set_flash('success', count($ids) . ' invoices deleted.');
    } else {
        $status = escape($action);
        db_query("UPDATE invoices SET status = '$status' WHERE id IN ($id_list)");
        set_flash('success', count($ids) . ' invoices updated to ' . $status . '.');
    }

    redirect('invoices/invoice_list.php');
}
