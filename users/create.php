<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape($_POST['name']);
    $email = escape($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = escape($_POST['role']);

    // Check email
    $check = db_fetch_one("SELECT id FROM users WHERE email = '$email'");
    if ($check) {
        $error = "Email already exists.";
    } else {
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
        if (db_query($sql)) {
            redirect('users/index.php');
        } else {
            $error = "Error creating user.";
        }
    }
}

$page_title = "Add User";
require_once '../header.php';
?>

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="index.php" class="btn btn-circle btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <h1 class="text-3xl font-bold text-base-content">Add New User</h1>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php csrf_field(); ?>
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Full Name <span class="text-error">*</span></span></label>
                    <input type="text" name="name" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Email Address <span class="text-error">*</span></span></label>
                    <input type="email" name="email" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Password <span class="text-error">*</span></span></label>
                    <input type="password" name="password" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mb-6">
                    <label class="label"><span class="label-text">Role</span></label>
                    <select name="role" class="select select-bordered w-full">
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                        <option value="client">Client</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-full">Create User</button>
            </form>

            <script>
            document.querySelector('form').addEventListener('submit', function(e) {
                const name = this.name.value.trim();
                const email = this.email.value.trim();
                const password = this.password.value.trim();
                let error = '';

                if (!name) error = 'Full Name is required.';
                else if (!email || !email.includes('@')) error = 'Valid Email is required.';
                else if (!password || password.length < 6) error = 'Password must be at least 6 characters.';

                if (error) {
                    e.preventDefault();
                    // Remove existing alert
                    const existing = document.querySelector('.alert-error.js-validation');
                    if(existing) existing.remove();

                    // Create new alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-error mb-4 js-validation';
                    alertDiv.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>${error}</span>
                    `;
                    this.prepend(alertDiv);
                }
            });
            </script>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
