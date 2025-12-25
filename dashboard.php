<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'header.php';
// Temporary Migration Trigger
if (isset($_GET['migrate'])) {
    require_once 'update_db_security.php';
    exit;
}

// Access Control: Clients cannot view dashboard
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    redirect('projects/project_list.php');
}

// --- User Context Filter ---
$user_context_filter = "1=1";
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    $uid = $_SESSION['user_id'];
    $user_context_filter = "EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = $uid)";
}

// --- Projects Queue ---
$queue_sql = "SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.status = 'Pending' AND $user_context_filter ORDER BY p.created_at ASC";
$queue_projects = db_fetch_all($queue_sql);

// --- Other Existing Stats (Preserved) ---
// Active Projects
$projects_sql = "SELECT COUNT(*) as total FROM projects p WHERE p.status = 'In Progress' AND $user_context_filter";
$active_projects = db_fetch_one($projects_sql)['total'];

// Overdue Projects
$overdue_sql = "SELECT COUNT(*) as total FROM projects p WHERE p.deadline < CURDATE() AND p.status != 'Completed' AND $user_context_filter";
$overdue_projects = db_fetch_one($overdue_sql)['total'];

// Follow Up Clients (Random 4 Active)
$clients_sql = "SELECT * FROM clients WHERE status = 'Active' ORDER BY RAND() LIMIT 4";
$follow_up_clients = db_fetch_all($clients_sql);

// Upcoming Promotion
$promo_sql = "SELECT * FROM promotions WHERE status != 'Sent' ORDER BY scheduled_at ASC LIMIT 1";
$next_promo = db_fetch_one($promo_sql);

// --- New Stats for Cards ---
// 1. Projects in Queue
$queue_count = count($queue_projects);

// 2. Active Projects (Already have $active_projects)

// 3. Total Clients
$clients_count_sql = "SELECT COUNT(*) as total FROM clients WHERE status = 'Active'";
$total_clients = db_fetch_one($clients_count_sql)['total'];

// 4. Completed Projects
$completed_sql = "SELECT COUNT(*) as total FROM projects p WHERE p.status = 'Completed' AND $user_context_filter";
$completed_projects = db_fetch_one($completed_sql)['total'];

// 5. Upcoming Events (Milestones)
$events_sql = "SELECT m.*, p.name as project_name FROM milestones m JOIN projects p ON m.project_id = p.id WHERE m.status != 'Completed' AND m.due_date >= CURDATE() AND $user_context_filter ORDER BY m.due_date ASC LIMIT 5";
$upcoming_events = db_fetch_all($events_sql);

// 6. Delayed Projects (Details)
$overdue_details_sql = "SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.deadline < CURDATE() AND p.status != 'Completed' AND $user_context_filter ORDER BY p.deadline ASC";
$overdue_details = db_fetch_all($overdue_details_sql);
?>

