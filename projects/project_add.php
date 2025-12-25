<?php
require_once '../config.php';
require_once '../functions.php';

// Access Control: Clients cannot add projects
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    redirect('projects/project_list.php');
}

$clients = db_fetch_all("SELECT * FROM clients WHERE status = 'Active'");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = (int)$_POST['client_id'];
    $name = escape($_POST['name']);
    $slug = escape($_POST['slug']);
    if (empty($slug)) {
        $slug = generate_slug($name);
    }
    $status = escape($_POST['status']);
    $source = escape($_POST['source']);
    $start_date = escape($_POST['start_date']);
    $deadline = escape($_POST['deadline']);
    // $budget = (float)$_POST['budget'];

    $sql = "INSERT INTO projects (client_id, name, slug, status, source, start_date, deadline, budget) VALUES ($client_id, '$name', '$slug', '$status', '$source', '$start_date', '$deadline', 0)";
    
    if (db_query($sql)) {
        $project_id = mysqli_insert_id($conn);
        
        // Handle Team Members
        if (isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $user_id) {
                $user_id = (int)$user_id;
                db_query("INSERT INTO project_members (project_id, user_id) VALUES ($project_id, $user_id)");
            }
        }

        redirect('projects/project_list.php');
    }
}

require_once '../header.php';

$preselected_client = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">New Project</h2>
        <a href="project_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" x-data="{ 
                budget: '', 
                source: 'Direct',
                get taxRate() {
                    if (this.source === 'Fiverr') return 0.22;
                    if (this.source === 'Upwork') return 0.12;
                    return 0;
                },
                get netAmount() {
                    return this.budget ? (this.budget * (1 - this.taxRate)).toFixed(2) : '0.00';
                }
            }">
                <div class="form-control w-full">
                    <label class="label">
                        <span class="label-text">Project Name <span class="text-error">*</span></span>
                    </label>
                    <input type="text" name="name" placeholder="e.g. Website Redesign" class="input input-bordered w-full" required />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text">Slug (Optional)</span>
                    </label>
                    <input type="text" name="slug" placeholder="website-redesign" class="input input-bordered w-full" />
                    <label class="label">
                        <span class="label-text-alt">Leave empty to auto-generate from name</span>
                    </label>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text font-semibold">Assign Team Members</span>
                    </label>
                    <div class="grid grid-cols-1 gap-2 border border-base-300 rounded-box p-4 bg-base-50/50 max-h-60 overflow-y-auto custom-scrollbar">
                        <?php 
                        // Modified to strictly show only 'member' role (no admins)
                        $members = db_fetch_all("SELECT * FROM users WHERE role = 'member' ORDER BY name ASC");
                        
                        if (empty($members)): ?>
                            <div class="col-span-full text-center text-base-content/50 text-sm py-4">
                                No members found. <a href="../users/index.php" class="link link-primary">Add members</a> first.
                            </div>
                        <?php else:
                            foreach ($members as $member): 
                        ?>
                            <label class="cursor-pointer label border border-base-200 rounded-lg hover:bg-base-100 hover:border-primary/50 transition-all p-3 justify-start gap-3 group bg-base-100 shadow-sm">
                                <input type="checkbox" name="members[]" value="<?php echo $member['id']; ?>" class="checkbox checkbox-primary checkbox-sm" />
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
                            <span class="label-text">Client <span class="text-error">*</span></span>
                            <a href="../clients/client_add.php" class="label-text-alt link link-primary hover:underline">+ Add New Client</a>
                        </label>
                        <select name="client_id" class="select select-bordered" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client['id'] == $preselected_client ? 'selected' : ''; ?>>
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
                            <span class="label-text">Start Date <span class="text-error">*</span></span>
                        </label>
                        <input type="date" name="start_date" class="input input-bordered w-full" required value="<?php echo date('Y-m-d'); ?>" />
                    </div>
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Deadline <span class="text-error">*</span></span>
                        </label>
                        <input type="date" name="deadline" class="input input-bordered w-full" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <!-- Budget Removed -->
                    
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text">Status</span>
                        </label>
                        <select name="status" class="select select-bordered">
                            <option value="In Progress">In Progress</option>
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>

            <script>
            document.querySelector('form').addEventListener('submit', function(e) {
                const name = this.name.value.trim();
                const client_id = this.client_id.value;
                const start_date = this.start_date.value;
                const deadline = this.deadline.value;
                let error = '';

                if (!name) error = 'Project Name is required.';
                else if (!client_id) error = 'Please select a Client.';
                else if (!start_date) error = 'Start Date is required.';
                else if (!deadline) error = 'Deadline is required.';
                else if (new Date(start_date) > new Date(deadline)) error = 'Start Date cannot be after Deadline.';

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
