<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Optional: Check for dependencies (invoices, projects) before deleting
    // For now, we will just delete the client
    $sql = "DELETE FROM clients WHERE id = $id";
    db_query($sql);
}

redirect('clients/client_list.php');
?>
