<?php
require_once '../header.php';

// Calculate Stats
$income = db_fetch_one("SELECT SUM(amount) as total FROM invoices WHERE status = 'Paid'")['total'] ?? 0;
$total_expenses = db_fetch_one("SELECT SUM(amount) as total FROM expenses")['total'] ?? 0;
$profit = $income - $total_expenses;

$sql = "SELECT * FROM expenses ORDER BY expense_date DESC";
$expenses = db_fetch_all($sql);
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stats shadow bg-base-100">
        <div class="stat">
            <div class="stat-title">Total Income</div>
            <div class="stat-value text-success"><?php echo format_money($income); ?></div>
            <div class="stat-desc">From paid invoices</div>
        </div>
    </div>
    
    <div class="stats shadow bg-base-100">
        <div class="stat">
            <div class="stat-title">Total Expenses</div>
            <div class="stat-value text-error"><?php echo format_money($total_expenses); ?></div>
            <div class="stat-desc">All time</div>
        </div>
    </div>
    
    <div class="stats shadow bg-base-100">
        <div class="stat">
            <div class="stat-title">Net Profit</div>
            <div class="stat-value <?php echo $profit >= 0 ? 'text-primary' : 'text-warning'; ?>"><?php echo format_money($profit); ?></div>
            <div class="stat-desc"><?php echo $profit >= 0 ? 'Healthy' : 'Loss'; ?></div>
        </div>
    </div>
</div>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold">Expenses</h2>
    <a href="expense_add.php" class="btn btn-primary">+ Add Expense</a>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="5" class="text-center">No expenses found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
                        <tr class="hover">
                            <td class="font-bold"><?php echo htmlspecialchars($expense['title']); ?></td>
                            <td><span class="badge badge-ghost"><?php echo htmlspecialchars($expense['category']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                            <td class="text-error font-bold">-<?php echo format_money($expense['amount']); ?></td>
                            <td>
                                <a href="expense_edit.php?id=<?php echo $expense['id']; ?>" class="btn btn-ghost btn-xs">Edit</a>
                                <button onclick="openDeleteModal(<?php echo $expense['id']; ?>)" class="btn btn-ghost btn-xs text-error">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>

<!-- Delete Confirmation Modal -->
<dialog id="delete_modal" class="modal">
  <div class="modal-box">
    <h3 class="font-bold text-lg">Confirm Deletion</h3>
    <p class="py-4">Are you sure you want to delete this expense? This action cannot be undone.</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Cancel</button>
      </form>
      <a id="confirm_delete_btn" href="#" class="btn btn-error">Delete</a>
    </div>
  </div>
</dialog>

<script>
function openDeleteModal(expenseId) {
    const modal = document.getElementById('delete_modal');
    const deleteBtn = document.getElementById('confirm_delete_btn');
    deleteBtn.href = 'expense_delete.php?id=' + expenseId;
    modal.showModal();
}
</script>
