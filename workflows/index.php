<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role('admin');

$page_title = "Workflows";
require_once '../header.php';

// Handle Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_workflow'])) {
    $name = escape($_POST['name']);
    $trigger = escape($_POST['trigger']);
    $action_type = escape($_POST['action_type']);
    $payload = escape($_POST['payload']);
    
    db_query("INSERT INTO workflows (name, trigger_event, action_type, action_payload) VALUES ('$name', '$trigger', '$action_type', '$payload')");
    redirect('index.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db_query("DELETE FROM workflows WHERE id = $id");
    redirect('index.php');
}

$workflows = db_fetch_all("SELECT * FROM workflows ORDER BY created_at DESC");
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-accent">Automation Workflows</h1>
        <p class="opacity-50">Automate repetitive tasks and notifications.</p>
    </div>
    <button class="btn btn-primary" onclick="create_modal.showModal()">+ New Workflow</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($workflows as $wf): ?>
    <div class="card bg-base-100 shadow-xl border border-base-200">
        <div class="card-body">
            <div class="flex justify-between items-start">
                <div class="badge badge-outline"><?php echo $wf['trigger_event']; ?></div>
                <a href="?delete=<?php echo $wf['id']; ?>" class="btn btn-ghost btn-xs text-error" onclick="return confirm('Delete this workflow?')">✕</a>
            </div>
            <h2 class="card-title mt-2"><?php echo htmlspecialchars($wf['name']); ?></h2>
            <div class="divider my-2">THEN</div>
            <div class="flex items-center gap-2 text-sm">
                 <span class="font-bold uppercase text-xs text-primary"><?php echo $wf['action_type']; ?></span>
                 <span class="truncate opacity-70"><?php echo htmlspecialchars($wf['action_payload']); ?></span>
            </div>
            <div class="card-actions justify-end mt-4">
                <div class="badge <?php echo $wf['is_active'] ? 'badge-success' : 'badge-ghost'; ?> badge-sm">
                    <?php echo $wf['is_active'] ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if(empty($workflows)): ?>
    <div class="col-span-full text-center py-12 border-2 border-dashed border-base-300 rounded-xl">
        <p class="mb-4 opacity-50">No workflows defined yet.</p>
        <button class="btn btn-outline" onclick="create_modal.showModal()">Create Your First Automation</button>
    </div>
    <?php endif; ?>
</div>

<dialog id="create_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Create Automation</h3>
        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="create_workflow" value="1">
            
            <div class="form-control">
                <label class="label"><span class="label-text">Workflow Name</span></label>
                <input type="text" name="name" class="input input-bordered" placeholder="e.g., Notify PM on Task Completion" required />
            </div>

            <div class="form-control">
                <label class="label"><span class="label-text">When this happens... (Trigger)</span></label>
                <select name="trigger" class="select select-bordered">
                    <option value="task_created">Task Created</option>
                    <option value="task_completed">Task Completed</option>
                    <option value="project_created">Project Created</option>
                </select>
            </div>

            <div class="form-control">
                <label class="label"><span class="label-text">Do this action...</span></label>
                <select name="action_type" class="select select-bordered">
                    <option value="notification">Send Notification</option>
                    <option value="email">Send Email</option>
                </select>
            </div>

            <div class="form-control">
                <label class="label"><span class="label-text">Action Details</span></label>
                <textarea name="payload" class="textarea textarea-bordered" placeholder="Is Notification: Content message...&#10;Is Email: Subject | Body"></textarea>
                <label class="label"><span class="label-text-alt">For notifications, enter the message. For emails, enter Subject | Body.</span></label>
            </div>

            <div class="modal-action">
                <button class="btn btn-primary">Save Workflow</button>
                <button type="button" class="btn" onclick="create_modal.close()">Close</button>
            </div>
        </form>
    </div>
</dialog>

<?php require_once '../footer.php'; ?>
