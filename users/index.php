<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

// Search Logic
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$where = "WHERE 1";
if ($search) {
    $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR role LIKE '%$search%')";
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Get Total Count
$total_users = db_fetch_one("SELECT COUNT(*) as total FROM users $where")['total'];
$total_pages = ceil($total_users / $limit);

$users = db_fetch_all("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

if (isset($_GET['ajax_search'])) {
    if (empty($users)) {
        echo '<tr><td colspan="5" class="text-center">No users found.</td></tr>';
    } else {
        foreach ($users as $user) {
            echo '<tr class="hover">';
            echo '<td>
                    <div class="flex items-center gap-3">
                        <div class="avatar placeholder">
                            <div class="bg-neutral-focus text-neutral-content rounded-full w-10">
                                <span>' . substr($user['name'], 0, 1) . '</span>
                            </div>
                        </div>
                        <div class="font-bold">' . htmlspecialchars($user['name']) . '</div>
                    </div>
                </td>';
            echo '<td>' . htmlspecialchars($user['email']) . '</td>';
            echo '<td>
                    <span class="badge ' . ($user['role'] == 'admin' ? 'badge-primary' : 'badge-ghost') . '">
                        ' . ucfirst($user['role']) . '
                    </span>
                </td>';
            echo '<td>' . date('M d, Y', strtotime($user['created_at'])) . '</td>';
            echo '<td>
                    <a href="view.php?id=' . $user['id'] . '" class="btn btn-sm btn-ghost text-info" title="View User">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </a>
                    <a href="edit.php?id=' . $user['id'] . '" class="btn btn-sm btn-ghost" title="Edit User">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </a>
                    ' . ($user['id'] != $_SESSION['user_id'] ? 
                    '<button onclick="openDeleteModal(' . $user['id'] . ')" class="btn btn-sm btn-ghost text-error" title="Delete User">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>' : '') . '
                </td>';
            echo '</tr>';
        }
    }
    exit;
}

$page_title = "Users";
require_once '../header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-3xl font-bold text-base-content">Users</h1>
    <div class="flex gap-2 w-full md:w-auto">
        <form method="GET" class="flex gap-2 w-full md:w-auto">
            <input type="text" name="search" placeholder="Search users..." class="input input-bordered w-full md:w-64" value="<?php echo htmlspecialchars($search); ?>" />
            <button class="btn btn-ghost">Search</button>
        </form>
        <a href="create.php" class="btn btn-primary">Add User</a>
    </div>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <div class="overflow-x-auto w-full">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="user-table-body">
                    <?php if (empty($users)): ?>
                         <tr><td colspan="5" class="text-center">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="hover">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="avatar placeholder">
                                        <div class="bg-neutral-focus text-neutral-content rounded-full w-10">
                                            <span><?php echo substr($user['name'], 0, 1); ?></span>
                                        </div>
                                    </div>
                                    <div class="font-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $user['role'] == 'admin' ? 'badge-primary' : 'badge-ghost'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td class="whitespace-nowrap">
                                <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-ghost text-info" title="View User">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </a>
                                <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-ghost" title="Edit User">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button onclick="openDeleteModal(<?php echo $user['id']; ?>)" class="btn btn-sm btn-ghost text-error" title="Delete User">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                                <?php endif; ?>
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
                // Build URL Helper
                function build_url($page, $search) {
                    $query = ['page' => $page];
                    if ($search) $query['search'] = $search;
                    return '?' . http_build_query($query);
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
    </div>
</div>

<!-- Delete Confirmation Modal -->
<dialog id="delete_modal" class="modal">
  <div class="modal-box">
    <h3 class="font-bold text-lg">Confirm Deletion</h3>
    <p class="py-4">Are you sure you want to delete this user? This action cannot be undone.</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Cancel</button>
      </form>
      <a id="confirm_delete_btn" href="#" class="btn btn-error">Delete</a>
    </div>
  </div>
</dialog>

<script>
function openDeleteModal(userId) {
    const modal = document.getElementById('delete_modal');
    const deleteBtn = document.getElementById('confirm_delete_btn');
    deleteBtn.href = 'delete.php?id=' + userId;
    modal.showModal();
}

const searchInput = document.querySelector('input[name="search"]');
const tableBody = document.getElementById('user-table-body');

if(searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value;
        fetch('index.php?ajax_search=1&search=' + encodeURIComponent(searchTerm))
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            });
    });
}
</script>

<?php require_once '../footer.php'; ?>
