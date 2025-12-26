<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'manager']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice = db_fetch_one("SELECT * FROM invoices WHERE id = $id");

if (!$invoice) {
    redirect('invoices/invoice_list.php');
}

$clients = db_fetch_all("SELECT * FROM clients WHERE status = 'Active'");
$projects = db_fetch_all("SELECT * FROM projects WHERE status = 'In Progress'");
$invoice_items = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = $id");

// Prepare items for AlpineJS
$js_items = [];
foreach ($invoice_items as $item) {
    $js_items[] = [
        'desc' => escape($item['description']),
        'qty' => (float)$item['quantity'],
        'price' => (float)$item['unit_price']
    ];
}
if (empty($js_items)) {
    $js_items[] = ['desc' => '', 'qty' => 1, 'price' => 0];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = (int)$_POST['client_id'];
    $project_id = (int)$_POST['project_id'];
    $invoice_number = escape($_POST['invoice_number']);
    $due_date = escape($_POST['due_date']);
    $status = escape($_POST['status']);
    
    // Calculate Total & Prepare Items
    $total_amount = 0;
    $items = [];
    
    if (isset($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            $qty = (float)$item['qty'];
            $price = (float)$item['price'];
            $total = $qty * $price;
            $total_amount += $total;
            $items[] = [
                'desc' => escape($item['desc']),
                'qty' => $qty,
                'price' => $price,
                'total' => $total
            ];
        }
    }

    $sql = "UPDATE invoices SET client_id = $client_id, project_id = $project_id, invoice_number = '$invoice_number', amount = $total_amount, due_date = '$due_date', status = '$status' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        // Delete existing items and re-insert
        db_query("DELETE FROM invoice_items WHERE invoice_id = $id");
        
        foreach ($items as $item) {
            $sql_item = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES ($id, '{$item['desc']}', {$item['qty']}, {$item['price']}, {$item['total']})";
            mysqli_query($conn, $sql_item);
        }
        
        redirect('invoices/invoice_view.php?id=' . $id);
    }
}

require_once '../header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Edit Invoice</h2>
        <a href="invoice_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <form method="POST" x-data="{ items: <?php echo htmlspecialchars(json_encode($js_items)); ?> }">
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">Client</span></label>
                        <select name="client_id" class="select select-bordered" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client['id'] == $invoice['client_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">Project (Optional)</span></label>
                        <select name="project_id" class="select select-bordered">
                            <option value="0">None</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $invoice['project_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($project['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">Invoice Number</span></label>
                        <input type="text" name="invoice_number" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" class="input input-bordered" required />
                    </div>
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">Due Date</span></label>
                        <input type="date" name="due_date" value="<?php echo $invoice['due_date']; ?>" class="input input-bordered" required />
                    </div>
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text">Status</span></label>
                        <select name="status" class="select select-bordered">
                            <option value="Unpaid" <?php echo $invoice['status'] == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="Paid" <?php echo $invoice['status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Overdue" <?php echo $invoice['status'] == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h3 class="card-title text-lg mb-4">Items</h3>
                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="flex gap-4 items-end">
                            <div class="form-control flex-1">
                                <label class="label" x-show="index === 0"><span class="label-text">Description</span></label>
                                <input type="text" :name="'items[' + index + '][desc]'" x-model="item.desc" class="input input-bordered w-full" placeholder="Item description" required />
                            </div>
                            <div class="form-control w-24">
                                <label class="label" x-show="index === 0"><span class="label-text">Qty</span></label>
                                <input type="number" :name="'items[' + index + '][qty]'" x-model="item.qty" class="input input-bordered w-full" required />
                            </div>
                            <div class="form-control w-32">
                                <label class="label" x-show="index === 0"><span class="label-text">Price</span></label>
                                <input type="number" step="0.01" :name="'items[' + index + '][price]'" x-model="item.price" class="input input-bordered w-full" required />
                            </div>
                            <div class="form-control w-32">
                                <label class="label" x-show="index === 0"><span class="label-text">Total</span></label>
                                <div class="input input-bordered w-full flex items-center bg-base-200" x-text="(item.qty * item.price).toFixed(2)"></div>
                            </div>
                            <button type="button" class="btn btn-square btn-ghost text-error" @click="items.splice(index, 1)" x-show="items.length > 1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" class="btn btn-ghost btn-sm mt-4" @click="items.push({desc: '', qty: 1, price: 0})">+ Add Item</button>
            </div>
        </div>

        <div class="flex justify-end gap-4">
            <div class="text-2xl font-bold">
                Total: $<span x-text="items.reduce((sum, item) => sum + (item.qty * item.price), 0).toFixed(2)">0.00</span>
            </div>
            <button type="submit" class="btn btn-primary">Update Invoice</button>
        </div>
    </form>
</div>

<?php require_once '../footer.php'; ?>
