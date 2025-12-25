<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$where = "WHERE 1";
if ($search) {
    $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Get Total Count
$count_sql = "SELECT COUNT(*) as total FROM clients $where";
$total_clients = db_fetch_one($count_sql)['total'];
$total_pages = ceil($total_clients / $limit);

$sql = "SELECT c.*, 
        (SELECT SUM(amount) FROM invoices WHERE client_id = c.id AND status = 'Paid') as total_earnings 
        FROM clients c $where ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset";
$clients = db_fetch_all($sql);

if (isset($_GET['ajax_search'])) {
    if (empty($clients)) {
        echo '<tr><td colspan="5" class="text-center text-base-content/60">No clients found.</td></tr>';
    } else {
        foreach ($clients as $client) {
            $score = 50; 
            if ($client['status'] == 'Active') $score += 20;
            if ($client['total_earnings'] > 1000) $score += 10;
            if ($client['total_earnings'] > 5000) $score += 20;
            
            $score_color = 'text-error';
            if ($score > 70) $score_color = 'text-success';
            elseif ($score > 50) $score_color = 'text-warning';

            echo '<tr class="hover">';
            echo '<td>
                    <input type="checkbox" value="' . $client['id'] . '" class="checkbox checkbox-sm bulk-checkbox" />
                  </td>';
            echo '<td>
                    <div class="flex items-center space-x-3">
                        <div class="avatar placeholder">
                            <div class="bg-neutral-focus text-neutral-content rounded-full w-12">
                                <span>' . strtoupper(substr($client['name'], 0, 2)) . '</span>
                            </div>
                        </div>
                        <div>
                            <div class="font-bold">' . htmlspecialchars($client['name']) . 
                                ($client['total_earnings'] > 5000 ? '<span class="badge badge-warning badge-xs ml-1" title="High Value Client">VIP</span>' : '') .
                            '</div>
                            <div class="text-sm opacity-50 text-base-content/60">ID: #' . $client['id'] . '</div>
                        </div>
                    </div>
                </td>';
            echo '<td>
                    <div>' . htmlspecialchars($client['email']) . '</div>
                    <div class="text-sm opacity-50 text-base-content/60">' . htmlspecialchars($client['phone']) . '</div>
                </td>';
            echo '<td>
                    <div class="radial-progress ' . $score_color . ' text-xs" style="--value:' . $score . '; --size:2rem;">' . $score . '</div>
                </td>';
            echo '<td>
                    <div class="badge ' . ($client['status'] == 'Active' ? 'badge-success' : 'badge-ghost') . '">' . $client['status'] . '</div>
                </td>';
            echo '<td>
                    <a href="' . get_client_url($client) . '" class="btn btn-ghost btn-xs" title="View Client">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </a>
                    <a href="client_edit.php?id=' . $client['id'] . '" class="btn btn-ghost btn-xs" title="Edit Client">
                         <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </a>
                    <button onclick="openDeleteModal(' . $client['id'] . ')" class="btn btn-ghost btn-xs text-error" title="Delete Client">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </td>';
            echo '</tr>';
        }
    }
    exit;
}

require_once '../header.php';
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold">Clients</h2>
    <a href="client_add.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        Add Client
    </a>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <div class="form-control mb-4">
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="Search clients..." class="input input-bordered w-full max-w-xs" value="<?php echo htmlspecialchars($search); ?>" />
                <button class="btn btn-ghost">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="checkbox checkbox-sm" /></th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Pro Score</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="5" class="text-center text-base-content/60">No clients found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                        <tr class="hover">
                            <td><input type="checkbox" value="<?php echo $client['id']; ?>" class="checkbox checkbox-sm bulk-checkbox" /></td>
                            <td>
                                <div class="flex items-center space-x-3">
                                    <div class="avatar placeholder">
                                        <div class="bg-neutral-focus text-neutral-content rounded-full w-12">
                                            <span><?php echo strtoupper(substr($client['name'], 0, 2)); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-bold">
                                            <?php echo htmlspecialchars($client['name']); ?>
                                            <?php if ($client['total_earnings'] > 5000): ?>
                                                <span class="badge badge-warning badge-xs" title="High Value Client">VIP</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm opacity-50 text-base-content/60">ID: #<?php echo $client['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($client['email']); ?></div>
                                <div class="text-sm opacity-50 text-base-content/60"><?php echo htmlspecialchars($client['phone']); ?></div>
                            </td>
                            <td>
                                <?php 
                                    // Mock Pro Score Calculation (would normally use client_stats)
                                    $score = 50; 
                                    if ($client['status'] == 'Active') $score += 20;
                                    if ($client['total_earnings'] > 1000) $score += 10;
                                    if ($client['total_earnings'] > 5000) $score += 20;
                                    
                                    $score_color = 'text-error';
                                    if ($score > 70) $score_color = 'text-success';
                                    elseif ($score > 50) $score_color = 'text-warning';
                                ?>
                                <div class="radial-progress <?php echo $score_color; ?> text-xs" style="--value:<?php echo $score; ?>; --size:2rem;"><?php echo $score; ?></div>
                            </td>
                            <td>
                                <div class="badge <?php echo $client['status'] == 'Active' ? 'badge-success' : 'badge-ghost'; ?>">
                                    <?php echo $client['status']; ?>
                                </div>
                            </td>
                            <td>
                                <a href="<?php echo get_client_url($client); ?>" class="btn btn-ghost btn-xs" title="View Client">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </a>
                                <a href="client_edit.php?id=<?php echo $client['id']; ?>" class="btn btn-ghost btn-xs" title="Edit Client">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                <button onclick="openDeleteModal(<?php echo $client['id']; ?>)" class="btn btn-ghost btn-xs text-error" title="Delete Client">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
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
                $search_param = $search ? '&search=' . urlencode($search) : '';
                function build_client_url($page, $search_param) {
                    return "?page={$page}{$search_param}";
                }
            ?>
            
            <!-- Previous Button -->
            <a href="<?php echo $page > 1 ? build_client_url($page - 1, $search_param) : '#'; ?>" 
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
                    <a href="<?php echo build_client_url($i, $search_param); ?>" 
                       class="btn btn-sm h-10 w-10 p-0 rounded-lg bg-white border-base-300 hover:bg-base-200 text-base-content">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Next Button -->
            <a href="<?php echo $page < $total_pages ? build_client_url($page + 1, $search_param) : '#'; ?>" 
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
    <p class="py-4">Are you sure you want to delete this client? This action cannot be undone.</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Cancel</button>
      </form>
      <a id="confirm_delete_btn" href="#" class="btn btn-error">Delete</a>
    </div>
  </div>
</dialog>

<script>
function openDeleteModal(clientId) {
    const modal = document.getElementById('delete_modal');
    const deleteBtn = document.getElementById('confirm_delete_btn');
    deleteBtn.href = 'client_delete.php?id=' + clientId;
    modal.showModal();
}

const searchInput = document.querySelector('input[name="search"]');
const tableBody = document.querySelector('tbody');

searchInput.addEventListener('input', function() {
    const searchTerm = this.value;
    fetch('client_list.php?ajax_search=1&search=' + encodeURIComponent(searchTerm))
        .then(response => response.text())
        .then(html => {
            tableBody.innerHTML = html;
        });
});
</script>

<!-- Bulk Action Bar -->
<div id="bulkActionBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-base-100 shadow-2xl rounded-2xl p-4 border border-primary/20 hidden items-center gap-6 z-50 animate-bounce-in">
    <div class="flex items-center gap-2 px-3 py-1 bg-primary/10 rounded-lg text-primary font-bold">
        <span id="selectedCount">0</span> selected
    </div>
    
    <form action="client_bulk.php" method="POST" class="flex items-center gap-2">
        <input type="hidden" name="ids" id="bulkIds" />
        
        <select name="action" class="select select-bordered select-sm min-w-[150px]" required>
            <option value="">Bulk Action...</option>
            <option value="Active">Set Active</option>
            <option value="Inactive">Set Inactive</option>
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
