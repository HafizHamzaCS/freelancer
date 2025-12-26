<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'manager']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Delete invoice items first
    db_query("DELETE FROM invoice_items WHERE invoice_id = $id");
    // Delete invoice
    db_query("DELETE FROM invoices WHERE id = $id");
}

redirect('invoices/invoice_list.php');
?>
