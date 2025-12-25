<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$id = (int)$_GET['id'];
$task = db_fetch_one("SELECT * FROM tasks WHERE id = $id");

if (!$task) {
    die("Task not found.");
}

$page_title = "Edit Task";


// Fetch Projects & Users
$projects = db_fetch_all("SELECT id, name FROM projects WHERE status != 'Completed' OR id = {$task['project_id']} ORDER BY name");
$users = db_fetch_all("SELECT id, name FROM users ORDER BY name");
$all_tasks = db_fetch_all("SELECT id, title FROM tasks WHERE id != $id AND status != 'Done' ORDER BY title");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = escape($_POST['title']);
    $project_id = (int)$_POST['project_id'];
    $assigned_to = (int)$_POST['assigned_to'];
    $priority = escape($_POST['priority']);
    $status = escape($_POST['status']);
    $due_date = escape($_POST['due_date']);
    $description = escape($_POST['description']);

    if ($title) {
        $sql = "UPDATE tasks SET 
                title = '$title', 
                project_id = $project_id, 
                assigned_to = " . ($assigned_to ? $assigned_to : "NULL") . ", 
                priority = '$priority', 
                status = '$status', 
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ", 
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ", 
                description = '$description',
                parent_id = " . ($parent_id = (int)$_POST['parent_id'] ? $parent_id : "NULL") . ",
                dependencies = '" . escape($_POST['dependencies']) . "' 
                WHERE id = $id";
        
        if (db_query($sql)) {
            redirect('tasks/index.php');
        } else {
            $error = "Error updating task.";
        }
    } else {
        $error = "Title is required.";
    }
}
?>
<?php require_once '../header.php'; ?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Edit Task</h1>
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
                    <label class="label"><span class="label-text">Task Title *</span></label>
                    <input type="text" name="title" class="input input-bordered" value="<?php echo htmlspecialchars($task['title']); ?>" required />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Project</span></label>
                        <select name="project_id" class="select select-bordered" required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php if($task['project_id'] == $p['id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Assign To</span></label>
                        <select name="assigned_to" class="select select-bordered">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php if($task['assigned_to'] == $u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Priority</span></label>
                        <select name="priority" class="select select-bordered">
                            <option value="Low" <?php if($task['priority'] == 'Low') echo 'selected'; ?>>Low</option>
                            <option value="Medium" <?php if($task['priority'] == 'Medium') echo 'selected'; ?>>Medium</option>
                            <option value="High" <?php if($task['priority'] == 'High') echo 'selected'; ?>>High</option>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Status</span></label>
                        <select name="status" class="select select-bordered">
                            <option value="Todo" <?php if($task['status'] == 'Todo') echo 'selected'; ?>>Todo</option>
                            <option value="In Progress" <?php if($task['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                            <option value="On Hold" <?php if($task['status'] == 'On Hold') echo 'selected'; ?>>On Hold</option>
                            <option value="Blocked" <?php if($task['status'] == 'Blocked') echo 'selected'; ?>>Blocked</option>
                            <option value="Done" <?php if($task['status'] == 'Done') echo 'selected'; ?>>Done</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Due Date</span></label>
                        <input type="date" name="due_date" class="input input-bordered" value="<?php echo $task['due_date']; ?>" />
                    </div>
                </div>

                <div class="form-control mb-6">
                    <label class="label"><span class="label-text">Description</span></label>
                    <textarea name="description" class="textarea textarea-bordered h-32" placeholder="Describe the task..."><?php echo htmlspecialchars($task['description']); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Parent Task (Optional)</span></label>
                        <select name="parent_id" class="select select-bordered">
                            <option value="">None</option>
                            <?php foreach ($all_tasks as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php if($task['parent_id'] == $t['id']) echo 'selected'; ?>><?php echo htmlspecialchars($t['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Dependencies (Task IDs, comma separated)</span></label>
                        <input type="text" name="dependencies" class="input input-bordered" value="<?php echo htmlspecialchars($task['dependencies']); ?>" placeholder="e.g. 101, 102" />
                    </div>
                </div>

                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
