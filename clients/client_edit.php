<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$client = db_fetch_one("SELECT * FROM clients WHERE id = $id");

if (!$client) {
    redirect('clients/client_list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = escape($_POST['name']);
    $slug = escape($_POST['slug']);
    if (empty($slug)) {
        $slug = generate_slug($name);
    }
    $email = escape($_POST['email']);
    $phone = escape($_POST['phone']);
    $company = escape($_POST['company']);
    $notes = escape($_POST['notes']);
    $status = escape($_POST['status']);

    $sql = "UPDATE clients SET name='$name', slug='$slug', email='$email', phone='$phone', company='$company', notes='$notes', status='$status' WHERE id=$id";
    
    if (db_query($sql)) {
        redirect('clients/client_list.php');
    }
}

require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Edit Client</h2>
        <a href="client_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST">
                <div class="form-control w-full">
                    <label class="label">
                        <span class="label-text">Client Name</span>
                    </label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text">Slug</span>
                    </label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($client['slug'] ?? ''); ?>" class="input input-bordered w-full" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Email</span>
                        </label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" class="input input-bordered w-full" />
                    </div>
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Phone</span>
                        </label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" class="input input-bordered w-full" />
                    </div>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text">Company</span>
                    </label>
                    <input type="text" name="company" value="<?php echo htmlspecialchars($client['company'] ?? ''); ?>" class="input input-bordered w-full" />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text">Status</span>
                    </label>
                    <select name="status" class="select select-bordered">
                        <option value="Active" <?php echo $client['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $client['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Lead" <?php echo $client['status'] == 'Lead' ? 'selected' : ''; ?>>Lead</option>
                    </select>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text">Notes</span>
                    </label>
                    <textarea name="notes" class="textarea textarea-bordered h-24"><?php echo htmlspecialchars($client['notes']); ?></textarea>
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary">Update Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
