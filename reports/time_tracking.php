<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role('admin');

$page_title = "Time Tracking";
require_once '../header.php';
?>

<!-- Report Tabs -->
<div role="tablist" class="tabs tabs-lifted mb-8">
    <a role="tab" class="tab" href="index.php">Overview</a>
    <a role="tab" class="tab" href="productivity.php">Productivity</a>
    <a role="tab" class="tab" href="project_performance.php">Performance</a>
    <a role="tab" class="tab tab-active">Time Logs</a>
    <a role="tab" class="tab" href="gantt.php">Gantt</a>
</div>
<?php

// Fetch Time Entries
// Assuming 'time_entries' table exists as per config schema
$sql = "SELECT t.*, p.name as project_name 
        FROM time_entries t 
        LEFT JOIN projects p ON t.project_id = p.id 
        ORDER BY t.start_time DESC LIMIT 50";
$entries = db_fetch_all($sql);

$total_hours = 0;
foreach($entries as $e) {
    if($e['end_time']) {
        $start = strtotime($e['start_time']);
        $end = strtotime($e['end_time']);
        $total_hours += ($end - $start) / 3600;
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <div class="text-sm breadcrumbs opacity-50">
            <ul>
                <li><a href="index.php">Reports</a></li>
                <li>Time Tracking</li>
            </ul>
        </div>
        <h1 class="text-3xl font-bold mt-2">Time Logs</h1>
    </div>
    <div class="stat-value text-xl">
        Total Logged: <span class="text-primary"><?php echo number_format($total_hours, 1); ?> hrs</span>
    </div>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Duration</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($entries)): ?>
                        <tr><td colspan="5" class="text-center py-8 opacity-50">No time entries found.</td></tr>
                    <?php else: ?>
                        <?php foreach($entries as $entry): 
                            $duration = '-';
                            if($entry['end_time']) {
                                $diff = strtotime($entry['end_time']) - strtotime($entry['start_time']);
                                $h = floor($diff / 3600);
                                $m = floor(($diff % 3600) / 60);
                                $duration = "{$h}h {$m}m";
                            }
                        ?>
                        <tr class="hover">
                            <td class="font-bold opacity-70"><?php echo date('M d, Y', strtotime($entry['start_time'])); ?></td>
                            <td class="font-bold text-primary"><?php echo htmlspecialchars($entry['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['description']); ?></td>
                            <td><span class="badge badge-ghost"><?php echo $duration; ?></span></td>
                            <td class="text-xs opacity-50">
                                <?php echo date('H:i', strtotime($entry['start_time'])); ?> - 
                                <?php echo $entry['end_time'] ? date('H:i', strtotime($entry['end_time'])) : 'Active'; ?>
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
