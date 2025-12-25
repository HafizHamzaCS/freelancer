<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$page_title = "Tasks";
require_once '../header.php';

// Filter Logic
$status = isset($_GET['status']) ? escape($_GET['status']) : '';
$priority = isset($_GET['priority']) ? escape($_GET['priority']) : '';
$assigned_to = isset($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : '';
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : '';

$where = "WHERE 1";
if ($status) $where .= " AND t.status = '$status'";
if ($priority) $where .= " AND t.priority = '$priority'";
if ($assigned_to) $where .= " AND t.assigned_to = $assigned_to";
if ($project_id) $where .= " AND t.project_id = $project_id";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Fetch Tasks
$sql = "SELECT t.*, p.name as project_name, u.name as assigned_name 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        LEFT JOIN users u ON t.assigned_to = u.id 
        $where 
        ORDER BY t.due_date ASC, t.priority DESC 
        LIMIT $limit OFFSET $offset";
$tasks = db_fetch_all($sql);

// Count Total
$count_sql = "SELECT COUNT(*) as total FROM tasks t $where";
$total_tasks = db_fetch_one($count_sql)['total'];
$total_pages = ceil($total_tasks / $limit);

// Fetch Users & Projects for Filters
$users = db_fetch_all("SELECT id, name FROM users ORDER BY name");
$projects = db_fetch_all("SELECT id, name FROM projects WHERE status != 'Completed' ORDER BY name");
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-3xl font-bold text-base-content">Tasks</h1>
    <a href="create_task.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        New Task
    </a>
</div>

<!-- Filters -->
<div class="card bg-base-100 shadow-sm mb-6">
    <div class="card-body p-4">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="form-control w-full sm:w-auto">
                <label class="label"><span class="label-text">Status</span></label>
                <select name="status" class="select select-bordered select-sm">
                    <option value="">All Statuses</option>
                    <?php 
                    $statuses = ['Todo', 'In Progress', 'On Hold', 'Blocked', 'Done'];
                    foreach($statuses as $s) echo "<option value='$s' " . ($status == $s ? 'selected' : '') . ">$s</option>";
                    ?>
                </select>
            </div>
            <div class="form-control w-full sm:w-auto">
                <label class="label"><span class="label-text">Priority</span></label>
                <select name="priority" class="select select-bordered select-sm">
                    <option value="">All Priorities</option>
                    <option value="High" <?php if($priority=='High') echo 'selected'; ?>>High</option>
                    <option value="Medium" <?php if($priority=='Medium') echo 'selected'; ?>>Medium</option>
                    <option value="Low" <?php if($priority=='Low') echo 'selected'; ?>>Low</option>
                </select>
            </div>
            <div class="form-control w-full sm:w-auto">
                <label class="label"><span class="label-text">Assigned To</span></label>
                <select name="assigned_to" class="select select-bordered select-sm">
                    <option value="">Anyone</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php if($assigned_to==$u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-control w-full sm:w-auto">
                <label class="label"><span class="label-text">Project</span></label>
                <select name="project_id" class="select select-bordered select-sm">
                    <option value="">Any Project</option>
                    <?php foreach($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php if($project_id==$p['id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-sm btn-ghost">Filter</button>
            <?php if($status || $priority || $assigned_to || $project_id): ?>
                <a href="index.php" class="btn btn-sm btn-link text-error no-underline">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Task List -->
<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="7" class="text-center py-8 text-base-content/50">No tasks found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                        <tr class="hover">
                            <td>
                                <div class="font-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                <?php if($task['description']): ?>
                                    <div class="text-xs opacity-50 truncate max-w-xs"><?php echo htmlspecialchars($task['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($task['project_name']): ?>
                                    <a href="../projects/project_view.php?id=<?php echo $task['project_id']; ?>" class="link link-hover text-sm">
                                        <?php echo htmlspecialchars($task['project_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs opacity-50">No Project</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($task['assigned_name']): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar placeholder">
                                            <div class="bg-neutral-focus text-neutral-content rounded-full w-6 h-6 text-xs">
                                                <span><?php echo substr($task['assigned_name'], 0, 1); ?></span>
                                            </div>
                                        </div>
                                        <span class="text-sm"><?php echo htmlspecialchars($task['assigned_name']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm opacity-50">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $badge_class = 'badge-ghost';
                                if($task['priority'] == 'High') $badge_class = 'badge-error';
                                if($task['priority'] == 'Medium') $badge_class = 'badge-warning';
                                if($task['priority'] == 'Low') $badge_class = 'badge-info';
                                ?>
                                <span class="badge <?php echo $badge_class; ?> badge-sm"><?php echo $task['priority']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-outline badge-sm"><?php echo $task['status']; ?></span>
                            </td>
                            <td>
                                <?php 
                                $due = $task['due_date'] ? date('M d', strtotime($task['due_date'])) : '-';
                                $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time() && $task['status'] != 'Done';
                                ?>
                                <span class="<?php echo $is_overdue ? 'text-error font-bold' : ''; ?>"><?php echo $due; ?></span>
                            </td>
                            <td>
                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-ghost btn-xs" title="Edit Task">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                <button onclick="delete_task(<?php echo $task['id']; ?>)" class="btn btn-ghost btn-xs text-error" title="Delete Task">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center p-4 gap-2 border-t border-base-200">
             <?php 
                function build_url($page) {
                    $query = $_GET;
                    $query['page'] = $page;
                    return '?' . http_build_query($query);
                }
            ?>
            <a href="<?php echo $page > 1 ? build_url($page - 1) : '#'; ?>" class="btn btn-sm <?php echo $page <= 1 ? 'btn-disabled' : ''; ?>">«</a>
            <span class="btn btn-sm no-animation bg-base-100 border-none">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <a href="<?php echo $page < $total_pages ? build_url($page + 1) : '#'; ?>" class="btn btn-sm <?php echo $page >= $total_pages ? 'btn-disabled' : ''; ?>">»</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<dialog id="delete_modal" class="modal">
  <div class="modal-box">
    <h3 class="font-bold text-lg">Confirm Deletion</h3>
    <p class="py-4">Are you sure you want to delete this task? This action cannot be undone.</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Cancel</button>
      </form>
      <a id="confirm_delete_btn" href="#" class="btn btn-error">Delete</a>
    </div>
  </div>
</dialog>

<script>
function delete_task(id) {
    const modal = document.getElementById('delete_modal');
    const deleteBtn = document.getElementById('confirm_delete_btn');
    deleteBtn.href = 'delete_task.php?id=' + id;
    modal.showModal();
}
</script>

<?php require_once '../footer.php'; ?>
