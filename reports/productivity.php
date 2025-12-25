<?php
require_once '../config.php';
require_once '../functions.php';

// Access Control
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    redirect('projects/project_list.php');
}

$page_title = "Team Productivity";
require_once '../header.php';

// Fetch Team Productivity Stats
// We assume 'project_members' table exists as used in project_edit.php
// If it doesn't exist, this might fail, but since other files use it, we assume it's there.

$sql = "SELECT u.id, u.name, u.role, u.email,
        COUNT(pm.project_id) as total_projects,
        SUM(CASE WHEN p.status = 'Completed' THEN 1 ELSE 0 END) as completed_projects,
        SUM(CASE WHEN p.status = 'In Progress' THEN 1 ELSE 0 END) as active_projects,
        MAX(p.deadline) as last_deadline
        FROM users u
        LEFT JOIN project_members pm ON u.id = pm.user_id
        LEFT JOIN projects p ON pm.project_id = p.id
        WHERE u.role != 'client'
        GROUP BY u.id
        ORDER BY completed_projects DESC";

$members = db_fetch_all($sql);
?>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title mb-6">Team Performance Overview</h2>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th>Projects Assigned</th>
                        <th>Active Workload</th>
                        <th>Completed</th>
                        <th>Efficiency</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="6" class="text-center py-8 opacity-50">No team members found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): 
                            $efficiency = $m['total_projects'] > 0 ? round(($m['completed_projects'] / $m['total_projects']) * 100) : 0;
                            
                            // Avatar logic
                            $initials = strtoupper(substr($m['name'], 0, 2));
                        ?>
                        <tr class="hover">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="avatar placeholder">
                                        <div class="bg-neutral text-neutral-content rounded-full w-10">
                                            <span><?php echo $initials; ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-bold"><?php echo htmlspecialchars($m['name']); ?></div>
                                        <div class="text-xs opacity-50"><?php echo htmlspecialchars($m['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="badge badge-ghost badge-sm"><?php echo ucfirst($m['role']); ?></div>
                            </td>
                            <td class="text-center font-bold text-lg">
                                <?php echo $m['total_projects']; ?>
                            </td>
                            <td>
                                <?php if ($m['active_projects'] > 0): ?>
                                    <div class="badge badge-info gap-2">
                                        <?php echo $m['active_projects']; ?> Active
                                    </div>
                                <?php else: ?>
                                    <span class="opacity-30">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($m['completed_projects'] > 0): ?>
                                    <div class="text-success font-bold flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <?php echo $m['completed_projects']; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="opacity-30">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <progress class="progress w-20 <?php echo $efficiency > 75 ? 'progress-success' : 'progress-warning'; ?>" value="<?php echo $efficiency; ?>" max="100"></progress>
                                    <span class="text-xs font-bold"><?php echo $efficiency; ?>%</span>
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
