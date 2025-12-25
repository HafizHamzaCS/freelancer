<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

$page_title = "Create Task";


// Fetch Projects & Users for Dropdowns
$projects = db_fetch_all("SELECT id, name FROM projects WHERE status != 'Completed' ORDER BY name");
$users = db_fetch_all("SELECT id, name FROM users ORDER BY name");
$tasks = db_fetch_all("SELECT id, title FROM tasks WHERE status != 'Done' ORDER BY title");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = escape($_POST['title']);
    $project_id = (int)$_POST['project_id'];
    $assigned_to = (int)$_POST['assigned_to'];
    $priority = escape($_POST['priority']);
    $status = escape($_POST['status']);
    $due_date = escape($_POST['due_date']);
    $description = escape($_POST['description']);
    $created_by = $_SESSION['user_id'];

    $parent_id = (int)$_POST['parent_id'];
    $dependencies = escape($_POST['dependencies']);

    if ($title) {
        $sql = "INSERT INTO tasks (title, project_id, assigned_to, priority, status, due_date, description, parent_id, dependencies, created_by) 
                VALUES ('$title', $project_id, " . ($assigned_to ? $assigned_to : "NULL") . ", '$priority', '$status', " . ($due_date ? "'$due_date'" : "NULL") . ", '$description', " . ($parent_id ? $parent_id : "NULL") . ", '$dependencies', $created_by)";
        
        if (db_query($sql)) {
            // Trigger Automation
            $task_id = $conn->insert_id;
            trigger_workflow('task_created', [
                'task_id' => $task_id,
                'title' => $title,
                'project_id' => $project_id,
                'assigned_to' => $assigned_to,
                'link' => APP_URL . "/tasks/view_task.php?id=$task_id"
            ]);
            
            // Optional: Send Notification to assigned user (Legacy/Fallback)
            // if ($assigned_to) { ... }

            redirect('tasks/index.php');
        } else {
            $error = "Error creating task.";
        }
    } else {
        $error = "Title is required.";
    }
}
?>
<?php require_once '../header.php'; ?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Create Task</h1>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-control mb-4">
                    <label class="label"><span class="label-text">Task Title <span class="text-error">*</span></span></label>
                    <input type="text" name="title" class="input input-bordered" required />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Project <span class="text-error">*</span></span></label>
                        <select name="project_id" class="select select-bordered" required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Assign To</span></label>
                        <select name="assigned_to" class="select select-bordered">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Priority</span></label>
                        <select name="priority" class="select select-bordered">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Status</span></label>
                        <select name="status" class="select select-bordered">
                            <option value="Todo" selected>Todo</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Blocked">Blocked</option>
                            <option value="Done">Done</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Due Date</span></label>
                        <input type="date" name="due_date" class="input input-bordered" />
                    </div>
                </div>

                <div class="form-control mb-6">
                    <label class="label"><span class="label-text">Description</span></label>
                    <textarea name="description" class="textarea textarea-bordered h-32" placeholder="Describe the task..."></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Parent Task (Optional)</span></label>
                        <select name="parent_id" class="select select-bordered">
                            <option value="">None</option>
                            <?php foreach ($tasks as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Dependencies (Task IDs, comma separated)</span></label>
                        <input type="text" name="dependencies" class="input input-bordered" placeholder="e.g. 101, 102" />
                    </div>
                </div>

                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>

            <script>
            document.querySelector('form').addEventListener('submit', function(e) {
                const title = this.title.value.trim();
                const project_id = this.project_id.value;
                let error = '';

                if (!title) error = 'Task Title is required.';
                else if (!project_id) error = 'Please select a Project.';

                if (error) {
                    e.preventDefault();
                    const existing = document.querySelector('.alert-error.js-validation');
                    if(existing) existing.remove();

                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-error mb-4 js-validation';
                    alertDiv.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>${error}</span>
                    `;
                    this.prepend(alertDiv);
                    window.scrollTo(0,0);
                }
            });
            </script>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
