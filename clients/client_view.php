<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? escape($_GET['slug']) : '';

if ($id) {
    $client = db_fetch_one("SELECT * FROM clients WHERE id = $id");
} elseif ($slug) {
    $client = db_fetch_one("SELECT * FROM clients WHERE slug = '$slug'");
    if ($client) $id = $client['id'];
} else {
    $client = null;
}

if (!$client) {
    redirect('clients/client_list.php');
}

require_once '../header.php';

// Fetch Projects
$projects = db_fetch_all("SELECT * FROM projects WHERE client_id = $id ORDER BY created_at DESC");

// Fetch Invoices
$invoices = db_fetch_all("SELECT * FROM invoices WHERE client_id = $id ORDER BY created_at DESC");
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Client Info Card -->
    <div class="card bg-base-100 shadow-xl md:col-span-1">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="avatar placeholder">
                    <div class="bg-neutral-focus text-neutral-content rounded-full w-16">
                        <span class="text-2xl"><?php echo strtoupper(substr($client['name'], 0, 2)); ?></span>
                    </div>
                </div>
                <div>
                    <h2 class="card-title"><?php echo htmlspecialchars($client['name']); ?></h2>
                    <div class="badge <?php echo $client['status'] == 'Active' ? 'badge-success' : 'badge-ghost'; ?>"><?php echo $client['status']; ?></div>
                </div>
            </div>
            
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <span><?php echo htmlspecialchars($client['email']); ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    <span><?php echo htmlspecialchars($client['phone']); ?></span>
                </div>
            </div>

            <div class="divider"></div>
            
            <h3 class="font-bold mb-2">Notes</h3>
            <p class="text-sm text-base-content/70"><?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
            
            <div class="card-actions justify-end mt-4">
                <a href="<?php echo APP_URL; ?>/clients/client_edit.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-ghost">Edit Client</a>
            </div>
        </div>
    </div>

    <!-- Stats Column -->
    <div class="md:col-span-2 space-y-6">
        <!-- With Me vs On Platform Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="stats shadow bg-primary text-primary-content">
                <div class="stat">
                    <div class="stat-title text-primary-content opacity-70">Total Earnings (With Me)</div>
                    <div class="stat-value text-2xl">
                        <?php 
                        $earnings = db_fetch_one("SELECT SUM(amount) as total FROM invoices WHERE client_id = $id AND status = 'Paid'");
                        echo format_money($earnings['total'] ?? 0); 
                        ?>
                    </div>
                    <div class="stat-desc text-primary-content opacity-70">From <?php echo count($projects); ?> projects</div>
                </div>
            </div>
            
            <div class="stats shadow bg-base-100">
                <div class="stat">
                    <div class="stat-title">Platform Spend (Est.)</div>
                    <div class="stat-value text-2xl">$12,450</div>
                    <div class="stat-desc">Top 5% Client on Upwork</div>
                </div>
            </div>
        </div>

        <!-- Activity Tabs -->
        <div role="tablist" class="tabs tabs-lifted">
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="Projects" checked />
            <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold">Active Projects</h3>
                    <a href="../projects/project_add.php?client_id=<?php echo $client['id']; ?>" class="btn btn-xs btn-primary">+ Add Project</a>
                </div>
                <table class="table table-sm">
                    <thead><tr><th>Name</th><th>Status</th><th>Deadline</th><th>Budget</th></tr></thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><a href="../projects/project_view.php?id=<?php echo $project['id']; ?>" class="link link-hover"><?php echo htmlspecialchars($project['name']); ?></a></td>
                            <td><span class="badge badge-sm badge-outline"><?php echo $project['status']; ?></span></td>
                            <td><?php echo $project['deadline']; ?></td>
                            <td><?php echo $project['budget']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="Invoices" />
            <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                 <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold">Recent Invoices</h3>
                    <a href="../invoices/invoice_add.php?client_id=<?php echo $client['id']; ?>" class="btn btn-xs btn-primary">+ Create Invoice</a>
                </div>
                <table class="table table-sm">
                    <thead><tr><th>Number</th><th>Amount</th><th>Status</th><th>Due</th></tr></thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><a href="../invoices/invoice_view.php?id=<?php echo $invoice['id']; ?>" class="link link-hover"><?php echo htmlspecialchars($invoice['invoice_number']); ?></a></td>
                            <td><?php echo format_money($invoice['amount']); ?></td>
                            <td><span class="badge badge-sm <?php echo $invoice['status'] == 'Paid' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $invoice['status']; ?></span></td>
                            <td><?php echo $invoice['due_date']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="Emails" />
            <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                <h3 class="font-bold mb-4">Email History</h3>
                <?php 
                $emails = db_fetch_all("SELECT * FROM email_logs WHERE client_id = $id ORDER BY sent_at DESC LIMIT 5");
                if (empty($emails)): ?>
                    <p class="text-sm text-base-content/50">No emails sent yet.</p>
                <?php else: ?>
                    <ul class="steps steps-vertical w-full">
                        <?php foreach ($emails as $email): ?>
                        <li class="step step-primary" data-content="✉️">
                            <div class="text-left">
                                <div class="font-bold"><?php echo htmlspecialchars($email['subject']); ?></div>
                                <div class="text-xs opacity-50"><?php echo $email['sent_at']; ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
