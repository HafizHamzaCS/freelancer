<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']); // Only updated for proper auth check

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Security check: ensure task exists
    $task = db_fetch_one("SELECT * FROM tasks WHERE id = $id");
    
    if ($task) {
        // Cleanup comments
        db_query("DELETE FROM task_comments WHERE task_id = $id");
        
        // Unlink children
        db_query("UPDATE tasks SET parent_id = NULL WHERE parent_id = $id");

        $sql = "DELETE FROM tasks WHERE id = $id";
        if (db_query($sql)) {
            // Success
        }
    }
}

redirect('tasks/index.php');
?>
