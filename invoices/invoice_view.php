<?php
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice = db_fetch_one("SELECT i.*, c.name as client_name, c.email as client_email, c.phone as client_phone, p.name as project_name 
                         FROM invoices i 
                         LEFT JOIN clients c ON i.client_id = c.id 
                         LEFT JOIN projects p ON i.project_id = p.id 
                         WHERE i.id = $id");

if (!$invoice) {
    redirect('invoices/invoice_list.php');
}

// Access Control
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    $current_client_id = 0;
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'user') {
        $email = $_SESSION['user_email'];
        $cr = db_fetch_one("SELECT id FROM clients WHERE email = '$email'");
        if ($cr) $current_client_id = $cr['id'];
    } else {
        $current_client_id = $_SESSION['user_id'];
    }
    if ($invoice['client_id'] != $current_client_id) {
        redirect('invoices/invoice_list.php');
    }
} elseif (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    $uid = $_SESSION['user_id'];
    $project_id = $invoice['project_id'];
    $is_member = db_fetch_one("SELECT 1 FROM project_members WHERE project_id = $project_id AND user_id = $uid");
    if (!$is_member) {
        redirect('invoices/invoice_list.php');
    }
}

// Handle Status Change
if (isset($_GET['mark_paid'])) {
    mysqli_query($conn, "UPDATE invoices SET status = 'Paid' WHERE id = $id");
    redirect("invoices/invoice_view.php?id=$id");
}

require_once '../header.php';

$items = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = $id");
?>

<div class="flex justify-between items-center mb-6 print:hidden">
    <div class="flex gap-2">
        <a href="invoice_list.php" class="btn btn-ghost">Back to List</a>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="btn btn-outline">Print / PDF</button>
        <a href="invoice_send.php?id=<?php echo $id; ?>" class="btn btn-primary">Send to Client</a>
        <?php if ($invoice['status'] != 'Paid'): ?>
            <a href="invoice_view.php?id=<?php echo $id; ?>&mark_paid=1" class="btn btn-success">Mark as Paid</a>
        <?php endif; ?>
    </div>
</div>

<div class="card bg-base-100 shadow-xl print:shadow-none print:w-full max-w-4xl mx-auto">
    <div class="card-body print:p-0">
        <!-- Header -->
        <div class="flex justify-between items-start mb-8">
            <div>
                <h1 class="text-4xl font-bold text-primary"><?php echo APP_NAME; ?></h1>
                <div class="text-sm opacity-50 mt-2">
                    123 Freelance St.<br>
                    Business City, 12345<br>
                    contact@freelanceempire.com
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-gray-400">INVOICE</h2>
                <div class="text-xl font-bold mt-2"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                <div class="text-sm mt-1">
                    Date: <?php echo date('M d, Y', strtotime($invoice['created_at'])); ?><br>
                    Due: <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                </div>
                <div class="badge <?php echo $invoice['status'] == 'Paid' ? 'badge-success' : 'badge-warning'; ?> mt-2 print:hidden">
                    <?php echo $invoice['status']; ?>
                </div>
            </div>
        </div>

        <!-- Bill To -->
        <div class="mb-8">
            <h3 class="text-gray-500 text-sm uppercase font-bold mb-2">Bill To:</h3>
            <div class="font-bold text-lg"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
            <div><?php echo htmlspecialchars($invoice['client_email']); ?></div>
            <div><?php echo htmlspecialchars($invoice['client_phone']); ?></div>
        </div>

        <!-- Items -->
        <div class="overflow-x-auto mb-8">
            <table class="table w-full">
                <thead>
                    <tr class="bg-base-200">
                        <th>Description</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-right"><?php echo $item['quantity']; ?></td>
                        <td class="text-right"><?php echo format_money($item['unit_price']); ?></td>
                        <td class="text-right"><?php echo format_money($item['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Total -->
        <div class="flex justify-end">
            <div class="w-64">
                <div class="flex justify-between py-2 border-b">
                    <span>Subtotal:</span>
                    <span><?php echo format_money($invoice['amount']); ?></span>
                </div>
                <div class="flex justify-between py-2 font-bold text-xl">
                    <span>Total:</span>
                    <span><?php echo format_money($invoice['amount']); ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-center text-sm text-gray-500">
            <p>Thank you for your business!</p>
            <p>Please make payment within 30 days.</p>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
