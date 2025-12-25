<?php
require_once '../config.php';
require_once '../functions.php';

$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$source_filter = isset($_GET['source']) ? escape($_GET['source']) : '';

$where = "WHERE 1";
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    $client_id = 0; // Default to 0 (no projects)

    // If logged in as a 'user' (with client role), find the matching client record by email
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'user') {
        $email = $_SESSION['user_email'];
        $client_record = db_fetch_one("SELECT id FROM clients WHERE email = '$email'");
        if ($client_record) {
            $client_id = $client_record['id'];
        }
    } 
    // If logged in as a 'client' (direct client login), use their ID
    else {
        $client_id = $_SESSION['user_id'];
    }

    $where .= " AND p.client_id = $client_id";
} elseif (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    // If not admin/manager and not client, it's a team member (member, developer, viewer, etc.)
    $user_id = $_SESSION['user_id'];
    $where .= " AND EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = $user_id)";
}

if ($search) {
    $where .= " AND (p.name LIKE '%$search%' OR c.name LIKE '%$search%')";
}
if ($source_filter && $source_filter !== 'All') {
    $where .= " AND p.source = '$source_filter'";
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Get Total Count
$count_sql = "SELECT COUNT(*) as total FROM projects p 
              LEFT JOIN clients c ON p.client_id = c.id 
              $where";
$total_projects = db_fetch_one($count_sql)['total'];
$total_pages = ceil($total_projects / $limit);

$sql = "SELECT p.*, c.name as client_name,
        (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
            FROM project_members pm 
            JOIN users u ON pm.user_id = u.id 
            WHERE pm.project_id = p.id AND u.role = 'member') as team_members
        FROM projects p 
        LEFT JOIN clients c ON p.client_id = c.id 
        $where ORDER BY p.deadline ASC LIMIT $limit OFFSET $offset";
$projects = db_fetch_all($sql);

// Helper for Source Badge Color
function get_source_badge_class($source) {
    return match(strtolower($source)) {
        'fiverr' => 'badge-warning',
        'upwork' => 'badge-success',
        'linkedin' => 'badge-info',
        'whatsapp' => 'badge-accent',
        default => 'badge-ghost'
    };
}

if (isset($_GET['ajax_search'])) {
    if (empty($projects)) {
        echo '<tr><td colspan="6" class="text-center text-base-content/60">No projects found.</td></tr>';
    } else {
        foreach ($projects as $project) {
            $deadline = new DateTime($project['deadline']);
            $today = new DateTime();
            $is_overdue = $deadline < $today && $project['status'] != 'Completed';
            
            $status_badge = match($project['status']) {
                'Completed' => 'badge-success',
                'In Progress' => 'badge-info',
                'On Hold' => 'badge-warning',
                default => 'badge-ghost'
            };

            $source_badge = get_source_badge_class($project['source'] ?? 'Direct');
            $net_amount = calculate_project_net($project['budget'], $project['source'] ?? 'Direct');
            $tax_rate = get_tax_rate($project['source'] ?? 'Direct');

            // Team Members Parsing
            $members_list = $project['team_members'] ? explode(', ', $project['team_members']) : [];

            echo '<tr class="hover">';
            echo '<td>
                    <input type="checkbox" value="' . $project['id'] . '" class="checkbox checkbox-sm bulk-checkbox" />
                  </td>';
            echo '<td>
                    <div class="font-bold"><a href="' . get_project_url($project) . '" class="link link-hover">' . htmlspecialchars($project['name']) . '</a></div>
                    <div class="badge ' . $source_badge . ' badge-xs mt-1">' . htmlspecialchars($project['source'] ?? 'Direct') . '</div>
                  </td>';
            echo '<td><a href="../clients/client_view.php?id=' . $project['client_id'] . '" class="link link-hover">' . htmlspecialchars($project['client_name']) . '</a></td>';
            echo '<td><div class="badge ' . $status_badge . '">' . $project['status'] . '</div></td>';
            echo '<td><span class="' . ($is_overdue ? 'text-error font-bold' : '') . '">' . $project['deadline'] . ($is_overdue ? ' <span class="tooltip" data-tip="Overdue!">⚠️</span>' : '') . '</span></td>';
            
            // Removed Budget Column
            
            
            // Team Column
            echo '<td>';
            if (!empty($members_list)) {
                echo '<div class="flex flex-col gap-1 max-h-16 overflow-y-auto custom-scrollbar">';
                foreach ($members_list as $member) {
                    echo '<div class="flex items-center gap-2">
                            <div class="w-1.5 h-1.5 rounded-full bg-base-content/50"></div>
                            <span class="text-sm">' . htmlspecialchars($member) . '</span>
                          </div>';
                }
                echo '</div>';
            } else {
                echo '<span class="text-xs opacity-50">-</span>';
            }
            echo '</td>';

            echo '<td>
                    <div class="flex items-center justify-end gap-2">';
            
            // Actions for Admin/Team only
            if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']) {
                echo '<a href="' . get_project_url($project) . '" class="btn btn-ghost btn-xs" title="View Project">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      </a>
                      <a href="project_edit.php?id=' . $project['id'] . '" class="btn btn-ghost btn-xs" title="Edit Project">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                      </a>
                      <button onclick="openDeleteModal(' . $project['id'] . ')" class="btn btn-ghost btn-xs text-error" title="Delete Project">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>';
            } else {
                echo '<a href="' . get_project_url($project) . '" class="btn btn-ghost btn-xs" title="View Project">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      </a>';
            }
            
            echo '</div></td>';
            echo '</tr>';
        }
    }
    exit;
}

