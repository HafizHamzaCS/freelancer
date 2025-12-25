<?php
require_once 'header.php';
require_once 'auth.php';

require_role(['admin', 'member']);

// Handle Promotion Send
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_promo'])) {
    $promo_type = escape($_POST['promo_type']);
    $subject = escape($_POST['subject']);
    $message = escape($_POST['message']);
    
    // Fetch Target Clients
    $where = "WHERE status = 'Active'";
    if ($promo_type == 'vip') {
        // VIP logic: earnings > 5000
        $where .= " AND id IN (SELECT client_id FROM invoices WHERE status='Paid' GROUP BY client_id HAVING SUM(amount) > 5000)";
    }
    
    $clients = db_fetch_all("SELECT * FROM clients $where");
    $count = 0;
    
    foreach ($clients as $client) {
        // Mock Send
        // mail($client['email'], $subject, $message);
        $count++;
    }
    
    $success = "Promotion sent to $count clients!";
}

// Get Active Events from Database
$events = db_fetch_all("SELECT * FROM events WHERE status = 'Active' ORDER BY start_date ASC");
?>

<div class="max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold mb-6">Promotions & Marketing</h2>

    <?php if (isset($success)): ?>
    <div class="alert alert-success mb-6">
        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Active Events -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Active Events</h3>
                <p class="text-sm text-gray-500 mb-4">Currently running campaigns</p>
                <div class="space-y-4">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-4 text-base-content/50">
                            No active events.
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                        <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg">
                            <div>
                                <div class="font-bold"><?php echo htmlspecialchars($event['title']); ?></div>
                                <div class="text-xs opacity-50"><?php echo date('M d', strtotime($event['start_date'])); ?> - <?php echo date('M d', strtotime($event['end_date'])); ?></div>
                            </div>
                            <span class="badge badge-success">Active</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-actions justify-end mt-4">
                    <a href="events/index.php" class="btn btn-sm btn-ghost">Manage Events</a>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Audience Reach</h3>
                <div class="stats stats-vertical lg:stats-horizontal shadow bg-base-200 w-full mt-2">
                    <div class="stat">
                        <div class="stat-title">Total Clients</div>
                        <div class="stat-value"><?php echo db_fetch_one("SELECT COUNT(*) as c FROM clients")['c']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">VIP Clients</div>
                        <div class="stat-value text-primary">
                            <?php 
                            // Mock VIP count query
                            $sql = "SELECT COUNT(DISTINCT client_id) as c FROM invoices WHERE status='Paid' GROUP BY client_id HAVING SUM(amount) > 5000";
                            $res = mysqli_query($conn, $sql);
                            echo $res ? mysqli_num_rows($res) : 0; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Promotion Form -->
    <div class="card bg-base-100 shadow-xl mt-6">
        <div class="card-body">
            <h3 class="card-title mb-4">Send Campaign</h3>
            <form method="POST">
                <input type="hidden" name="send_promo" value="1">
                
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Target Audience</span></label>
                    <select name="promo_type" class="select select-bordered">
                        <option value="all">All Active Clients</option>
                        <option value="vip">VIP Clients Only (> $5k)</option>
                    </select>
                </div>

                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Subject</span></label>
                    <input type="text" name="subject" placeholder="e.g. Special Offer for You!" class="input input-bordered" required />
                </div>

                <div class="form-control w-full mb-6">
                    <label class="label"><span class="label-text">Message</span></label>
                    <textarea name="message" class="textarea textarea-bordered h-32" placeholder="Write your message here..." required></textarea>
                </div>

                <div class="card-actions justify-end">
                    <button class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                        Send Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
