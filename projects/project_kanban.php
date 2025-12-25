<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$projects = db_fetch_all("SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id ORDER BY p.deadline ASC");

// Fetch Tasks for these projects
$project_ids = array_column($projects, 'id');
$tasks_by_project = [];

if (!empty($project_ids)) {
    $ids_str = implode(',', array_map('intval', $project_ids));
    // Fetch only pending/active tasks to keep the board clean
    $all_tasks = db_fetch_all("SELECT * FROM tasks WHERE project_id IN ($ids_str) AND status != 'Done'");
    
    foreach ($all_tasks as $t) {
        $tasks_by_project[$t['project_id']][] = $t;
    }
}

foreach ($projects as &$p) {
    $p['tasks'] = $tasks_by_project[$p['id']] ?? [];
}
unset($p); // Break reference

$columns = [
    'Pending' => [],
    'In Progress' => [],
    'Review' => [],
    'Changes Requested' => [],
    'On Hold' => [],
    'Completed' => []
];

foreach ($projects as $project) {
    if (isset($columns[$project['status']])) {
        $columns[$project['status']][] = $project;
    } else {
        $columns['Pending'][] = $project; // Default fallback
    }
}

// Helper for Source Badge Color is now in functions.php
?>

<?php require_once '../header.php'; ?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold">Project Kanban</h2>
    <div class="flex gap-2">
        <a href="project_list.php" class="btn btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
            List View
        </a>
        <a href="project_add.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            New Project
        </a>
    </div>
</div>

<div class="flex overflow-x-auto gap-6 pb-4 h-[calc(100vh-200px)]" x-data="kanbanBoard()">
    <?php foreach ($columns as $status => $items): ?>
    <div class="flex-none w-80" 
         @dragover.prevent="dragOver($event)" 
         @drop.prevent="drop($event, '<?php echo $status; ?>')">
        <div class="bg-base-200 p-4 rounded-xl h-full flex flex-col transition-all duration-200" 
             :class="dragOverColumn === '<?php echo $status; ?>' ? 'bg-base-300 ring-2 ring-primary ring-inset' : ''">
            <h3 class="font-bold mb-4 flex justify-between items-center px-1">
                <span class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full <?php 
                        echo match($status) {
                            'Completed' => 'bg-success',
                            'In Progress' => 'bg-info',
                            'On Hold' => 'bg-warning',
                            default => 'bg-base-content/30'
                        };
                    ?>"></span>
                    <?php echo $status; ?>
                </span>
                <span class="badge badge-sm"><?php echo count($items); ?></span>
            </h3>
            
            <div class="space-y-3 overflow-y-auto flex-1 pr-2 min-h-[100px] scrollbar-thin scrollbar-thumb-base-content/20 scrollbar-track-transparent">
                <?php foreach ($items as $project): ?>
                <div class="card bg-base-100 shadow-sm hover:shadow-md transition-all cursor-move group border border-base-content/5" 
                     draggable="true" 
                     @dragstart="dragStart($event, <?php echo $project['id']; ?>)"
                     onclick="if(!isDragging) window.location='project_view.php?id=<?php echo $project['id']; ?>'">
                    <div class="card-body p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div class="badge <?php echo get_kanban_source_badge($project['source'] ?? 'Direct'); ?> badge-xs">
                                <?php echo htmlspecialchars($project['source'] ?? 'Direct'); ?>
                            </div>
                            <?php if(new DateTime($project['deadline']) < new DateTime() && $project['status'] != 'Completed'): ?>
                                <span class="text-xs text-error font-bold" title="Overdue">⚠️</span>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="font-bold text-sm line-clamp-2" title="<?php echo htmlspecialchars($project['name']); ?>">
                            <?php echo htmlspecialchars($project['name']); ?>
                        </h4>
                        
                        <div class="text-xs text-base-content/70 mt-1 mb-3 truncate">
                            👤 <?php echo htmlspecialchars($project['client_name']); ?>
                        </div>

                        <!-- Tasks Display -->
                        <?php if (!empty($project['tasks'])): ?>
                            <div class="mt-2 mb-3 bg-base-200/50 rounded p-2 space-y-1">
                                <?php foreach ($project['tasks'] as $task): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full bg-primary/70"></div>
                                        <span class="text-xs truncate text-base-content/80"><?php echo htmlspecialchars($task['title']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between items-end mt-auto pt-2 border-t border-base-content/10">
                            <div class="text-xs opacity-70 font-mono">
                                <?php echo date('M d', strtotime($project['deadline'])); ?>
                            </div>
                            <div class="font-bold text-xs">
                                <?php echo format_money($project['budget']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function kanbanBoard() {
    return {
        isDragging: false,
        dragOverColumn: null,
        draggedId: null,

        dragStart(e, id) {
            this.isDragging = true;
            this.draggedId = id;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', id);
            e.target.classList.add('opacity-50', 'scale-95');
        },

        dragOver(e) {
            // Find the closest column container
            const column = e.target.closest('.w-80');
            if (column) {
                // Extract status from the h3 header
                const headerText = column.querySelector('h3').innerText;
                // The header text includes the count (e.g., "Pending\n3"), so we split and take the first part
                this.dragOverColumn = headerText.split('\n')[0].trim();
            }
        },

        async drop(e, newStatus) {
            e.preventDefault();
            const id = this.draggedId;
            
            // Reset visual states
            this.isDragging = false;
            this.dragOverColumn = null;
            document.querySelectorAll('.card').forEach(el => el.classList.remove('opacity-50', 'scale-95'));

            if (!id) return;

            // Optimistic UI update could happen here, but for safety we'll reload
            // In a full SPA, we would move the DOM element manually.
            
            try {
                const response = await fetch('update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id, status: newStatus })
                });
                
                const result = await response.json();
                if (result.success) {
                    // Show success toast or just reload
                    window.location.reload(); 
                } else {
                    alert('Failed to update status: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating status');
            }
        }
    }
}
</script>

<?php require_once '../footer.php'; ?>
