<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids_json = $_POST['ids'] ?? '[]';
    $ids = json_decode($ids_json, true);
    $action = $_POST['action'] ?? '';

    if (empty($ids) || $action !== 'delete') {
        set_flash('error', 'No teams selected or invalid action.');
        redirect('teams/index.php');
    }

    $id_list = implode(',', array_map('intval', $ids));

    // 1. Unassign projects
    db_query("UPDATE projects SET team_id = NULL WHERE team_id IN ($id_list)");
    
    // 2. Remove members
    db_query("DELETE FROM team_members WHERE team_id IN ($id_list)");
    
    // 3. Delete teams
    db_query("DELETE FROM teams WHERE id IN ($id_list)");

    set_flash('success', count($ids) . ' teams deleted.');

    redirect('teams/index.php');
}