require_once '../header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold">Projects</h2>
    <div class="flex gap-2">
        <?php if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']): ?>
        <a href="project_kanban.php" class="btn btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" /></svg>
            Kanban Board
        </a>
        <a href="project_add.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            New Project
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Tabs -->
<?php if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']): ?>
<div class="tabs tabs-boxed bg-base-100 mb-6 p-2 shadow-sm overflow-x-auto flex-nowrap">
    <?php 
    $sources = ['All', 'Fiverr', 'Upwork', 'LinkedIn', 'WhatsApp', 'Direct'];
    foreach ($sources as $src): 
        $active = ($source_filter == $src || ($source_filter == '' && $src == 'All')) ? 'tab-active bg-primary text-white' : '';
        $url = "project_list.php?source=" . ($src == 'All' ? '' : $src);
    ?>
        <a href="<?php echo $url; ?>" 
           hx-get="<?php echo $url; ?>" 
           hx-target="#main-content" 
           hx-select="#main-content" 
           hx-push-url="true"
           class="tab <?php echo $active; ?> whitespace-nowrap flex-shrink-0"><?php echo $src; ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <div class="form-control mb-4">
            <form method="GET" class="flex gap-2" hx-get="project_list.php" hx-target="#project-table-body" hx-select="#project-table-body" hx-trigger="submit, keyup delay:500ms from:input[name='search']">
                <input type="hidden" name="ajax_search" value="1">
                <input type="hidden" name="source" value="<?php echo htmlspecialchars($source_filter); ?>">
                <div class="relative w-full max-w-xs">
                    <input type="text" name="search" placeholder="Search projects..." class="input input-bordered w-full" value="<?php echo htmlspecialchars($search); ?>" />
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none htmx-indicator">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                    </div>
                </div>
                <button class="btn btn-primary">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="checkbox checkbox-sm" /></th>
                        <th>Project Name</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Team</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="project-table-body">
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="6" class="text-center text-base-content/60">No projects found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                        <tr class="hover">
                            <td>
                                <input type="checkbox" name="ids[]" value="<?php echo $project['id']; ?>" class="checkbox checkbox-sm bulk-checkbox" />
                            </td>
                            <td>
                                <div class="font-bold"><a href="<?php echo get_project_url($project); ?>" class="link link-hover"><?php echo htmlspecialchars($project['name']); ?></a></div>
                                <div class="badge <?php echo get_source_badge_class($project['source'] ?? 'Direct'); ?> badge-xs mt-1">
                                    <?php echo htmlspecialchars($project['source'] ?? 'Direct'); ?>
                                </div>
                            </td>
                            <td>
                                <a href="../clients/client_view.php?id=<?php echo $project['client_id']; ?>" class="link link-hover">
                                    <?php echo htmlspecialchars($project['client_name']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="badge <?php 
                                    echo match($project['status']) {
                                        'Completed' => 'badge-success',
                                        'In Progress' => 'badge-info',
                                        'On Hold' => 'badge-warning',
                                        default => 'badge-ghost'
                                    };
                                ?> whitespace-nowrap">
                                    <?php echo $project['status']; ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $deadline = new DateTime($project['deadline']);
                                    $today = new DateTime();
                                    $is_overdue = $deadline < $today && $project['status'] != 'Completed';
                                ?>
                                <span class="<?php echo $is_overdue ? 'text-error font-bold' : ''; ?>">
                                    <?php echo $project['deadline']; ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="tooltip" data-tip="Overdue!">⚠️</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            
                            <!-- Removed Budget Column -->
                            
                            <td>
                                <?php 
                                    $members_list = $project['team_members'] ? explode(', ', $project['team_members']) : [];
                                    if (!empty($members_list)): 
                                ?>
                                    <div class="flex flex-col gap-1 max-h-16 overflow-y-auto custom-scrollbar">
                                        <?php foreach ($members_list as $member): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-1.5 h-1.5 rounded-full bg-base-content/50"></div>
                                                <span class="text-sm"><?php echo htmlspecialchars($member); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs opacity-50">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                <a href="<?php echo get_project_url($project); ?>" class="btn btn-ghost btn-xs" title="View Project">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </a>
                                <?php if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']): ?>
                                <a href="project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-ghost btn-xs" title="Edit Project">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                <button onclick="openDeleteModal(<?php echo $project['id']; ?>)" class="btn btn-ghost btn-xs text-error" title="Delete Project">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination UI -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-6 gap-2">
            <?php 
                // Query Params Helper
                $params = [];
                if ($search) $params['search'] = $search;
                if ($source_filter && $source_filter !== 'All') $params['source'] = $source_filter;
                
                function build_url($page, $params) {
                    $params['page'] = $page;
                    return '?' . http_build_query($params);
                }
            ?>
            
            <!-- Previous Button -->
            <a href="<?php echo $page > 1 ? build_url($page - 1, $params) : '#'; ?>" 
               class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-white border-base-300 hover:bg-base-200 text-base-content <?php echo $page <= 1 ? 'btn-disabled opacity-50' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>

            <!-- Page Numbers -->
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <button class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-primary text-primary-content border-primary hover:bg-primary/90 pointer-events-none">
                        <?php echo $i; ?>
                    </button>
                <?php else: ?>
                    <a href="<?php echo build_url($i, $params); ?>" 
                       class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-white border-base-300 hover:bg-base-200 text-base-content">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Next Button -->
            <a href="<?php echo $page < $total_pages ? build_url($page + 1, $params) : '#'; ?>" 
               class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-white border-base-300 hover:bg-base-200 text-base-content <?php echo $page >= $total_pages ? 'btn-disabled opacity-50' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<dialog id="delete_modal" class="modal">
  <div class="modal-box">
    <h3 class="font-bold text-lg">Confirm Deletion</h3>
    <p class="py-4">Are you sure you want to delete this project? This action cannot be undone.</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Cancel</button>
      </form>
      <a id="confirm_delete_btn" href="#" class="btn btn-error">Delete</a>
    </div>
  </div>
</dialog>

<script>
function openDeleteModal(projectId) {
    const modal = document.getElementById('delete_modal');
    const deleteBtn = document.getElementById('confirm_delete_btn');
    deleteBtn.href = 'project_delete.php?id=' + projectId;
    modal.showModal();
}
</script>

<!-- Bulk Action Bar -->
<div id="bulkActionBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-base-100 shadow-2xl rounded-2xl p-4 border border-primary/20 hidden items-center gap-6 z-50 animate-bounce-in">
    <div class="flex items-center gap-2 px-3 py-1 bg-primary/10 rounded-lg text-primary font-bold">
        <span id="selectedCount">0</span> selected
    </div>
    
    <form action="project_bulk.php" method="POST" class="flex items-center gap-2">
        <input type="hidden" name="ids" id="bulkIds" />
        
        <select name="action" class="select select-bordered select-sm min-w-[150px]" required>
            <option value="">Bulk Action...</option>
            <option value="Completed">Set Completed</option>
            <option value="In Progress">Set In Progress</option>
            <option value="On Hold">Set On Hold</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <button type="submit" class="btn btn-primary btn-sm px-6" onclick="return document.querySelector('select[name=action]').value === 'delete' ? confirmBulkDelete() : true">
            Apply
        </button>
    </form>
    
    <button onclick="document.getElementById('selectAll').click()" class="btn btn-ghost btn-circle btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
</div>

<?php require_once '../footer.php'; ?>
