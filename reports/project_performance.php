<?php
require_once '../config.php';
require_once '../functions.php';

// Access Control
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    redirect('projects/project_list.php');
}

$page_title = "Project Performance";
require_once '../header.php';
?>

<!-- Report Tabs -->
<div role="tablist" class="tabs tabs-lifted mb-8">
    <a role="tab" class="tab" href="index.php">Overview</a>
    <a role="tab" class="tab" href="productivity.php">Productivity</a>
    <a role="tab" class="tab tab-active">Performance</a>
    <a role="tab" class="tab" href="time_tracking.php">Time Logs</a>
    <a role="tab" class="tab" href="gantt.php">Gantt</a>
</div>
<?php

// Fetch Projects with Task Stats
$sql = "SELECT p.*, c.name as client_name,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'Done') as completed_tasks
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        WHERE p.status != 'Archived'
        ORDER BY p.created_at DESC";

$projects = db_fetch_all($sql);
?>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title mb-6">Active Projects Health Check</h2>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Budget</th>
                        <th class="w-1/3">Progress (Tasks)</th>
                        <th>Deadline Risk</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="6" class="text-center py-8 opacity-50">No active projects found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $p): 
                            // Calculations
                            $percent = $p['total_tasks'] > 0 ? round(($p['completed_tasks'] / $p['total_tasks']) * 100) : 0;
                            
                            $deadline = new DateTime($p['deadline']);
                            $today = new DateTime();
                            $interval = $today->diff($deadline);
                            $days_left = (int)$interval->format('%r%a'); // Signed days
                            
                            $risk_text = "On Track";
                            $risk_class = "text-success";
                            
                            if ($days_left < 0 && $p['status'] != 'Completed') {
                                $risk_text = "Overdue";
                                $risk_class = "text-error font-bold";
                            } elseif ($days_left < 3 && $p['status'] != 'Completed') {
                                $risk_text = "Critical";
                                $risk_class = "text-warning font-bold";
                            }
                        ?>
                        <tr class="hover">
                            <td>
                                <div class="font-bold text-lg"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="text-xs opacity-50"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                            </td>
                            <td>
                                <div class="font-medium"><?php echo htmlspecialchars($p['client_name']); ?></div>
                            </td>
                            <td class="font-mono">
                                <?php echo format_money($p['budget']); ?>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <progress class="progress progress-primary w-full h-3" value="<?php echo $percent; ?>" max="100"></progress>
                                    <span class="font-bold text-sm"><?php echo $percent; ?>%</span>
                                </div>
                                <div class="text-xs opacity-50 mt-1">
                                    <?php echo $p['completed_tasks']; ?>/<?php echo $p['total_tasks']; ?> tasks
                                </div>
                            </td>
                            <td>
                                <div class="<?php echo $risk_class; ?>">
                                    <?php echo $risk_text; ?>
                                </div>
                                <div class="text-xs opacity-50">
                                    <?php 
                                        if ($days_left < 0) echo abs($days_left) . " days ago";
                                        else echo $days_left . " days left";
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div class="badge <?php 
                                    echo match($p['status']) {
                                        'Completed' => 'badge-success',
                                        'In Progress' => 'badge-info',
                                        'Pending' => 'badge-ghost',
                                        'On Hold' => 'badge-warning',
                                        default => 'badge-ghost'
                                    };
                                ?>">
                                    <?php echo $p['status']; ?>
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

<?php require_once '../footer.php'; ?>
