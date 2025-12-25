<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$team = db_fetch_one("SELECT * FROM teams WHERE id = $id");

if (!$team) {
    redirect('teams/index.php');
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape($_POST['name']);
    $description = escape($_POST['description']);
    $leader_id = (int)$_POST['leader_id'];

    $sql = "UPDATE teams SET name = '$name', description = '$description', leader_id = $leader_id WHERE id = $id";
    
    if (db_query($sql)) {
        // Ensure leader is a member
        $check = db_fetch_one("SELECT * FROM team_members WHERE team_id = $id AND user_id = $leader_id");
        if (!$check) {
             db_query("INSERT INTO team_members (team_id, user_id, role) VALUES ($id, $leader_id, 'Leader')");
        } else {
             // Optional: Update role to Leader if they were just a member
             db_query("UPDATE team_members SET role = 'Leader' WHERE team_id = $id AND user_id = $leader_id");
        }

        redirect('teams/index.php');
    } else {
        $error = "Error updating team.";
    }
}

// Fetch Users for Leader Selection
$users = db_fetch_all("SELECT * FROM users ORDER BY name ASC");

$page_title = "Edit Team";
require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="index.php" class="btn btn-circle btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <h1 class="text-3xl font-bold text-base-content">Edit Team</h1>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Team Name</span></label>
                    <input type="text" name="name" class="input input-bordered w-full" required value="<?php echo htmlspecialchars($team['name']); ?>" />
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Description</span></label>
                    <textarea name="description" class="textarea textarea-bordered h-24"><?php echo htmlspecialchars($team['description']); ?></textarea>
                </div>

                <div class="form-control w-full mb-6">
                    <label class="label"><span class="label-text">Team Leader</span></label>
                    <select name="leader_id" class="select select-bordered w-full" required>
                        <option value="" disabled>Select a leader</option>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $team['leader_id'] == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No users found</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary">Update Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
