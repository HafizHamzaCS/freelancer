<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

// Ensure user is logged in as a client
if (!is_logged_in() || !isset($_SESSION['client_logged_in'])) {
    redirect('../login.php');
}

$client_id = $_SESSION['user_id'];
$client = db_fetch_one("SELECT * FROM clients WHERE id = $client_id");

// Fetch Projects
$projects = db_fetch_all("SELECT * FROM projects WHERE client_id = $client_id ORDER BY created_at DESC");

// Fetch Invoices
$invoices = db_fetch_all("SELECT * FROM invoices WHERE client_id = $client_id ORDER BY created_at DESC LIMIT 5");

// Calculate Stats
$active_projects = count(array_filter($projects, fn($p) => $p['status'] != 'Completed'));
$total_spent = 0;
foreach ($invoices as $inv) {
    if ($inv['status'] == 'Paid') {
        $total_spent += $inv['amount'];
    }
}

require_once '../header.php';
?>

<div class="navbar bg-base-100 shadow-sm rounded-box mb-8">
    <div class="flex-1">
        <a class="btn btn-ghost normal-case text-xl">Client Portal</a>
    </div>
    <div class="flex-none gap-2">
        <div class="dropdown dropdown-end">
            <label tabindex="0" class="btn btn-ghost btn-circle avatar">
                <div class="w-10 rounded-full">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($client['name']); ?>&background=random" />
                </div>
            </label>
            <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-52">
                <li><a>Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stats shadow">
        <div class="stat">
            <div class="stat-figure text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <div class="stat-title">Active Projects</div>
            <div class="stat-value text-primary"><?php echo $active_projects; ?></div>
            <div class="stat-desc">Currently in progress</div>
        </div>
    </div>
    
    <div class="stats shadow">
        <div class="stat">
            <div class="stat-figure text-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="stat-title">Total Spent</div>
            <div class="stat-value text-secondary"><?php echo format_money($total_spent); ?></div>
            <div class="stat-desc">Lifetime investment</div>
        </div>
    </div>
</div>

<h2 class="text-2xl font-bold mb-4">Your Projects</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
    <?php foreach ($projects as $project): ?>
    <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all cursor-pointer" onclick="window.location='project_view.php?id=<?php echo $project['id']; ?>'">
        <div class="card-body">
            <div class="flex justify-between items-start">
                <h3 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                <div class="badge <?php echo $project['status'] == 'Completed' ? 'badge-success' : 'badge-info'; ?>"><?php echo $project['status']; ?></div>
            </div>
            <p class="text-sm text-gray-500 mt-2">Deadline: <?php echo date('M d, Y', strtotime($project['deadline'])); ?></p>
            <div class="card-actions justify-end mt-4">
                <button class="btn btn-primary btn-sm">View Details</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<h2 class="text-2xl font-bold mb-4">Recent Invoices</h2>
<div class="overflow-x-auto">
    <table class="table w-full bg-base-100 shadow-xl rounded-box">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $invoice): ?>
            <tr>
                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                <td><?php echo format_money($invoice['amount']); ?></td>
                <td>
                    <div class="badge <?php echo $invoice['status'] == 'Paid' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $invoice['status']; ?>
                    </div>
                </td>
                <td>
                    <button class="btn btn-ghost btn-xs">Download</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../footer.php'; ?>
