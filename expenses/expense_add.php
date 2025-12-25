<?php
require_once '../config.php';
require_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = escape($_POST['title']);
    $amount = (float)$_POST['amount'];
    $category = escape($_POST['category']);
    $expense_date = escape($_POST['expense_date']);

    $sql = "INSERT INTO expenses (title, amount, category, expense_date) VALUES ('$title', $amount, '$category', '$expense_date')";
    
    if (mysqli_query($conn, $sql)) {
        redirect('expenses/expense_list.php');
    }
}

require_once '../header.php';
?>

<div class="max-w-md mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Add Expense</h2>
        <a href="expense_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Title</span></label>
                    <input type="text" name="title" placeholder="e.g. Hosting Renewal" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Amount ($)</span></label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Category</span></label>
                    <select name="category" class="select select-bordered">
                        <option value="Software">Software</option>
                        <option value="Hosting">Hosting</option>
                        <option value="Office">Office</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Date</span></label>
                    <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered w-full" required />
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
