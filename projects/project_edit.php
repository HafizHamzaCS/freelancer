<?php
require_once '../config.php';
require_once '../functions.php';

// Access Control: Clients cannot edit projects
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    redirect('projects/project_list.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = db_fetch_one("SELECT * FROM projects WHERE id = $id");
$clients = db_fetch_all("SELECT * FROM clients WHERE status = 'Active'");

if (!$project) {
    redirect('projects/project_list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = (int)$_POST['client_id'];
    $name = escape($_POST['name']);
    $slug = escape($_POST['slug']);
    if (empty($slug)) {
        $slug = generate_slug($name);
    }
    $source = escape($_POST['source']);
    $start_date = escape($_POST['start_date']);
    $deadline = escape($_POST['deadline']);
    // $budget = (float)$_POST['budget'];
    $status = escape($_POST['status']);

    $sql = "UPDATE projects SET client_id=$client_id, name='$name', slug='$slug', status='$status', source='$source', start_date='$start_date', deadline='$deadline' WHERE id=$id";
    
    if (db_query($sql)) {
        // Update Members
        db_query("DELETE FROM project_members WHERE project_id = $id");
        if (isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $user_id) {
                $user_id = (int)$user_id;
                db_query("INSERT INTO project_members (project_id, user_id) VALUES ($id, $user_id)");
            }
        }

        redirect('projects/project_list.php');
    }
}

// Fetch assigned members
$assigned_members = [];
$mem_sql = "SELECT user_id FROM project_members WHERE project_id = $id";
$mem_res = db_fetch_all($mem_sql);
foreach ($mem_res as $m) {
    $assigned_members[] = $m['user_id'];
}

require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Edit Project</h2>
        <a href="project_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" x-data="{ 
                source: '<?php echo $project['source'] ?? 'Direct'; ?>'
            }">
                <div class="form-control w-full">
                    <label class="label">
                        <span class="label-text">Project Name</span>
                    </label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($project['name']); ?>" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text">Slug</span>
                    </label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($project['slug'] ?? ''); ?>" class="input input-bordered w-full" />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text font-semibold">Assign Team Members</span>
                    </label>
                    <div class="grid grid-cols-1 gap-2 border border-base-300 rounded-box p-4 bg-base-50/50 max-h-60 overflow-y-auto custom-scrollbar">
                        <?php 
                        $members = db_fetch_all("SELECT * FROM users WHERE role = 'member' ORDER BY name ASC");
                        
                        if (empty($members)): ?>
                            <div class="col-span-full text-center text-base-content/50 text-sm py-4">
                                No members found. <a href="../users/index.php" class="link link-primary">Add members</a> first.
                            </div>
                        <?php else:
                            foreach ($members as $member): 
                                $is_assigned = in_array($member['id'], $assigned_members);
                        ?>
                            <label class="cursor-pointer label border border-base-200 rounded-lg hover:bg-base-100 hover:border-primary/50 transition-all p-3 justify-start gap-3 group bg-base-100 shadow-sm">
                                <input type="checkbox" name="members[]" value="<?php echo $member['id']; ?>" class="checkbox checkbox-primary checkbox-sm" <?php echo $is_assigned ? 'checked' : ''; ?> />
                                <span class="label-text font-medium group-hover:text-primary transition-colors"><?php echo htmlspecialchars($member['name']); ?></span>
                            </label>
                        <?php 
                            endforeach;
                        endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Client</span>
                        </label>
                        <select name="client_id" class="select select-bordered" required>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client['id'] == $project['client_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Source</span>
                        </label>
                        <select name="source" class="select select-bordered" x-model="source">
                            <option value="Direct">Direct</option>
                            <option value="Fiverr">Fiverr (22% Tax)</option>
                            <option value="Upwork">Upwork (12% Tax)</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Start Date</span>
                        </label>
                        <input type="date" name="start_date" value="<?php echo $project['start_date']; ?>" class="input input-bordered w-full" required />
                    </div>
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Deadline</span>
                        </label>
                        <input type="date" name="deadline" value="<?php echo $project['deadline']; ?>" class="input input-bordered w-full" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <!-- Budget Removed -->
                    
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Status</span>
                        </label>
                        <select name="status" class="select select-bordered">
                            <option value="In Progress" <?php echo $project['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Pending" <?php echo $project['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Completed" <?php echo $project['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="On Hold" <?php echo $project['status'] == 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary">Update Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
