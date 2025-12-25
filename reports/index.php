<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role('admin');

$page_title = "Reports & Analytics";
require_once '../header.php';

// Quick Stats for Cards
$total_projects = db_fetch_one("SELECT COUNT(*) as c FROM projects")['c'];
$total_income = db_fetch_one("SELECT SUM(amount) as s FROM invoices WHERE status='Paid'");
$total_tasks = db_fetch_one("SELECT COUNT(*) as c FROM tasks")['c'];
$active_clients = db_fetch_one("SELECT COUNT(*) as c FROM clients WHERE status='Active'")['c'];
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-secondary">Analytics Hub</h1>
    <p class="opacity-50">Insights into your agency's performance.</p>
</div>

<!-- Quick Stats Row -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat bg-base-100 shadow-xl rounded-box">
        <div class="stat-figure text-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        </div>
        <div class="stat-title">Total Projects</div>
        <div class="stat-value text-primary"><?php echo $total_projects; ?></div>
        <div class="stat-desc">Lifetime</div>
    </div>
    
    <div class="stat bg-base-100 shadow-xl rounded-box">
        <div class="stat-figure text-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="stat-title">Revenue</div>
        <div class="stat-value text-secondary">$<?php echo number_format($total_income['s'] ?? 0); ?></div>
        <div class="stat-desc">Paid Invoices</div>
    </div>

    <div class="stat bg-base-100 shadow-xl rounded-box">
        <div class="stat-figure text-accent">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
        </div>
        <div class="stat-title">Tasks</div>
        <div class="stat-value"><?php echo $total_tasks; ?></div>
        <div class="stat-desc">Across all projects</div>
    </div>

    <div class="stat bg-base-100 shadow-xl rounded-box">
        <div class="stat-title">Active Clients</div>
        <div class="stat-value"><?php echo $active_clients; ?></div>
        <div class="stat-desc">Currently engaged</div>
    </div>
</div>

<!-- Report Navigation Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    
    <!-- Productivity -->
    <a href="productivity.php" class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all group border border-transparent hover:border-primary/20">
        <div class="card-body items-center text-center">
            <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </div>
            <h2 class="card-title">Team Productivity</h2>
            <p class="text-sm opacity-60">Task completion, efficiency stats per member.</p>
        </div>
    </a>

    <!-- Project Performance -->
    <a href="project_performance.php" class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all group border border-transparent hover:border-secondary/20">
        <div class="card-body items-center text-center">
            <div class="w-16 h-16 rounded-full bg-secondary/10 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h2 class="card-title">Project Performance</h2>
            <p class="text-sm opacity-60">On-time rates, budget health, status breakdowns.</p>
        </div>
    </a>

    <!-- Time Tracking -->
    <a href="time_tracking.php" class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all group border border-transparent hover:border-accent/20">
        <div class="card-body items-center text-center">
            <div class="w-16 h-16 rounded-full bg-accent/10 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h2 class="card-title">Time Tracking</h2>
            <p class="text-sm opacity-60">Billable hours logs, time entries analysis.</p>
        </div>
    </a>

    <!-- Gantt Chart -->
    <a href="gantt.php" class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all group border border-transparent hover:border-info/20">
        <div class="card-body items-center text-center">
            <div class="w-16 h-16 rounded-full bg-info/10 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </div>
            <h2 class="card-title">Gantt Timelines</h2>
            <p class="text-sm opacity-60">Visual project roadmap and schedules.</p>
        </div>
    </a>

</div>

<?php require_once '../footer.php'; ?>
