<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin']);

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $id = (int)$_POST['delete_event'];
    db_query("DELETE FROM events WHERE id = $id");
    redirect('events/index.php?msg=deleted');
}

$events = [];

// Search Logic
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$where = "WHERE 1";
if ($search) {
    $where .= " AND (title LIKE '%$search%' OR status LIKE '%$search%')";
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get Total Count
$total_events = db_fetch_one("SELECT COUNT(*) as total FROM events $where")['total'];
$total_pages = ceil($total_events / $limit);

$events = db_fetch_all("SELECT * FROM events $where ORDER BY start_date DESC LIMIT $limit OFFSET $offset");

if (isset($_GET['ajax_search'])) {
    if (empty($events)) {
        echo '<tr>
                <td colspan="5" class="text-center py-8 text-base-content/50">
                    No events found. Start by adding one!
                </td>
            </tr>';
    } else {
        foreach ($events as $event) {
            $start = new DateTime($event['start_date']);
            $end = new DateTime($event['end_date']);
            $duration = $start->diff($end)->days + 1;
            $isActive = $event['status'] === 'Active';
            
            echo '<tr class="hover">
                    <td>
                        <div class="font-bold text-lg">' . htmlspecialchars($event['title']) . '</div>
                    </td>
                    <td>
                        <div class="badge ' . ($isActive ? 'badge-success' : 'badge-ghost') . '">
                            ' . $event['status'] . '
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-col text-sm">
                            <span>' . $start->format('M d, Y') . '</span>
                            <span class="text-xs text-base-content/50">to ' . $end->format('M d, Y') . '</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-sm badge-outline">' . $duration . ' Days</span>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="edit.php?id=' . $event['id'] . '" class="btn btn-sm btn-ghost btn-square text-info">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </a>
                            <button onclick="confirmDelete(' . $event['id'] . ')" class="btn btn-sm btn-ghost btn-square text-error">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </td>
                </tr>';
        }
    }
    exit;
}

require_once '../header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-bold">Events Management</h2>
            <p class="text-base-content/70">Manage seasonal promotions and events.</p>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
             <form method="GET" class="flex gap-2 w-full md:w-auto">
                <input type="text" name="search" placeholder="Search events..." class="input input-bordered w-full md:w-64" value="<?php echo htmlspecialchars($search); ?>" />
                <button class="btn btn-ghost">Search</button>
            </form>
            <a href="create.php" class="btn btn-primary">+ New Event</a>
        </div>
    </div>

    <div class="card bg-base-100 shadow-xl overflow-x-auto">
        <table class="table w-full">
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Status</th>
                    <th>Dates</th>
                    <th>Duration</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="event-table-body">
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-8 text-base-content/50">
                            No events found. Start by adding one!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $event): 
                        $start = new DateTime($event['start_date']);
                        $end = new DateTime($event['end_date']);
                        $duration = $start->diff($end)->days + 1;
                        $isActive = $event['status'] === 'Active';
                    ?>
                    <tr class="hover">
                        <td>
                            <div class="font-bold text-lg"><?php echo htmlspecialchars($event['title']); ?></div>
                        </td>
                        <td>
                            <div class="badge <?php echo $isActive ? 'badge-success' : 'badge-ghost'; ?>">
                                <?php echo $event['status']; ?>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-col text-sm">
                                <span><?php echo $start->format('M d, Y'); ?></span>
                                <span class="text-xs text-base-content/50">to <?php echo $end->format('M d, Y'); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-sm badge-outline"><?php echo $duration; ?> Days</span>
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <a href="edit.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-ghost btn-square text-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                <button onclick="confirmDelete(<?php echo $event['id']; ?>)" class="btn btn-sm btn-ghost btn-square text-error">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
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
</div>

<dialog id="delete_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg text-error">Delete Event</h3>
        <p class="py-4">Are you sure you want to delete this event?</p>
        <form method="POST" class="modal-action">
            <input type="hidden" name="delete_event" id="delete_event_id">
            <button class="btn btn-error">Yes, Delete</button>
            <button type="button" class="btn" onclick="delete_modal.close()">Cancel</button>
        </form>
    </div>
</dialog>

<script>
function confirmDelete(id) {
    document.getElementById('delete_event_id').value = id;
    document.getElementById('delete_modal').showModal();
}

const searchInput = document.querySelector('input[name="search"]');
const tableBody = document.getElementById('event-table-body');

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
