<?php
require_once '../config.php';
require_once '../functions.php';

$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? escape($_GET['status']) : '';

$where = "WHERE 1";
if ($search) {
    $where .= " AND (i.invoice_number LIKE '%$search%' OR c.name LIKE '%$search%')";
}
if ($status_filter) {
    $where .= " AND i.status = '$status_filter'";
}

$sql = "SELECT i.*, c.name as client_name, p.name as project_name 
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.id 
        LEFT JOIN projects p ON i.project_id = p.id 
        $where 
        ORDER BY i.created_at DESC";
$invoices = db_fetch_all($sql);

if (isset($_GET['ajax_search'])) {
    if (empty($invoices)) {
        echo '<tr><td colspan="7" class="text-center">No invoices found.</td></tr>';
    } else {
        foreach ($invoices as $invoice) {
            $due = strtotime($invoice['due_date']);
            $is_overdue = $due < time() && $invoice['status'] != 'Paid';
            
            $status_badge = match($invoice['status']) {
                'Paid' => 'badge-success',
                'Unpaid' => 'badge-error',
                'Overdue' => 'badge-warning',
                default => 'badge-ghost'
            };

            echo '<tr class="hover">';
            echo '<td class="font-bold"><a href="invoice_view.php?id=' . $invoice['id'] . '" class="link link-hover">' . htmlspecialchars($invoice['invoice_number']) . '</a></td>';
            echo '<td><a href="../clients/client_view.php?id=' . $invoice['client_id'] . '" class="link link-hover">' . htmlspecialchars($invoice['client_name']) . '</a></td>';
            echo '<td>' . htmlspecialchars($invoice['project_name']) . '</td>';
            echo '<td>' . format_money($invoice['amount']) . '</td>';
            echo '<td><span class="badge ' . $status_badge . '">' . $invoice['status'] . '</span></td>';
            echo '<td><span class="' . ($is_overdue ? 'text-error font-bold' : '') . '">' . date('M d, Y', $due) . ($is_overdue ? ' <span class="tooltip" data-tip="Overdue!">⚠️</span>' : '') . '</span></td>';
            echo '<td>
                    <div class="flex items-center gap-2">
                        <a href="invoice_view.php?id=' . $invoice['id'] . '" class="btn btn-ghost btn-xs" title="View Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </a>
                        <a href="invoice_edit.php?id=' . $invoice['id'] . '" class="btn btn-ghost btn-xs" title="Edit Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </a>
                        <a href="invoice_send.php?id=' . $invoice['id'] . '" class="btn btn-ghost btn-xs" title="Send Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                        </a>
                        <button onclick="openDeleteModal(' . $invoice['id'] . ')" class="btn btn-ghost btn-xs text-error" title="Delete Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </td>';
            echo '</tr>';
        }
    }
    exit;
}

require_once '../header.php';
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold">Invoices</h2>
    <a href="invoice_add.php" class="btn btn-primary">+ Create Invoice</a>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <!-- Search & Filter -->
        <form method="GET" class="flex flex-wrap gap-4 mb-4">
            <input type="text" name="search" placeholder="Search invoice # or client..." class="input input-bordered w-full max-w-xs" value="<?php echo htmlspecialchars($search); ?>" />
            <select name="status" class="select select-bordered" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Unpaid" <?php echo $status_filter == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="Paid" <?php echo $status_filter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="Overdue" <?php echo $status_filter == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
            </select>
            <button class="btn btn-ghost">Filter</button>
            <?php if ($search || $status_filter): ?>
                <a href="invoice_list.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="7" class="text-center">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover">
                            <td class="font-bold">
                                <a href="invoice_view.php?id=<?php echo $invoice['id']; ?>" class="link link-hover">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </a>
                            </td>
                            <td>
                                <a href="../clients/client_view.php?id=<?php echo $invoice['client_id']; ?>" class="link link-hover">
                                    <?php echo htmlspecialchars($invoice['client_name']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($invoice['project_name']); ?></td>
                            <td><?php echo format_money($invoice['amount']); ?></td>
                            <td>
                                <span class="badge <?php echo $invoice['status'] == 'Paid' ? 'badge-success' : ($invoice['status'] == 'Unpaid' ? 'badge-error' : 'badge-warning'); ?>">
                                    <?php echo $invoice['status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $due = strtotime($invoice['due_date']);
                                    $is_overdue = $due < time() && $invoice['status'] != 'Paid';
                                ?>
                                <span class="<?php echo $is_overdue ? 'text-error font-bold' : ''; ?>">
                                    <?php echo date('M d, Y', $due); ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="tooltip" data-tip="Overdue!">⚠️</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <a href="invoice_view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-ghost btn-xs" title="View Invoice">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </a>
                                    <a href="invoice_edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-ghost btn-xs" title="Edit Invoice">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </a>
                                    <a href="invoice_send.php?id=<?php echo $invoice['id']; ?>" class="btn btn-ghost btn-xs" title="Send Invoice">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                    </a>
                                    <button onclick="openDeleteModal(<?php echo $invoice['id']; ?>)" class="btn btn-ghost btn-xs text-error" title="Delete Invoice">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<dialog id="delete_modal" class="modal">
  <div class="modal-box">
    <h3 class="font-bold text-lg">Confirm Deletion</h3>
    <p class="py-4">Are you sure you want to delete this invoice? This action cannot be undone.</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Cancel</button>
      </form>
      <a id="confirm_delete_btn" href="#" class="btn btn-error">Delete</a>
    </div>
  </div>
</dialog>

<script>
function openDeleteModal(invoiceId) {
    const modal = document.getElementById('delete_modal');
    const deleteBtn = document.getElementById('confirm_delete_btn');
    deleteBtn.href = 'invoice_delete.php?id=' + invoiceId;
    modal.showModal();
}

const searchInput = document.querySelector('input[name="search"]');
const tableBody = document.querySelector('tbody');

searchInput.addEventListener('input', function() {
    const searchTerm = this.value;
    // Keep current status filter if any
    const statusFilter = document.querySelector('select[name="status"]').value;
    
    fetch('invoice_list.php?ajax_search=1&search=' + encodeURIComponent(searchTerm) + '&status=' + encodeURIComponent(statusFilter))
        .then(response => response.text())
        .then(html => {
            tableBody.innerHTML = html;
        });
});
</script>

<?php require_once '../footer.php'; ?>
