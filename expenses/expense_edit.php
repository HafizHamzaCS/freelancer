<?php
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$expense = db_fetch_one("SELECT * FROM expenses WHERE id = $id");

if (!$expense) {
    redirect('expenses/expense_list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = escape($_POST['title']);
    $amount = (float)$_POST['amount'];
    $category = escape($_POST['category']);
    $expense_date = escape($_POST['expense_date']);

    $sql = "UPDATE expenses SET title = '$title', amount = $amount, category = '$category', expense_date = '$expense_date' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        redirect('expenses/expense_list.php');
    }
}

require_once '../header.php';
?>

<div class="max-w-md mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Edit Expense</h2>
        <a href="expense_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Title</span></label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($expense['title']); ?>" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Amount ($)</span></label>
                    <input type="number" step="0.01" name="amount" value="<?php echo $expense['amount']; ?>" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Category</span></label>
                    <select name="category" class="select select-bordered">
                        <option value="Software" <?php echo $expense['category'] == 'Software' ? 'selected' : ''; ?>>Software</option>
                        <option value="Hosting" <?php echo $expense['category'] == 'Hosting' ? 'selected' : ''; ?>>Hosting</option>
                        <option value="Office" <?php echo $expense['category'] == 'Office' ? 'selected' : ''; ?>>Office</option>
                        <option value="Marketing" <?php echo $expense['category'] == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                        <option value="Other" <?php echo $expense['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Date</span></label>
                    <input type="date" name="expense_date" value="<?php echo $expense['expense_date']; ?>" class="input input-bordered w-full" required />
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary">Update Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
