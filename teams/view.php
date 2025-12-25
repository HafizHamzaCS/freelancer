<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$team = db_fetch_one("SELECT t.*, u.name as leader_name FROM teams t LEFT JOIN users u ON t.leader_id = u.id WHERE t.id = $id");

if (!$team) {
    redirect('teams/index.php');
}

// Handle Add Member
if (isset($_POST['add_member'])) {
    $user_id = (int)$_POST['user_id'];
    $role = escape($_POST['role']);
    
    // Check if already member
    $check = db_fetch_one("SELECT * FROM team_members WHERE team_id = $id AND user_id = $user_id");
    if (!$check) {
        db_query("INSERT INTO team_members (team_id, user_id, role) VALUES ($id, $user_id, '$role')");
    }
    // Refresh to clear POST
    redirect("teams/view.php?id=$id");
}

// Handle Remove Member
if (isset($_POST['remove_member'])) {
    $member_id = (int)$_POST['remove_member'];
    db_query("DELETE FROM team_members WHERE id = $member_id");
    redirect("teams/view.php?id=$id");
}

// Handle Assign Project
if (isset($_POST['assign_project'])) {
    $project_id = (int)$_POST['project_id']; // Fix: use project_id, not assign_project (which is just the flag)
    db_query("UPDATE projects SET team_id = $id WHERE id = $project_id");
    redirect("teams/view.php?id=$id");
}

// Handle Unassign Project
if (isset($_POST['unassign_project'])) {
    $project_id = (int)$_POST['unassign_project'];
    db_query("UPDATE projects SET team_id = NULL WHERE id = $project_id");
    redirect("teams/view.php?id=$id");
}

// Fetch Data
$members = db_fetch_all("SELECT tm.*, u.name, u.email FROM team_members tm JOIN users u ON tm.user_id = u.id WHERE tm.team_id = $id");
$projects = db_fetch_all("SELECT * FROM projects WHERE team_id = $id");
$available_users = db_fetch_all("SELECT * FROM users WHERE id NOT IN (SELECT user_id FROM team_members WHERE team_id = $id)");
$available_projects = db_fetch_all("SELECT * FROM projects WHERE team_id IS NULL AND status != 'Completed'");

$page_title = "Team Details";
require_once '../header.php';
?>

<div class="mb-6">
    <div class="flex items-center gap-4 mb-2">
        <a href="index.php" class="btn btn-circle btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-base-content"><?php echo htmlspecialchars($team['name']); ?></h1>
            <p class="text-base-content/70"><?php echo htmlspecialchars($team['description']); ?></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Members Column -->
    <div class="lg:col-span-1 space-y-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="card-title text-lg">Team Members</h2>
                    <button class="btn btn-xs btn-primary" onclick="member_modal.showModal()">+ Add</button>
                </div>
                
                <div class="space-y-4">
                    <?php foreach ($members as $member): ?>
                    <div class="flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <div class="avatar placeholder">
                                <div class="bg-neutral-focus text-neutral-content rounded-full w-8">
                                    <span><?php echo substr($member['name'], 0, 1); ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="font-bold text-sm"><?php echo htmlspecialchars($member['name']); ?></div>
                                <div class="text-xs opacity-50"><?php echo htmlspecialchars($member['role']); ?></div>
                            </div>
                        </div>
                        <?php if ($member['user_id'] != $team['leader_id']): ?>
                        <form method="POST" onsubmit="return confirm('Remove this member?');">
                            <input type="hidden" name="remove_member" value="<?php echo $member['id']; ?>">
                            <button class="btn btn-ghost btn-xs text-error opacity-0 group-hover:opacity-100">✕</button>
                        </form>
                        <?php else: ?>
                            <span class="badge badge-xs badge-primary">Leader</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Column -->
    <div class="lg:col-span-2 space-y-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="card-title text-lg">Assigned Projects</h2>
                    <button class="btn btn-xs btn-primary" onclick="project_modal.showModal()">+ Assign Project</button>
                </div>

                <?php if (empty($projects)): ?>
                    <div class="text-center py-8 text-base-content/50">
                        <p>No projects assigned to this team yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Deadline</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td class="font-bold">
                                        <a href="../projects/project_view.php?id=<?php echo $project['id']; ?>" class="hover:underline">
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-sm badge-ghost"><?php echo $project['status']; ?></span></td>
                                    <td><?php echo $project['deadline'] ? date('M d', strtotime($project['deadline'])) : '-'; ?></td>
                                    <td>
                                        <button class="btn btn-xs btn-ghost text-error" onclick="confirmUnassign(<?php echo $project['id']; ?>)">Unassign</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<dialog id="member_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Add Team Member</h3>
        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="add_member" value="1">
            <div class="form-control">
                <label class="label"><span class="label-text">Select User</span></label>
                <select name="user_id" class="select select-bordered" required>
                    <?php if (empty($available_users)): ?>
                        <option value="" disabled selected>No other users available</option>
                    <?php else: ?>
                        <?php foreach ($available_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">Role</span></label>
                <input type="text" name="role" class="input input-bordered" value="Member" required />
            </div>
            <div class="modal-action">
                <button class="btn btn-primary">Add</button>
                <button type="button" class="btn" onclick="member_modal.close()">Close</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Assign Project Modal -->
<dialog id="project_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Assign Project</h3>
        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="assign_project" value="1">
            <div class="form-control">
                <label class="label"><span class="label-text">Select Project</span></label>
                <select name="project_id" class="select select-bordered" required>
                    <?php foreach ($available_projects as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-action">
                <button class="btn btn-primary">Assign</button>
                <button type="button" class="btn" onclick="project_modal.close()">Close</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Unassign Confirmation Modal -->
<dialog id="unassign_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg text-error">Unassign Project?</h3>
        <p class="py-4">Are you sure you want to remove this project from the team?</p>
        <form method="POST" class="modal-action">
            <input type="hidden" name="unassign_project" id="unassign_project_id">
            <button class="btn btn-error">Yes, Unassign</button>
            <button type="button" class="btn" onclick="unassign_modal.close()">Cancel</button>
        </form>
    </div>
</dialog>

<script>
function confirmUnassign(projectId) {
    document.getElementById('unassign_project_id').value = projectId;
    unassign_modal.showModal();
}
</script>

<script>
// Modal Cleanup Logic
['member_modal', 'project_modal', 'unassign_modal'].forEach(id => {
    const el = document.getElementById(id);
    if(el) {
        el.addEventListener('close', () => {
            const form = el.querySelector('form');
            if(form) form.reset();
        });
    }
});
</script>

<?php require_once '../footer.php'; ?>
