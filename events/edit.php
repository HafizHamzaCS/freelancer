<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = db_fetch_one("SELECT * FROM events WHERE id = $id");

if (!$event) {
    redirect('events/index.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = escape($_POST['title']);
    $start_date = escape($_POST['start_date']);
    $end_date = escape($_POST['end_date']);
    $status = escape($_POST['status']);
    
    // Basic validation
    if (strtotime($end_date) < strtotime($start_date)) {
        $error = "End date cannot be before start date.";
    } else {
        $sql = "UPDATE events SET title = '$title', start_date = '$start_date', end_date = '$end_date', status = '$status' WHERE id = $id";
        if (db_query($sql)) {
            redirect('events/index.php');
        } else {
            $error = "Error updating event.";
        }
    }
}

require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Edit Event</h2>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4">
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Event Title</span></label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" class="input input-bordered w-full" required />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">Start Date</span></label>
                        <input type="date" name="start_date" value="<?php echo $event['start_date']; ?>" class="input input-bordered w-full" required />
                    </div>
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">End Date</span></label>
                        <input type="date" name="end_date" value="<?php echo $event['end_date']; ?>" class="input input-bordered w-full" required />
                    </div>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select name="status" class="select select-bordered">
                        <option value="Active" <?php echo $event['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $event['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
