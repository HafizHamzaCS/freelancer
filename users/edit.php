<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = db_fetch_one("SELECT * FROM users WHERE id = $id");

if (!$user) {
    redirect('users/index.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape($_POST['name']);
    $email = escape($_POST['email']);
    $role = escape($_POST['role']);
    
    // Password update is optional
    $pass_sql = "";
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pass_sql = ", password = '$password'";
    }

    $sql = "UPDATE users SET name = '$name', email = '$email', role = '$role' $pass_sql WHERE id = $id";
    
    if (db_query($sql)) {
        redirect('users/index.php');
    } else {
        $error = "Error updating user.";
    }
}

$page_title = "Edit User";
require_once '../header.php';
?>

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="index.php" class="btn btn-circle btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <h1 class="text-3xl font-bold text-base-content">Edit User</h1>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Full Name</span></label>
                    <input type="text" name="name" class="input input-bordered w-full" value="<?php echo htmlspecialchars($user['name']); ?>" required />
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Email Address</span></label>
                    <input type="email" name="email" class="input input-bordered w-full" value="<?php echo htmlspecialchars($user['email']); ?>" required />
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Password (Leave blank to keep current)</span></label>
                    <input type="password" name="password" class="input input-bordered w-full" />
                </div>

                <div class="form-control w-full mb-6">
                    <label class="label"><span class="label-text">Role</span></label>
                    <select name="role" class="select select-bordered w-full">
                        <option value="member" <?php echo $user['role'] == 'member' ? 'selected' : ''; ?>>Member</option>
                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="project_manager" <?php echo $user['role'] == 'project_manager' ? 'selected' : ''; ?>>Project Manager</option>
                        <option value="member" <?php echo $user['role'] == 'member' ? 'selected' : ''; ?>>Member (Developer)</option>
                        <option value="viewer" <?php echo $user['role'] == 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-full">Update User</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
