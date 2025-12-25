<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

// Handle Delete
if (isset($_POST['delete_team'])) {
    $team_id = (int)$_POST['delete_team'];
    // Unassign projects
    db_query("UPDATE projects SET team_id = NULL WHERE team_id = $team_id"); 
    // Remove members
    db_query("DELETE FROM team_members WHERE team_id = $team_id"); 
    // Delete team
    db_query("DELETE FROM teams WHERE id = $team_id"); 
    redirect('teams/index.php');
}

// Search Logic
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$where = "WHERE 1";
if ($search) {
    $where .= " AND (t.name LIKE '%$search%' OR t.description LIKE '%$search%')";
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 9; // Grid view, so 9 looks good (3x3)
$offset = ($page - 1) * $limit;

// Get Total Count
$total_teams = db_fetch_one("SELECT COUNT(*) as total FROM teams t $where")['total'];
$total_pages = ceil($total_teams / $limit);

$teams = db_fetch_all("
    SELECT t.*, u.name as leader_name, 
    (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count 
    FROM teams t 
    LEFT JOIN users u ON t.leader_id = u.id 
    $where
    ORDER BY t.name ASC 
    LIMIT $limit OFFSET $offset
");

if (isset($_GET['ajax_search'])) {
    if (empty($teams)) {
        echo '<div class="col-span-full text-center py-10">
                <div class="text-base-content/40 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </div>
                <h3 class="text-xl font-bold text-base-content/80">No Teams Found</h3>
              </div>';
    } else {
        foreach ($teams as $team) {
            echo '<div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all relative">
                    <div class="absolute top-4 left-4 z-10">
                        <input type="checkbox" value="' . $team['id'] . '" class="checkbox checkbox-sm bulk-checkbox" />
                    </div>
                    <div class="card-body">
                        <h2 class="card-title justify-between">
                            <a href="view.php?id=' . $team['id'] . '" class="link link-hover">' . htmlspecialchars($team['name']) . '</a>
                            <div class="badge badge-secondary">' . $team['member_count'] . ' Members</div>
                        </h2>
                        <p class="text-sm text-base-content/70 mb-4">' . htmlspecialchars($team['description']) . '</p>
                        
                        <div class="flex items-center gap-2 text-sm text-base-content/80 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            Leader: <span class="font-bold">' . htmlspecialchars($team['leader_name'] ?? 'None') . '</span>
                        </div>

                        <div class="card-actions justify-end items-center gap-2">
                             <form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this team?\');">
                                <input type="hidden" name="delete_team" value="' . $team['id'] . '">
                                <button class="btn btn-sm btn-ghost text-error btn-circle" title="Delete Team">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                            <a href="edit.php?id=' . $team['id'] . '" class="btn btn-sm btn-ghost text-primary btn-circle" title="Edit Team">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </a>
                            <a href="view.php?id=' . $team['id'] . '" class="btn btn-sm btn-ghost">View Details</a>
                        </div>
                    </div>
                </div>';
        }
    }
    exit;
}

$page_title = "Teams";
require_once '../header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <div class="flex items-center gap-4">
        <h1 class="text-3xl font-bold text-base-content">Teams</h1>
        <div class="form-control">
            <label class="label cursor-pointer gap-2">
                <input type="checkbox" id="selectAll" class="checkbox checkbox-sm" />
                <span class="label-text">Select All</span>
            </label>
        </div>
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <form method="GET" class="flex gap-2 w-full md:w-auto">
            <input type="text" name="search" placeholder="Search teams..." class="input input-bordered w-full md:w-64" value="<?php echo htmlspecialchars($search); ?>" />
            <button class="btn btn-ghost">Search</button>
        </form>
        <a href="create.php" class="btn btn-primary">Create Team</a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="team-grid">
    <?php foreach ($teams as $team): ?>
    <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all relative">
        <div class="absolute top-4 left-4 z-10">
            <input type="checkbox" value="<?php echo $team['id']; ?>" class="checkbox checkbox-sm bulk-checkbox" />
        </div>
        <div class="card-body">
            <h2 class="card-title justify-between">
                <a href="view.php?id=<?php echo $team['id']; ?>" class="link link-hover"><?php echo htmlspecialchars($team['name']); ?></a>
                <div class="badge badge-secondary"><?php echo $team['member_count']; ?> Members</div>



            </h2>
            <p class="text-sm text-base-content/70 mb-4"><?php echo htmlspecialchars($team['description']); ?></p>
            
            <div class="flex items-center gap-2 text-sm text-base-content/80 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                Leader: <span class="font-bold"><?php echo htmlspecialchars($team['leader_name'] ?? 'None'); ?></span>
            </div>

            <div class="card-actions justify-end items-center gap-2">
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this team?');">
                    <input type="hidden" name="delete_team" value="<?php echo $team['id']; ?>">
                    <button class="btn btn-sm btn-ghost text-error btn-circle" title="Delete Team">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </form>
                <a href="edit.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-ghost text-primary btn-circle" title="Edit Team">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                </a>
                <a href="view.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-ghost">View Details</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($teams)): ?>
    <div class="col-span-full text-center py-10">
        <div class="text-base-content/40 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        </div>
        <h3 class="text-xl font-bold text-base-content/80">No Teams Yet</h3>
        <p class="text-base-content/60">Create your first team to get started.</p>
    </div>
    <?php endif; ?>
</div>

    <!-- Pagination UI -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-8 mb-8 gap-2">
        <?php 
            if (!function_exists('build_url')) {
                function build_url($page, $search) {
                    $query = ['page' => $page];
                    if ($search) $query['search'] = $search;
                    return '?' . http_build_query($query);
                }
            }
        ?>
        <!-- Previous Button -->
        <a href="<?php echo $page > 1 ? build_url($page - 1, $search) : '#'; ?>" 
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
                <a href="<?php echo build_url($i, $search); ?>" 
                   class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-white border-base-300 hover:bg-base-200 text-base-content">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <!-- Next Button -->
        <a href="<?php echo $page < $total_pages ? build_url($page + 1, $search) : '#'; ?>" 
           class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-white border-base-300 hover:bg-base-200 text-base-content <?php echo $page >= $total_pages ? 'btn-disabled opacity-50' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </a>
    </div>
    <?php endif; ?>


<script>
const searchInput = document.querySelector('input[name="search"]');
const teamGrid = document.getElementById('team-grid');

if(searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value;
        fetch('index.php?ajax_search=1&search=' + encodeURIComponent(searchTerm))
            .then(response => response.text())
            .then(html => {
                teamGrid.innerHTML = html;
            });
    });
}
</script>

<!-- Bulk Action Bar -->
<div id="bulkActionBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-base-100 shadow-2xl rounded-2xl p-4 border border-primary/20 hidden items-center gap-6 z-50 animate-bounce-in">
    <div class="flex items-center gap-2 px-3 py-1 bg-primary/10 rounded-lg text-primary font-bold">
        <span id="selectedCount">0</span> selected
    </div>
    
    <form action="team_bulk.php" method="POST" class="flex items-center gap-2">
        <input type="hidden" name="ids" id="bulkIds" />
        
        <select name="action" class="select select-bordered select-sm min-w-[150px]" required>
            <option value="">Bulk Action...</option>
            <option value="delete">Delete Selected Teams</option>
        </select>
        
        <button type="submit" class="btn btn-primary btn-sm px-6" onclick="return confirmBulkDelete()">
            Apply
        </button>
    </form>
    
    <button onclick="document.getElementById('selectAll').click()" class="btn btn-ghost btn-circle btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
</div>

<?php require_once '../footer.php'; ?>