<!-- Hero Section -->
<div class="mb-10">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Dashboard</h1>
            <p class="text-base-content/70">Welcome back, <?php echo get_user_name(); ?>!</p>
        </div>
    </div>

    <!-- Hero Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <!-- Delayed Projects Card (1st - New) -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-red-100 rounded-xl group-hover:bg-red-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Delayed Projects</p>
                    <h3 class="text-2xl font-black text-red-500 mt-1"><?php echo $overdue_projects; ?></h3>
                </div>
            </div>
        </div>

        <!-- Active Projects Card (2nd) -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-purple-100 rounded-xl group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Active Projects</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $active_projects; ?></h3>
                </div>
            </div>
        </div>

        <!-- Completed Projects Card (3rd) -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-orange-100 rounded-xl group-hover:bg-orange-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Completed Projects</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $completed_projects; ?></h3>
                </div>
            </div>
        </div>

        <!-- Total Clients Card (4th) -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-emerald-100 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Total Clients</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $total_clients; ?></h3>
                </div>
            </div>
        </div>

        <!-- Upcoming Events Card (5th) -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-rose-100 rounded-xl group-hover:bg-rose-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Upcoming Events</p>
                    <?php if (empty($upcoming_events)): ?>
                        <div class="text-xs text-base-content/50 mt-1">No upcoming events</div>
                    <?php else: ?>
                        <div class="space-y-2 mt-2">
                        <?php foreach (array_slice($upcoming_events, 0, 3) as $event): ?>
                            <div class="flex justify-between items-center text-xs">
                                <span class="truncate font-bold max-w-[120px]" title="<?php echo htmlspecialchars($event['title']); ?>">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </span>
                                <span class="opacity-70"><?php echo date('M d', strtotime($event['due_date'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Projects Queue Card (6th - Moved to Last) -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 group">
            <div class="card-body p-6">
                <div class="flex justify-between items-start">
                    <div class="p-3 bg-blue-100 rounded-xl group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-base-content/70 font-medium">Projects in Queue</p>
                    <h3 class="text-2xl font-black text-base-content mt-1"><?php echo $queue_count; ?></h3>
                </div>
            </div>
        </div>

    </div>

    <!-- Delayed Projects Section -->
    <?php if (!empty($overdue_details)): ?>
    <div class="card bg-base-100 shadow-xl mb-8 border border-red-200">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h2 class="card-title text-lg text-red-600 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    Delayed Projects
                </h2>
                <div class="badge badge-error text-white"><?php echo count($overdue_details); ?> Overdue</div>
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-xs font-bold text-base-content/70 uppercase">Project</th>
                            <th class="text-xs font-bold text-base-content/70 uppercase">Client</th>
                            <th class="text-xs font-bold text-base-content/70 uppercase">Deadline</th>
                            <th class="text-xs font-bold text-base-content/70 uppercase">Delay</th>
                            <th class="text-xs font-bold text-base-content/70 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_details as $p): 
                            $deadline = new DateTime($p['deadline']);
                            $now = new DateTime(); // Current time
                            $interval = $now->diff($deadline);
                            $days_overdue = $interval->days;
                        ?>
                        <tr class="hover bg-base-100/50">
                            <td class="font-bold text-base-content"><?php echo htmlspecialchars($p['name']); ?></td>
                            <td class="text-base-content/80"><?php echo htmlspecialchars($p['client_name']); ?></td>
                            <td class="text-sm font-bold text-red-500"><?php echo $deadline->format('Y-m-d'); ?></td>
                            <td>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-600 border border-red-200">
                                    <?php echo $days_overdue; ?> days late
                                </span>
                            </td>
                            <td>
                                <a href="projects/project_view.php?id=<?php echo $p['id']; ?>" class="btn btn-xs btn-outline btn-error hover:bg-red-50">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Projects Queue Section -->
    <div class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h2 class="card-title text-lg">Projects Queue</h2>
                <div class="badge badge-neutral"><?php echo count($queue_projects); ?> Waiting</div>
            </div>

            <?php if (empty($queue_projects)): ?>
                <div class="text-center py-8 text-base-content/50">
                    <p>No projects in the queue.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Date Added</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queue_projects as $p): ?>
                            <tr class="hover">
                                <td class="font-bold"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['client_name']); ?></td>
                                <td class="text-sm opacity-70"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                                <td>
                                    <a href="projects/project_view.php?id=<?php echo $p['id']; ?>" class="btn btn-xs btn-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
// --- Chart Data Fetching ---


// 3. Project Status
$status_sql = "SELECT status, COUNT(*) as count FROM projects GROUP BY status";
$status_res = db_fetch_all($status_sql);
$status_labels = [];
$status_counts = [];
foreach ($status_res as $row) {
    $status_labels[] = $row['status'];
    $status_counts[] = $row['count'];
}

// --- Urgent Follow-up Logic ---
// Clients with no contact in 30+ days (or never contacted)
$urgent_sql = "SELECT * FROM clients WHERE status = 'Active' AND (last_contacted IS NULL OR last_contacted < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) LIMIT 5";
$urgent_clients = db_fetch_all($urgent_sql);
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    
    <!-- Left Column: Charts (2/3 width) -->
    <div class="lg:col-span-2 space-y-8">
        

            <!-- Project Status -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-sm mb-2">Project Status</h2>
                    <div class="h-60">
                        <canvas id="projectsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Column: Urgent Actions & Promo (1/3 width) -->
    <div class="space-y-8">
        
        <!-- Urgent Follow-up Section (Red Glass) -->
        <div class="card bg-red-500/10 backdrop-blur-md border border-red-500/20 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-red-600 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    Action Required Today
                </h2>
                <p class="text-sm text-gray-600 mb-4">Hamza! These clients haven't heard from you in a while.</p>
                
                <?php if (empty($urgent_clients)): ?>
                    <div class="alert alert-success text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>All caught up! Great job.</span>
                    </div>
                <?php else: ?>
                    <div class="space-y-3" id="urgent-list">
                        <?php foreach ($urgent_clients as $client): ?>
                        <div class="bg-white p-3 rounded-lg shadow-sm border border-red-100 flex justify-between items-center group hover:shadow-md transition-all" id="urgent-item-<?php echo $client['id']; ?>">
                            <div>
                                <div class="font-bold text-gray-800"><?php echo htmlspecialchars($client['name']); ?></div>
                                <div class="text-xs text-red-400">
                                    <?php echo $client['last_contacted'] ? 'Last: ' . date('M d', strtotime($client['last_contacted'])) : 'Never contacted'; ?>
                                </div>
                            </div>
                            <div class="flex gap-1">
                                <a href="mailto:<?php echo $client['email']; ?>?subject=Checking in - <?php echo htmlspecialchars(get_user_name()); ?>&body=Hi <?php echo htmlspecialchars($client['name']); ?>,%0D%0A%0D%0AJust wanted to check in and see how things are going." class="btn btn-xs btn-error text-white">Email</a>
                                <button onclick="snoozeClient(<?php echo $client['id']; ?>)" class="btn btn-xs btn-ghost text-gray-400 hover:text-gray-600">Snooze</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <script>
                        // Init Snooze Logic
                        document.addEventListener('DOMContentLoaded', () => {
                            const snoozed = JSON.parse(localStorage.getItem('snoozed_clients') || '{}');
                            const now = new Date().getTime();
                            
                            // Cleanup expired snoozes (24h)
                            for (const id in snoozed) {
                                if (now > snoozed[id]) {
                                    delete snoozed[id];
                                } else {
                                    // Hide if valid snooze
                                    const el = document.getElementById('urgent-item-' + id);
                                    if(el) el.style.display = 'none';
                                }
                            }
                            localStorage.setItem('snoozed_clients', JSON.stringify(snoozed));
                            
                            // Check if all hidden
                            const list = document.getElementById('urgent-list');
                            if(list && list.querySelectorAll('div[id^="urgent-item-"]:not([style*="display: none"])').length === 0) {
                                list.innerHTML = '<div class="alert alert-success text-sm"><span>All caught up! Great job.</span></div>';
                            }
                        });

                        function snoozeClient(id) {
                            if(!confirm('Snooze this client for 24 hours?')) return;
                            
                            const snoozed = JSON.parse(localStorage.getItem('snoozed_clients') || '{}');
                            // Snooze for 24 hours
                            snoozed[id] = new Date().getTime() + (24 * 60 * 60 * 1000);
                            localStorage.setItem('snoozed_clients', JSON.stringify(snoozed));
                            
                            const el = document.getElementById('urgent-item-' + id);
                            if(el) {
                                el.style.display = 'none';
                                // Create toast
                                const toast = document.createElement('div');
                                toast.className = 'toast toast-end';
                                toast.innerHTML = '<div class="alert alert-info py-2 text-sm"><span>Snoozed for 24h</span></div>';
                                document.body.appendChild(toast);
                                setTimeout(() => toast.remove(), 2000);
                            }
                        }
                    </script>
                <?php endif; ?>
            </div>
        </div>

        <!-- Promotion Banner (Preserved) -->
        <?php if ($next_promo): ?>
        <div class="card bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-xl">
            <div class="card-body">
                <h2 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                    Upcoming Promotion
                </h2>
                <p class="text-lg font-bold"><?php echo htmlspecialchars($next_promo['title']); ?></p>
                <p class="opacity-90">Scheduled for: <?php echo date('M d, Y', strtotime($next_promo['scheduled_at'])); ?></p>
                <div class="card-actions justify-end">
                    <a href="promotions.php" class="btn btn-sm btn-white text-purple-600 border-none hover:bg-gray-100">Manage</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Chart Configs
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: '#6b7280' } }
    },
    scales: {
        y: { grid: { color: '#f3f4f6' }, ticks: { color: '#6b7280' } },
        x: { grid: { display: false }, ticks: { color: '#6b7280' } }
    }
};


// 3. Project Status
new Chart(document.getElementById('projectsChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($status_counts); ?>,
            backgroundColor: ['#3b82f6', '#10b981', '#ef4444', '#f59e0b'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true } }
        },
        cutout: '70%'
    }
});
</script>

<?php require_once 'footer.php'; ?>
