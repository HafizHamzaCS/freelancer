<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

// Ensure last_contacted column exists
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM clients LIKE 'last_contacted'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE clients ADD COLUMN last_contacted DATE NULL");
}

// Handle "Mark as Contacted"
if (isset($_GET['contacted'])) {
    $id = (int)$_GET['contacted'];
    mysqli_query($conn, "UPDATE clients SET last_contacted = CURDATE() WHERE id = $id");
    redirect('clients/client_follow-up.php');
}

require_once '../header.php';

// Get clients not contacted in last 30 days or never contacted
$sql = "SELECT * FROM clients WHERE status = 'Active' AND (last_contacted IS NULL OR last_contacted < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) ORDER BY last_contacted ASC LIMIT 20";
$clients = db_fetch_all($sql);
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold">Follow-Up Required</h2>
    <div class="text-sm text-gray-500">Clients not contacted in 30+ days</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($clients)): ?>
        <div class="col-span-full text-center py-10">
            <div class="text-2xl text-gray-300">No follow-ups needed! 🎉</div>
        </div>
    <?php else: ?>
        <?php foreach ($clients as $client): ?>
        <div class="card bg-base-100 shadow-xl border-l-4 border-warning">
            <div class="card-body">
                <h3 class="card-title"><?php echo htmlspecialchars($client['name']); ?></h3>
                <p class="text-sm text-gray-500">
                    Last Contacted: 
                    <?php echo $client['last_contacted'] ? date('M d, Y', strtotime($client['last_contacted'])) : 'Never'; ?>
                </p>
                <div class="mt-4 flex justify-between items-center">
                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="btn btn-sm btn-outline">Email</a>
                    <a href="client_follow-up.php?contacted=<?php echo $client['id']; ?>" class="btn btn-sm btn-primary">Mark Contacted</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../footer.php'; ?>
