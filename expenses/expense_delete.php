<?php
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    db_query("DELETE FROM expenses WHERE id = $id");
}

redirect('expenses/expense_list.php');
?>
