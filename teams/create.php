<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape($_POST['name']);
    $description = escape($_POST['description']);
    $leader_id = (int)$_POST['leader_id'];

    $sql = "INSERT INTO teams (name, description, leader_id) VALUES ('$name', '$description', $leader_id)";
    if (db_query($sql)) {
        // Add leader as a member automatically
        $team_id = mysqli_insert_id($conn);
        db_query("INSERT INTO team_members (team_id, user_id, role) VALUES ($team_id, $leader_id, 'Leader')");
        
        redirect('teams/index.php');
    } else {
        $error = "Error creating team.";
    }
}

// Fetch Users for Leader Selection
$users = db_fetch_all("SELECT * FROM users ORDER BY name ASC");

$page_title = "Create Team";
require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="index.php" class="btn btn-circle btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <h1 class="text-3xl font-bold text-base-content">Create New Team</h1>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
            <?php csrf_field(); ?>
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Team Name</span></label>
                    <input type="text" name="name" class="input input-bordered w-full" required placeholder="e.g. Marketing Squad" />
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Description</span></label>
                    <textarea name="description" class="textarea textarea-bordered h-24" placeholder="What is this team for?"></textarea>
                </div>

                <div class="form-control w-full mb-6">
                    <label class="label"><span class="label-text">Team Leader</span></label>
                    <select name="leader_id" class="select select-bordered w-full" required>
                        <option value="" disabled selected>Select a leader</option>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No users found</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary">Create Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
