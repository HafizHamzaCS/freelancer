<?php
require_once '../config.php';
require_once '../functions.php';

// --- Auto-Migration for Schema Updates ---
$check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'deleted_at'");
if (mysqli_num_rows($check_cols) == 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE projects ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS system_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Feature: Task Chat
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS task_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT,
    user_id INT,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Feature: Task Files
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS task_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT,
    original_name VARCHAR(255),
    file_path VARCHAR(255),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Feature: Task Activity
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS task_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT,
    user_id INT,
    action_type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Feature 1: Task Dependencies
$check_dep = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'dependency_id'");
if (mysqli_num_rows($check_dep) == 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN dependency_id INT NULL");
}

// Feature 2: Communication (Discussions)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    user_id INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Feature 3: RBAC
// Attempt to update enum safely. 
// Note: In strict SQL, changing ENUM requires full table alter. 
// We will attempt a benign alter that expands the list.
@mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN role ENUM('admin','member','client','manager','developer','viewer') NOT NULL DEFAULT 'member'");
// -----------------------------------------

// Access Control: Clients can only view their own projects
// Access Control: Clients can only view their own projects
if (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
    $current_client_id = 0;

    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'user') {
        $email = $_SESSION['user_email'];
        $client_record = db_fetch_one("SELECT id FROM clients WHERE email = '$email'");
        if ($client_record) $current_client_id = $client_record['id'];
    } else {
        $current_client_id = $_SESSION['user_id'];
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $slug = isset($_GET['slug']) ? escape($_GET['slug']) : '';
    
    $check_project = null;
    if ($slug) {
        $check_project = db_fetch_one("SELECT client_id FROM projects WHERE slug = '$slug'");
    } elseif ($id) {
        $check_project = db_fetch_one("SELECT client_id FROM projects WHERE id = $id");
    }
    
    if (!$check_project || $check_project['client_id'] != $current_client_id) {
        redirect('projects/project_list.php');
    }
} elseif (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    // Access Control: Team members must be assigned to the project
    $user_id = $_SESSION['user_id'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $slug = isset($_GET['slug']) ? escape($_GET['slug']) : '';

    $is_assigned = false;
    if ($slug) {
        $is_assigned = db_fetch_one("SELECT 1 FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE p.slug = '$slug' AND pm.user_id = $user_id");
    } elseif ($id) {
        $is_assigned = db_fetch_one("SELECT 1 FROM project_members WHERE project_id = $id AND user_id = $user_id");
    }

    if (!$is_assigned) {
        $_SESSION['flash_error'] = "You are not assigned to this project.";
        redirect('projects/project_list.php');
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? escape($_GET['slug']) : '';

if ($slug) {
    $project = db_fetch_one("SELECT * FROM projects WHERE slug = '$slug'");
} else {
    $project = db_fetch_one("SELECT * FROM projects WHERE id = $id");
}

if (!$project) {
    redirect('projects/project_list.php');
}

$id = $project['id']; // Ensure we have the ID

// Fetch related data
$client = db_fetch_one("SELECT * FROM clients WHERE id = " . $project['client_id']);
$tasks = db_fetch_all("SELECT * FROM tasks WHERE project_id = $id AND deleted_at IS NULL ORDER BY id DESC");
$milestones = db_fetch_all("SELECT * FROM milestones WHERE project_id = $id ORDER BY due_date ASC");
$time_entries = db_fetch_all("SELECT * FROM time_entries WHERE project_id = $id ORDER BY start_time DESC");
$project_files = db_fetch_all("SELECT * FROM project_files WHERE project_id = $id ORDER BY uploaded_at DESC");
$users = db_fetch_all("SELECT id, name FROM users ORDER BY name");

// Calculate Stats
$total_milestones = count($milestones);
$completed_milestones = count(array_filter($milestones, fn($m) => $m['status'] == 'Completed'));
$progress = $total_milestones > 0 ? round(($completed_milestones / $total_milestones) * 100) : 0;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tab_redirect = 'overview';
    
    // Add/Update Task (Full)
    if (isset($_POST['save_task'])) {
        $task_id = (int)$_POST['task_id']; // 0 for new
        $title = escape($_POST['title']);
        $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : 'NULL';
        $priority = escape($_POST['priority']);
        $status = escape($_POST['status']);
        $due_date = !empty($_POST['due_date']) ? "'" . escape($_POST['due_date']) . "'" : 'NULL';
        $description = escape($_POST['description']);
        $dependency_id = !empty($_POST['dependency_id']) ? (int)$_POST['dependency_id'] : 'NULL';
        
        if ($task_id > 0) {
            // Update
            $sql = "UPDATE tasks SET title='$title', assigned_to=$assigned_to, priority='$priority', status='$status', due_date=$due_date, description='$description', dependency_id=$dependency_id WHERE id=$task_id AND project_id=$id";
            db_query($sql);
            log_system_activity('Task Update', "Updated task #$task_id");
            set_flash('success', 'Task updated successfully!');
        } else {
            // Create
            $sql = "INSERT INTO tasks (project_id, title, assigned_to, priority, status, due_date, description, dependency_id) VALUES ($id, '$title', $assigned_to, '$priority', '$status', $due_date, '$description', $dependency_id)";
            db_query($sql);
            log_system_activity('Task Create', "Created new task in project #$id");
            set_flash('success', 'Task created successfully!');
        }
        $tab_redirect = 'overview';
    }
    
    // Simple status update (keep for legacy/kanban if needed, or remove)
    if (isset($_POST['update_task_status_only'])) { // Rename to avoid conflict
        $task_id = (int)$_POST['edit_task_id'];
        $title = escape($_POST['edit_task_title']);
        $status = escape($_POST['edit_task_status']);
        db_query("UPDATE tasks SET title = '$title', status = '$status' WHERE id = $task_id AND project_id = $id");
        $tab_redirect = 'overview';
    }

    // Update Description
    if (isset($_POST['update_description'])) {
        $desc = escape($_POST['description']);
        db_query("UPDATE projects SET description = '$desc' WHERE id = $id");
        $tab_redirect = 'description';
    }
    
    // Delete Task (Soft Delete)
    if (isset($_POST['delete_task'])) {
        $task_id = (int)$_POST['delete_task'];
        db_query("UPDATE tasks SET deleted_at = NOW() WHERE id = $task_id AND project_id = $id");
        log_system_activity('Task', "Soft deleted task #$task_id");
        set_flash('success', 'Task moved to trash!');
        $tab_redirect = 'overview';
    }
    // Add Milestone
    if (isset($_POST['add_milestone'])) {
        $title = escape($_POST['title']);
        $due = escape($_POST['due_date']);
        db_query("INSERT INTO milestones (project_id, title, due_date, status) VALUES ($id, '$title', '$due', 'Pending')");
        
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            $milestones = db_fetch_all("SELECT * FROM milestones WHERE project_id = $id ORDER BY due_date ASC");
            include 'milestones_partial.php';
            exit;
        }
        $tab_redirect = 'milestones';
    }
    // Toggle Milestone Status
    if (isset($_POST['toggle_milestone'])) {
        $m_id = (int)$_POST['toggle_milestone'];
        $current = db_fetch_one("SELECT status FROM milestones WHERE id = $m_id");
        $new_status = $current['status'] == 'Completed' ? 'Pending' : 'Completed';
        db_query("UPDATE milestones SET status = '$new_status' WHERE id = $m_id");
        
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            $milestones = db_fetch_all("SELECT * FROM milestones WHERE project_id = $id ORDER BY due_date ASC");
            echo '<div id="milestones-list" class="space-y-4">';
            include 'milestones_partial.php';
            echo '</div>';
            exit;
        }
        $tab_redirect = 'milestones';
    }
    // Upload File
    if (isset($_FILES['project_file'])) {
        $file = $_FILES['project_file'];
        if (validate_upload($file)) {
            $filename = escape($file['name']);
            $target_dir = "../assets/uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
                file_put_contents($target_dir . '.htaccess', "Deny from all\n<FilesMatch \"\.(jpg|jpeg|png|gif|pdf|docx|txt|zip)$\">\nAllow from all\n</FilesMatch>\nphp_flag engine off");
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_name = 'p' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_file = $target_dir . $new_name;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                db_query("INSERT INTO project_files (project_id, filename, filepath) VALUES ($id, '$filename', '$target_file')");
            }
        } else {
            set_flash('error', 'Invalid file type or dangerous content.');
        }

        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            $project_files = db_fetch_all("SELECT * FROM project_files WHERE project_id = $id ORDER BY uploaded_at DESC");
            include 'files_partial.php';
            exit;
        }
        $tab_redirect = 'files';
    }

    // Delete Project File
    if (isset($_POST['delete_project_file'])) {
        $file_id = (int)$_POST['delete_project_file'];
        $file = db_fetch_one("SELECT * FROM project_files WHERE id = $file_id AND project_id = $id");
        if ($file) {
            if (file_exists($file['filepath'])) {
                @unlink($file['filepath']);
            }
            db_query("DELETE FROM project_files WHERE id = $file_id");
            log_system_activity('File Delete', "Deleted project file #$file_id from project #$id");
        }
        
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            $project_files = db_fetch_all("SELECT * FROM project_files WHERE project_id = $id ORDER BY uploaded_at DESC");
            include 'files_partial.php';
            exit;
        }
        $tab_redirect = 'files';
    }
    // Time Tracking (Start/Stop Mock)
    if (isset($_POST['time_action'])) {
        $action = $_POST['time_action'];
        $desc = escape($_POST['description'] ?? 'Work session');
        if ($action == 'start') {
            db_query("INSERT INTO time_entries (project_id, start_time, description) VALUES ($id, NOW(), '$desc')");
        }
        $tab_redirect = 'time';
    }

    $url = get_project_url($project);
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    
    // Set Flash Messages based on action
    if (isset($_POST['save_task'])) set_flash('success', 'Task saved successfully!');
    if (isset($_POST['update_task_status_only'])) set_flash('success', 'Task status updated!');
    if (isset($_POST['update_description'])) set_flash('success', 'Project description updated!');
    if (isset($_POST['delete_task'])) set_flash('success', 'Task deleted successfully!');
    if (isset($_POST['add_milestone'])) set_flash('success', 'Milestone added successfully!');
    if (isset($_POST['toggle_milestone'])) set_flash('success', 'Milestone status updated!');
    if (isset($_POST['project_file'])) set_flash('success', 'File uploaded successfully!');
    if (isset($_POST['time_action'])) set_flash('success', 'Time entry started!');

    if (isset($_POST['time_action'])) set_flash('success', 'Time entry started!');

    // Post Comment
    if (isset($_POST['post_comment'])) {
        $msg = escape($_POST['comment_text']);
        $uid = $_SESSION['user_id'];
        if ($msg) {
            db_query("INSERT INTO project_comments (project_id, user_id, comment) VALUES ($id, $uid, '$msg')");
            set_flash('success', 'Comment posted!');
        }
        $tab_redirect = 'comments';
    }

    redirect($url . $sep . "tab=" . $tab_redirect);
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
// --- Auto-Migration for Schema Updates ---
// This block ensures the DB schema is up to date automatically
// check for deleted_at in tasks
$check_cols = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'deleted_at'");
if (mysqli_num_rows($check_cols) == 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE projects ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS system_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(50),
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}
// -----------------------------------------
require_once '../header.php';
?>


<!-- Header Section -->
<?php display_flash(); ?>
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <h2 class="text-3xl font-bold"><?php echo e($project['name']); ?></h2>
            <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'manager'])): ?>
            <div class="badge <?php echo get_kanban_source_badge($project['source'] ?? 'Direct'); ?>">
                <?php echo e($project['source'] ?? 'Direct'); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="text-sm breadcrumbs text-base-content/70">
            <ul>
                <li><a href="<?php echo APP_URL; ?>/projects/project_list.php">Projects</a></li>
                <?php if (in_array($_SESSION['role'], ['admin', 'manager']) || (isset($_SESSION['is_client']) && $_SESSION['is_client'])): ?>
                <li><?php echo htmlspecialchars($client['name']); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="flex gap-2">
        <?php if (has_permission('edit_project')): ?>
        <a href="<?php echo APP_URL; ?>/projects/project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-ghost tooltip" data-tip="Edit Project">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
        </a>
        <?php endif; ?>
        <a href="<?php echo APP_URL; ?>/projects/project_list.php" class="btn btn-primary">Back</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar / Stats -->
    <div class="lg:col-span-1 space-y-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-6">
                <h3 class="card-title text-xs uppercase font-bold text-base-content/50 mb-4">Project Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="text-xs text-base-content/70 mb-1">Status</div>
                        <div class="badge <?php 
                            echo match($project['status']) {
                                'Completed' => 'badge-success',
                                'In Progress' => 'badge-info',
                                'On Hold' => 'badge-warning',
                                default => 'badge-ghost'
                            };
                        ?>"><?php echo $project['status']; ?></div>
                    </div>

                    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'manager'])): ?>
                    <div>
                        <div class="text-xs text-base-content/70 mb-1">Budget</div>
                        <div class="font-bold text-lg"><?php echo format_money($project['budget']); ?></div>
                        <?php 
                            $net = calculate_project_net($project['budget'], $project['source'] ?? 'Direct');
                            $rate = get_tax_rate($project['source'] ?? 'Direct');
                            if ($rate > 0):
                        ?>
                            <div class="text-xs text-success font-bold">Net: <?php echo format_money($net); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div class="text-xs text-base-content/70 mb-1">Deadline</div>
                        <div class="flex items-center gap-2 <?php echo strtotime($project['deadline']) < time() && $project['status'] != 'Completed' ? 'text-error font-bold' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <?php echo date('M d, Y', strtotime($project['deadline'])); ?>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-base-content/70 mb-1">Progress</div>
                        <div class="flex items-center gap-2">
                            <progress class="progress progress-primary w-full" value="<?php echo $progress; ?>" max="100"></progress>
                            <span class="text-xs font-bold"><?php echo $progress; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="lg:col-span-3">
        <div class="overflow-x-auto w-full pb-2"> <!-- Added overflow container -->
            <div role="tablist" class="tabs tabs-lifted tabs-lg mb-0 min-w-max"> <!-- Added min-w-max -->
                <a role="tab" class="tab <?php echo $active_tab == 'overview' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=overview" hx-push-url="true">Overview</a>
                <a role="tab" class="tab <?php echo $active_tab == 'description' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=description" hx-push-url="true">Description</a>
                <a role="tab" class="tab <?php echo $active_tab == 'milestones' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=milestones" hx-push-url="true">Milestones</a>
                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'manager'])): ?>
                <a role="tab" class="tab <?php echo $active_tab == 'reports' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=reports" hx-push-url="true">Reports</a>
                <?php endif; ?>
                <a role="tab" class="tab <?php echo $active_tab == 'time' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=time" hx-push-url="true">Time</a>
                <a role="tab" class="tab <?php echo $active_tab == 'files' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=files" hx-push-url="true">Files</a>
                <a role="tab" class="tab <?php echo $active_tab == 'chat' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=chat" hx-push-url="true">Chat</a>
                <a role="tab" class="tab <?php echo $active_tab == 'comments' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=comments" hx-push-url="true">Comments</a>
                <a role="tab" class="tab <?php echo $active_tab == 'activity' ? 'tab-active font-bold' : ''; ?>" hx-get="?id=<?php echo $id; ?>&tab=activity" hx-push-url="true">Activity</a>
            </div>
        </div>

        <div class="bg-base-100 border-base-300 rounded-b-box rounded-tr-box border p-6 min-h-[500px]">
             
            <!-- Description Tab -->
            <?php if ($active_tab == 'description'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Project Description</h3>
                </div>
                
                <form method="POST" class="space-y-4">
                    <?php csrf_field(); ?>
                    <div class="form-control">
                        <textarea name="description" class="textarea textarea-bordered h-64 text-base" placeholder="Enter project description..."><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="update_description" class="btn btn-primary">Save Description</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Overview Tab -->
            <?php if ($active_tab == 'overview'): ?>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Quick Tasks</h3>
                    <?php if (get_setting('ai_enabled') && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <button @click="$dispatch('open-ai-suggest-modal')" 
                                class="btn btn-xs btn-outline btn-primary gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            AI Suggest Tasks
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-4 mb-6">
                    <?php if(empty($tasks)): ?>
                        <div class="text-center py-6 text-base-content/50 italic">No tasks yet. Add one below!</div>
                    <?php endif; ?>

                    <?php foreach ($tasks as $task): ?>
                    <div class="card bg-base-100 border border-base-200 shadow-sm" x-data="taskItem(<?php echo $task['id']; ?>)">
                        <!-- Main Row -->
                        <div class="p-4 flex items-center gap-4 group hover:bg-base-200/50 transition-colors">
                            <label class="cursor-pointer">
                                <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" <?php echo $task['status'] == 'Done' ? 'checked' : ''; ?> disabled />
                            </label>
                            
                            <div class="flex-grow min-w-0 cursor-pointer" @click="toggleExpand">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold truncate <?php echo $task['status'] == 'Done' ? 'line-through opacity-50' : ''; ?>">
                                        <a href="../tasks/view_task.php?id=<?php echo $task['id']; ?>" class="link link-hover" @click.stop><?php echo e($task['title']); ?></a>
                                    </span>
                                    <span class="badge badge-xs <?php echo match($task['status']) { 'In Progress'=>'badge-info', 'Done'=>'badge-success', default=>'badge-ghost' }; ?>"><?php echo $task['status']; ?></span>
                                </div>
                                <div class="text-xs opacity-50 truncate">
                                    <?php echo $task['description'] ? strip_tags(substr($task['description'], 0, 60)).'...' : 'Click to view details'; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <!-- Quick Actions -->
                                <button @click.stop="toggleExpand('files')" class="btn btn-ghost btn-xs tooltip" data-tip="Upload/View Files">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                </button>
                                <button @click.stop="toggleExpand('chat')" class="btn btn-ghost btn-xs tooltip" data-tip="Chat">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                </button>
                                
                                <button onclick='openTaskModal(<?php echo $task['id']; ?>)' class="btn btn-ghost btn-xs text-info tooltip" data-tip="Edit Task">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                                <?php if (has_permission('delete_task')): ?>
                                <button onclick="openDeleteTaskModal(<?php echo $task['id']; ?>)" class="btn btn-ghost btn-xs hover:text-error tooltip" data-tip="Delete Task">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                                <?php endif; ?>
                                <button @click="toggleExpand" class="btn btn-ghost btn-xs tooltip" :data-tip="expanded ? 'Collapse' : 'Expand'">
                                    <svg x-show="!expanded" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    <svg x-show="expanded" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Expanded Section -->
                        <div x-show="expanded" x-collapse style="display: none;" class="bg-base-100 border-t border-base-200">
                            <div class="p-4">
                                <!-- Clean Tabs -->
                                <div role="tablist" class="tabs tabs-bordered w-full mb-6">
                                    <a role="tab" class="tab h-10" :class="{ 'tab-active font-bold border-primary': tab === 'overview' }" @click="tab = 'overview'">Description</a>
                                    <a role="tab" class="tab h-10" :class="{ 'tab-active font-bold border-primary': tab === 'files' }" @click="switchTab('files')">
                                        Files <span class="badge badge-xs badge-ghost ml-1" x-text="files.length" x-show="files.length > 0"></span>
                                    </a>
                                    <a role="tab" class="tab h-10" :class="{ 'tab-active font-bold border-primary': tab === 'chat' }" @click="switchTab('chat')">
                                        Chat <span class="badge badge-xs badge-ghost ml-1" x-text="messages.length" x-show="messages.length > 0"></span>
                                    </a>
                                    <a role="tab" class="tab h-10" :class="{ 'tab-active font-bold border-primary': tab === 'activity' }" @click="switchTab('activity')">Activity</a>
                                </div>

                                <!-- Tab Content -->
                                <div class="min-h-[200px]">
                                    <!-- Description Tab -->
                                    <div x-show="tab === 'overview'" class="animate-fade-in-up">
                                        <div class="prose prose-sm max-w-none text-base-content/80 mb-6">
                                            <?php echo $task['description'] ? nl2br(e($task['description'])) : '<span class="italic opacity-50">No additional details provided.</span>'; ?>
                                        </div>
                                        
                                        <!-- Meta Grid -->
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-base-200 rounded-lg text-sm">
                                            <div>
                                                <div class="opacity-50 text-xs uppercase font-bold mb-1">Priority</div>
                                                <div class="font-medium <?php echo match($task['priority']) { 'High'=>'text-error', 'Medium'=>'text-warning', default=>'text-success' }; ?>">
                                                    <?php echo $task['priority']; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="opacity-50 text-xs uppercase font-bold mb-1">Due Date</div>
                                                <div class="font-medium"><?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No date'; ?></div>
                                            </div>
                                            <div>
                                                <div class="opacity-50 text-xs uppercase font-bold mb-1">Status</div>
                                                <div class="badge badge-sm badge-outline"><?php echo $task['status']; ?></div>
                                            </div>
                                            <div>
                                                <div class="opacity-50 text-xs uppercase font-bold mb-1">Progress</div>
                                                <div class="flex items-center gap-2">
                                                    <progress class="progress progress-primary w-16" value="<?php echo $task['progress'] ?? 0; ?>" max="100"></progress>
                                                    <span class="text-xs"><?php echo $task['progress'] ?? 0; ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Files Tab -->
                                    <div x-show="tab === 'files'" class="animate-fade-in-up">
                                        <div class="flex justify-between items-center mb-4">
                                            <h4 class="font-bold text-sm">Attached Files</h4>
                                            <div class="flex gap-2 items-center">
                                                <span x-show="loadingFiles" class="loading loading-spinner loading-xs text-primary"></span>
                                                <label class="btn btn-xs btn-primary gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                                    Upload
                                                    <input type="file" @change="uploadFile" class="hidden" />
                                                </label>
                                                <span x-show="uploading" class="loading loading-spinner loading-xs text-primary"></span>
                                            </div>
                                        </div>
                                        
                                        <div x-show="files.length === 0" class="text-center py-8 border-2 border-dashed border-base-300 rounded-lg text-base-content/50">
                                            No files attached yet.
                                        </div>

                                        <div class="grid grid-cols-1 gap-2">
                                            <template x-for="f in files" :key="f.id">
                                                <div class="flex justify-between items-center p-3 bg-base-100 border border-base-200 rounded-lg hover:border-primary/50 transition-colors group">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-sm" x-text="f.name"></div>
                                                            <div class="text-xs opacity-50" x-text="f.date"></div>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <a :href="f.url" download class="btn btn-ghost btn-xs text-primary" title="Download">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                                        </a>
                                                        <button @click="deleteFile(f.id)" class="btn btn-ghost btn-xs text-error" title="Delete">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Chat Tab -->
                                    <div x-show="tab === 'chat'" class="animate-fade-in-up">
                                        <div class="flex flex-col h-[300px]">
                                            <div class="flex-1 overflow-y-auto space-y-4 p-2 mb-4" x-ref="chatBox">
                                                <div x-show="loadingChat && messages.length === 0" class="h-full flex flex-col items-center justify-center">
                                                    <span class="loading loading-spinner loading-lg text-primary"></span>
                                                </div>
                                                <div x-show="!loadingChat && messages.length === 0" class="h-full flex flex-col items-center justify-center text-base-content/30">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                                    <span>No messages yet. Start the discussion!</span>
                                                </div>
                                                <template x-for="msg in messages" :key="msg.id">
                                                    <div class="chat" :class="msg.is_me ? 'chat-end' : 'chat-start'">
                                                        <div class="chat-image avatar placeholder">
                                                            <div class="bg-neutral text-neutral-content rounded-full w-8">
                                                                <span class="text-xs" x-text="msg.avatar_letter"></span>
                                                            </div>
                                                        </div>
                                                        <div class="chat-header text-xs opacity-50 mb-1">
                                                            <span x-text="msg.user_name"></span>
                                                            <time class="ml-1" x-text="msg.time"></time>
                                                        </div>
                                                        <div class="chat-bubble text-sm" :class="msg.is_me ? 'chat-bubble-primary' : 'chat-bubble-secondary'" x-text="msg.message"></div>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="flex gap-2 items-center bg-base-200 p-2 rounded-lg">
                                                <input type="text" x-model="newMessage" @keyup.enter="sendMessage" class="input input-sm border-0 bg-transparent flex-1 focus:outline-none" placeholder="Type a message..." />
                                                <button @click="sendMessage" class="btn btn-sm btn-circle btn-primary shadow-lg" :disabled="!newMessage.trim()">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Activity Tab -->
                                    <div x-show="tab === 'activity'" class="animate-fade-in-up">
                                         <div x-show="loadingActivity" class="flex justify-center py-8">
                                            <span class="loading loading-spinner text-primary"></span>
                                         </div>
                                         <ul class="steps steps-vertical w-full" x-show="!loadingActivity">
                                            <template x-for="log in activities" :key="log.id">
                                                <li class="step step-neutral">
                                                    <div class="text-left py-2">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-bold text-sm" x-text="log.user_name"></span>
                                                            <span class="text-xs opacity-50" x-text="log.date_formatted"></span>
                                                        </div>
                                                        <p class="text-sm text-base-content/80 mt-1" x-text="log.description"></p>
                                                    </div>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <script>
                // Tasks Data Map (Safe way to pass data)
                const tasksMap = <?php 
                    $json_tasks = [];
                    foreach($tasks as $t) $json_tasks[$t['id']] = $t;
                    echo json_encode($json_tasks, JSON_HEX_APOS | JSON_HEX_QUOT); 
                ?>;

                document.addEventListener('alpine:init', () => {
                    Alpine.data('taskItem', (taskId) => ({
                        expanded: false,
                        tab: 'overview',
                        files: [],
                        messages: [],
                        activities: [],
                        newMessage: '',
                        uploading: false,
                        loadingFiles: false,
                        loadingChat: false,
                        loadingActivity: false,
                        chatInterval: null,

                        toggleExpand(targetTab = null) {
                            this.expanded = !this.expanded;
                            if (this.expanded) {
                                if(targetTab && typeof targetTab === 'string') this.switchTab(targetTab);
                                else this.switchTab('overview'); // Default
                            } else {
                                clearInterval(this.chatInterval);
                            }
                        },

                        switchTab(newTab) {
                            this.tab = newTab;
                            if(newTab === 'files') this.loadFiles();
                            if(newTab === 'chat') {
                                this.loadChat();
                                clearInterval(this.chatInterval);
                                this.chatInterval = setInterval(() => this.loadChat(), 5000);
                            }
                            if(newTab === 'activity') this.loadActivity();
                        },

                        async loadFiles() {
                            this.loadingFiles = true;
                            try {
                                const fd = new FormData();
                                fd.append('action', 'get_files');
                                fd.append('task_id', taskId);
                                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                const res = await fetch('../tasks/task_actions.php', { method:'POST', body:fd });
                                const data = await res.json();
                                if(data.success) this.files = data.files;
                            } catch (error) {
                                console.error('Error loading files:', error);
                            } finally {
                                this.loadingFiles = false;
                            }
                        },

                        async uploadFile(e) {
                            const file = e.target.files[0];
                            if(!file) return;
                            this.uploading = true;
                            
                            try {
                                const fd = new FormData();
                                fd.append('task_id', taskId);
                                fd.append('file', file);
                                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                
                                const res = await fetch('../tasks/upload_file.php', { method:'POST', body:fd });
                                
                                let data;
                                const text = await res.text();
                                try {
                                    data = JSON.parse(text);
                                } catch(e) {
                                    throw new Error(`Invalid Server Response: ${text.substring(0, 100)}...`);
                                }

                                this.uploading = false;
                                
                                if (!res.ok || !data.success) {
                                     throw new Error(data.error || `Server Error: ${res.status}`);
                                }
                                
                                this.loadFiles();
                                e.target.value = ''; // Reset input
                                
                            } catch (error) {
                                this.uploading = false;
                                console.error('Upload Error:', error);
                                alert(`Error uploading file: ${error.message}`);
                            }
                        },

                        async deleteFile(fileId) {
                            if(!confirm('Are you sure you want to delete this file? This cannot be undone.')) return;
                            
                            try {
                                const fd = new FormData();
                                fd.append('action', 'delete_file');
                                fd.append('task_id', taskId); 
                                fd.append('file_id', fileId);
                                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                
                                const res = await fetch('../tasks/task_actions.php', { method:'POST', body:fd });
                                const data = await res.json();
                                
                                if(data.success) {
                                    this.loadFiles();
                                } else {
                                    alert('Failed to delete file: ' + (data.error || 'Unknown error'));
                                }
                            } catch (error) {
                                console.error('Delete Error:', error);
                                alert('Error deleting file');
                            }
                        },

                        async loadChat() {
                            this.loadingChat = true;
                            try {
                                const fd = new FormData();
                                fd.append('action', 'get_chat');
                                fd.append('task_id', taskId);
                                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                const res = await fetch('../tasks/task_actions.php', { method:'POST', body:fd });
                                const data = await res.json();
                                if(data.success) {
                                    this.messages = data.messages;
                                    this.$nextTick(() => {
                                        if(this.$refs.chatBox) this.$refs.chatBox.scrollTop = this.$refs.chatBox.scrollHeight;
                                    });
                                }
                            } catch(e) { console.warn(e); }
                            finally { this.loadingChat = false; }
                        },

                        async sendMessage() {
                            if(!this.newMessage.trim()) return;
                            try {
                                const fd = new FormData();
                                fd.append('action', 'send_chat');
                                fd.append('task_id', taskId);
                                fd.append('message', this.newMessage);
                                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                this.newMessage = '';
                                await fetch('../tasks/task_actions.php', { method:'POST', body:fd });
                                this.loadChat();
                            } catch(e) { alert('Failed to send message'); }
                        },

                        async loadActivity() {
                            this.loadingActivity = true;
                            try {
                                const fd = new FormData();
                                fd.append('action', 'get_activity');
                                fd.append('task_id', taskId);
                                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                const res = await fetch('../tasks/task_actions.php', { method:'POST', body:fd });
                                const data = await res.json();
                                if(data.success) this.activities = data.prop_logs;
                            } catch(e) { console.warn(e); }
                            finally { this.loadingActivity = false; }
                        }
                    }));
                });
                </script>

                <!-- Add Task Button -->
                <button onclick="openTaskModal()" class="btn btn-primary w-full mb-4">+ Add New Task</button>

                <!-- Comprehensive Task Modal -->
                <dialog id="task_modal" class="modal">
                    <div class="modal-box w-11/12 max-w-3xl">
                        <h3 class="font-bold text-lg" id="modal_title">Create Task</h3>
                        <form method="POST" class="mt-4">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="save_task" value="1">
                            <input type="hidden" name="task_id" id="modal_task_id" value="0">
                            
                            <div class="form-control mb-4">
                                <label class="label"><span class="label-text">Task Title <span class="text-error">*</span></span></label>
                                <input type="text" name="title" id="modal_task_title" class="input input-bordered" required />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Assign To</span></label>
                                    <select name="assigned_to" id="modal_assigned_to" class="select select-bordered">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Due Date</span></label>
                                    <input type="date" name="due_date" id="modal_due_date" class="input input-bordered" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Priority</span></label>
                                    <select name="priority" id="modal_priority" class="select select-bordered">
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Status</span></label>
                                    <select name="status" id="modal_status" class="select select-bordered">
                                        <option value="Todo">Todo</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="On Hold">On Hold</option>
                                        <option value="Done">Done</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-control w-full">
                    <label class="label"><span class="label-text">Description</span></label>
                    <textarea name="description" id="modal_description" class="textarea textarea-bordered h-24" placeholder="Task details..."></textarea>
                </div>
                
                <!-- Dependency -->
                 <div class="form-control w-full mt-2">
                    <label class="label"><span class="label-text">Blocked By (Dependency)</span><span class="label-text-alt text-gray-500">Optional</span></label>
                    <select name="dependency_id" id="modal_dependency_id" class="select select-bordered w-full">
                        <option value="">No Dependency</option>
                        <?php foreach($tasks as $t_opt): ?>
                            <option value="<?php echo $t_opt['id']; ?>"><?php echo htmlspecialchars($t_opt['title']); ?> (#<?php echo $t_opt['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-action">
                                <button type="submit" class="btn btn-primary">Save Task</button>
                                <button type="button" class="btn" onclick="document.getElementById('task_modal').close()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </dialog>

                <!-- Delete Task Modal -->
                <dialog id="delete_task_modal" class="modal">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg text-error">Delete Task</h3>
                        <p class="py-4">Are you sure you want to delete this task? This action cannot be undone.</p>
                        <form method="POST" class="modal-action">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="delete_task" id="delete_task_id_input">
                            <button class="btn btn-error">Yes, Delete</button>
                            <button type="button" class="btn" onclick="document.getElementById('delete_task_modal').close()">Cancel</button>
                        </form>
                    </div>
                </dialog>

                <script>
                // Validation Logic
                document.querySelector('dialog#task_modal form').addEventListener('submit', function(e) {
                    const title = document.getElementById('modal_task_title').value.trim();
                    if (!title) {
                        e.preventDefault();
                        alert('Please enter a Task Title.'); // Simple fallback, or custom toast
                        return;
                    }
                });

                function openTaskModal(taskIdOrObj = null) {
                    console.log('openTaskModal called with:', taskIdOrObj);
                    const modal = document.getElementById('task_modal');
                    const title = document.getElementById('modal_title');
                    
                    if (taskIdOrObj) {
                        // Edit Mode
                        title.textContent = "Edit Task";
                        
                        let task = taskIdOrObj;
                        if (typeof taskIdOrObj === 'number' || typeof taskIdOrObj === 'string') {
                            console.log('Lookup from map:', tasksMap[taskIdOrObj]);
                            task = tasksMap[taskIdOrObj];
                        }

                        if (!task) {
                            alert('Task data not found!');
                            return;
                        }

                        document.getElementById('modal_task_id').value = task.id;
                        document.getElementById('modal_task_title').value = task.title;
                        document.getElementById('modal_assigned_to').value = task.assigned_to || '';
                        document.getElementById('modal_priority').value = task.priority || 'Medium';
                        document.getElementById('modal_status').value = task.status || 'Todo';
                        document.getElementById('modal_due_date').value = task.due_date || '';
                        document.getElementById('modal_description').value = task.description || '';
                        document.getElementById('modal_dependency_id').value = task.dependency_id || '';
                        
                        modal.showModal();

                    } else {
                        // Create Mode
                        title.textContent = "Create New Task";
                        document.getElementById('modal_task_id').value = 0;
                        document.getElementById('modal_task_title').value = '';
                        document.getElementById('modal_assigned_to').value = '';
                        document.getElementById('modal_priority').value = 'Medium';
                        document.getElementById('modal_status').value = 'Todo';
                        document.getElementById('modal_due_date').value = '';
                        document.getElementById('modal_description').value = '';
                        document.getElementById('modal_dependency_id').value = '';
                        
                        modal.showModal();
                    }
                }



                function openDeleteTaskModal(id) {
                    document.getElementById('delete_task_id_input').value = id;
                    document.getElementById('delete_task_modal').showModal();
                }
                </script>
            <?php endif; ?>



            <!-- Time Tracking Tab -->
            <?php if ($active_tab == 'time'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Time Log</h3>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="time_action" value="start">
                        <button class="btn btn-sm btn-accent gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Start Timer
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead><tr><th>Date</th><th>Description</th><th>Duration</th></tr></thead>
                        <tbody>
                            <?php if (empty($time_entries)): ?>
                                <tr><td colspan="3" class="text-center text-base-content/50 py-8">No time logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($time_entries as $entry): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($entry['start_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                    <td class="font-mono">--:--</td> <!-- Placeholder for duration calc -->
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Comments/Discussions Tab -->
            <?php if ($active_tab == 'comments'): ?>
                <div class="flex flex-col h-[500px]">
                    <div class="flex-grow overflow-y-auto space-y-4 p-4 bg-base-100 rounded-box border border-base-200 mb-4">
                        <?php 
                        $comments = db_fetch_all("
                            SELECT c.*, u.name as user_name 
                            FROM project_comments c 
                            JOIN users u ON c.user_id = u.id 
                            WHERE c.project_id = $id 
                            ORDER BY c.created_at ASC
                        ");
                        if (empty($comments)): ?>
                            <div class="text-center text-gray-500 py-10">Start the discussion!</div>
                        <?php else: ?>
                            <?php foreach ($comments as $c): 
                                $is_me = ($c['user_id'] == $_SESSION['user_id']);
                            ?>
                                <div class="chat <?php echo $is_me ? 'chat-end' : 'chat-start'; ?>">
                                    <div class="chat-header text-xs opacity-50 mb-1">
                                        <?php echo htmlspecialchars($c['user_name']); ?>
                                        <time class="text-[10px] ml-1"><?php echo date('M d H:i', strtotime($c['created_at'])); ?></time>
                                    </div>
                                    <div class="chat-bubble <?php echo $is_me ? 'chat-bubble-primary' : 'chat-bubble-secondary'; ?>">
                                        <?php echo nl2br(htmlspecialchars($c['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="flex gap-2">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="post_comment" value="1">
                        <textarea name="comment_text" class="textarea textarea-bordered w-full" placeholder="Type your message here..." rows="2" required></textarea>
                        <button class="btn btn-primary self-end">Send</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Files Tab -->
            <?php if ($active_tab == 'files'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Files & Assets</h3>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8" id="project-files-grid">
                    <?php include 'files_partial.php'; ?>
                </div>
                
                <form hx-post="<?php echo $_SERVER['REQUEST_URI']; ?>" hx-target="#project-files-grid" hx-encoding="multipart/form-data" hx-on::after-request="this.reset()" class="card bg-base-100 border border-base-300">
                    <?php csrf_field(); ?>
                    <div class="card-body p-4">
                        <div class="form-control w-full">
                            <label class="label"><span class="label-text">Upload New File</span></label>
                            <div class="flex gap-2">
                                <input type="file" name="project_file" class="file-input file-input-bordered w-full" required />
                                <button class="btn btn-primary">Upload</button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Chat Tab -->
            <?php if ($active_tab == 'chat'): ?>
                <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
                <div class="flex flex-col h-[600px] bg-[#e5ded8] rounded-xl overflow-hidden relative" x-data="chatSystem(<?php echo $id; ?>)">
                    <!-- WhatsApp Background Pattern -->
                    <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');"></div>

                    <!-- Chat Header -->
                    <div class="flex justify-between items-center p-3 bg-[#008069] text-white z-10 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="avatar placeholder">
                                <div class="bg-white text-[#008069] rounded-full w-10">
                                    <span class="text-xl">👥</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm"><?php echo htmlspecialchars($project['name']); ?></h3>
                                <p class="text-xs opacity-80">
                                    <?php 
                                    if (in_array($_SESSION['role'], ['admin', 'manager'])) {
                                        echo htmlspecialchars($client['name']) . ", Admin, Team";
                                    } elseif (isset($_SESSION['is_client']) && $_SESSION['is_client']) {
                                        echo "Admin, Team";
                                    } else {
                                        echo "Client, Team";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <button class="btn btn-ghost btn-circle btn-sm text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </button>
                            <button class="btn btn-ghost btn-circle btn-sm text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-2 z-10 scrollbar-thin scrollbar-thumb-black/20" id="chat-messages" x-ref="chatContainer">
                        <template x-if="loading">
                            <div class="flex justify-center py-4">
                                <span class="loading loading-spinner loading-md text-[#008069]"></span>
                            </div>
                        </template>

                        <template x-for="msg in messages" :key="msg.id">
                            <div class="chat" :class="msg.is_me ? 'chat-end' : 'chat-start'">
                                <div class="chat-header mb-1">
                                    <span class="text-xs font-bold" 
                                          :class="{
                                              'text-[#008069]': msg.sender_type === 'admin',
                                              'text-[#d62929]': msg.sender_type === 'client',
                                              'text-[#3b82f6]': msg.sender_type === 'member',
                                              'hidden': msg.is_me
                                          }" 
                                          x-text="msg.sender_name"></span>
                                </div>
                                <div class="chat-bubble shadow-sm text-sm relative pb-5 min-w-[120px]" 
                                     :class="msg.is_me ? 'bg-[#d9fdd3] text-black' : 'bg-white text-black'">
                                    <div x-show="msg.attachment_path" class="mb-2">
                                        <template x-if="msg.attachment_type === 'image'">
                                            <a :href="'<?php echo APP_URL; ?>/' + msg.attachment_path" target="_blank">
                                                <img :src="'<?php echo APP_URL; ?>/' + msg.attachment_path" class="rounded-lg max-w-[200px] max-h-[200px] object-cover border border-base-300" alt="Attachment">
                                            </a>
                                        </template>
                                        <template x-if="msg.attachment_type !== 'image'">
                                            <a :href="'<?php echo APP_URL; ?>/' + msg.attachment_path" target="_blank" class="flex items-center gap-2 p-2 bg-gray-100 rounded-lg text-xs hover:bg-gray-200 transaction">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                                <span class="truncate max-w-[150px]">View Attachment</span>
                                            </a>
                                        </template>
                                    </div>
                                    <span x-text="msg.message" class="whitespace-pre-wrap"></span>
                                    <div class="absolute bottom-1 right-2 flex items-center gap-1">
                                        <time class="text-[10px] text-gray-500" x-text="msg.formatted_time"></time>
                                        <span x-show="msg.is_me" class="text-[#53bdeb] text-[10px]">✓✓</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <div x-show="messages.length === 0 && !loading" class="flex justify-center my-4">
                            <div class="bg-[#e1f3fb] text-xs text-center p-2 rounded-lg shadow-sm text-gray-600 max-w-xs">
                                🔒 Messages are end-to-end encrypted. No one outside of this chat, not even WhatsApp, can read or listen to them.
                            </div>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="p-2 bg-[#f0f2f5] z-10 relative">
                        
                        <!-- Emoji Picker Popover -->
                        <div x-show="showPicker" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-4"
                             @click.outside="showPicker = false"
                             class="absolute bottom-16 left-2 z-50 shadow-2xl rounded-xl overflow-hidden" 
                             style="display: none;">
                            <emoji-picker class="light" @emoji-click="insertEmoji($event)"></emoji-picker>
                        </div>

                        <!-- Attachment Preview -->
                        <div x-show="attachment" class="absolute bottom-16 left-12 z-40 bg-white p-2 rounded-lg shadow-xl border border-gray-200 flex items-center gap-2">
                             <template x-if="attachmentPreview">
                                <img :src="attachmentPreview" class="w-12 h-12 rounded object-cover border">
                             </template>
                             <template x-if="!attachmentPreview">
                                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                </div>
                             </template>
                             <div class="flex flex-col">
                                 <span class="text-xs font-bold truncate max-w-[100px]" x-text="attachment ? attachment.name : ''"></span>
                                 <span class="text-[10px] text-gray-500">Ready to send</span>
                             </div>
                             <button @click="cancelAttachment" type="button" class="btn btn-ghost btn-xs btn-circle text-error">✕</button>
                        </div>

                        <form @submit.prevent="sendMessage" class="flex gap-2 items-end">
                            <input type="file" x-ref="fileInput" @change="handleFileSelect" class="hidden" accept="image/*,.pdf,.doc,.docx,.zip,.txt">
                            
                            <button type="button" @click="showPicker = !showPicker" class="btn btn-ghost btn-circle btn-sm text-gray-500 hover:bg-gray-200/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </button>
                            <button type="button" @click="selectFile" class="btn btn-ghost btn-circle btn-sm text-gray-500 hover:bg-gray-200/50" :class="attachment ? 'text-primary' : ''">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            </button>
                            <div class="flex-1 bg-white rounded-lg px-4 py-2 shadow-sm">
                                <textarea 
                                    x-model="newMessage" 
                                    class="w-full bg-transparent border-none focus:ring-0 resize-none text-sm max-h-24 outline-none" 
                                    placeholder="Type a message" 
                                    rows="1"
                                    @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"
                                    @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                ></textarea>
                            </div>
                            <button type="submit" class="btn btn-ghost btn-circle btn-sm text-[#008069]" :disabled="!newMessage.trim() && !attachment">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                <script>
                function chatSystem(projectId) {
                    return {
                        messages: [],
                        newMessage: '',
                        loading: true,
                        interval: null,
                        showPicker: false,
                        attachment: null,
                        attachmentPreview: null,

                        init() {
                            this.fetchMessages();
                            this.interval = setInterval(() => this.fetchMessages(), 3000); // Poll every 3s
                            
                            // Scroll to bottom on load
                            this.$watch('messages', () => {
                                this.$nextTick(() => {
                                    this.scrollToBottom();
                                });
                            });
                        },

                        async fetchMessages() {
                            try {
                                const response = await fetch(`<?php echo APP_URL; ?>/projects/chat_api.php?project_id=${projectId}`);
                                
                                // Check for non-JSON response
                                const contentType = response.headers.get("content-type");
                                if (!response.ok || !contentType || !contentType.includes("application/json")) {
                                    // Don't throw here to avoid spamming console/alerts on every poll if server is temporarily down
                                    // But do stop loading
                                    return; 
                                }

                                const data = await response.json();
                                if (data.success) {
                                    const wasAtBottom = this.isAtBottom();
                                    this.messages = data.messages;
                                    
                                    if (wasAtBottom || this.messages.length === 0) {
                                        this.$nextTick(() => this.scrollToBottom());
                                    }
                                }
                            } catch (error) {
                                console.error('Chat Error:', error);
                            } finally {
                                this.loading = false;
                            }
                        },

                        insertEmoji(event) {
                            const emoji = event.detail.unicode;
                            const textarea = document.querySelector('textarea[x-model="newMessage"]');
                            
                            // Insert at cursor position if possible
                            if (textarea) {
                                const start = textarea.selectionStart;
                                const end = textarea.selectionEnd;
                                this.newMessage = this.newMessage.substring(0, start) + emoji + this.newMessage.substring(end);
                                
                                // Restore focus and cursor
                                this.$nextTick(() => {
                                    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                                    textarea.focus();
                                    // Adjust height
                                    textarea.style.height = 'auto'; 
                                    textarea.style.height = textarea.scrollHeight + 'px';
                                });
                            } else {
                                this.newMessage += emoji;
                            }
                        },

                        selectFile() {
                            this.$refs.fileInput.click();
                        },

                        handleFileSelect(event) {
                            const file = event.target.files[0];
                            if (file) {
                                this.attachment = file;
                                if (file.type.startsWith('image/')) {
                                    const reader = new FileReader();
                                    reader.onload = e => this.attachmentPreview = e.target.result;
                                    reader.readAsDataURL(file);
                                } else {
                                    this.attachmentPreview = null;
                                }
                            }
                        },

                        cancelAttachment() {
                            this.attachment = null;
                            this.attachmentPreview = null;
                            this.$refs.fileInput.value = '';
                        },

                        async sendMessage() {
                            if (!this.newMessage.trim() && !this.attachment) return;
                            
                            const msg = this.newMessage;
                            const file = this.attachment;

                            // Optimistic UI for text only (skip if file)
                            if (!file) {
                                // optional: can implement optimistic update here
                            }

                            this.newMessage = ''; 
                            this.cancelAttachment(); // Clear attachment

                            // Reset height
                            const textarea = document.querySelector('textarea[x-model="newMessage"]');
                            if(textarea) textarea.style.height = 'auto';

                            try {
                                const formData = new FormData();
                                formData.append('project_id', projectId);
                                formData.append('message', msg);
                                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                if (file) {
                                    formData.append('attachment', file);
                                }

                                const response = await fetch('<?php echo APP_URL; ?>/projects/chat_api.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                
                                const contentType = response.headers.get("content-type");
                                if (!response.ok) {
                                    const text = await response.text();
                                    throw new Error("Server Error");
                                }

                                const result = await response.json();
                                if (result.success) {
                                    this.fetchMessages(); // Refresh immediately
                                } else {
                                    alert('Failed to send: ' + result.message);
                                    if(!file) this.newMessage = msg; // Restore if failed
                                }
                            } catch (error) {
                                console.error('Send Error:', error);
                                alert('Error sending message: ' + error.message);
                                if(!file) this.newMessage = msg;
                            }
                        },

                        scrollToBottom() {
                            const container = this.$refs.chatContainer;
                            if(container) container.scrollTop = container.scrollHeight;
                        },

                        isAtBottom() {
                            const container = this.$refs.chatContainer;
                            if(!container) return true;
                            return container.scrollHeight - container.scrollTop <= container.clientHeight + 150;
                        }
                    }
                }
                </script>
            <?php endif; ?>

            <!-- Comments Tab -->
            <?php if ($active_tab == 'comments'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Project Discussion</h3>
                </div>

                <!-- Comment Input -->
                <div class="card bg-base-100 border border-base-300 mb-8">
                    <div class="card-body p-4">
                        <form id="commentForm">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="project_id" value="<?php echo $id; ?>">
                            <div class="form-control">
                                <textarea name="content" class="textarea textarea-bordered h-24" placeholder="Post a comment or update..."></textarea>
                            </div>
                            <div class="flex justify-end mt-2">
                                <button type="submit" class="btn btn-primary btn-sm">Post Comment</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Comments List -->
                <div id="commentsList" class="space-y-4">
                    <!-- Loaded via AJAX -->
                    <div class="flex justify-center py-8">
                        <span class="loading loading-spinner loading-md"></span>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    loadComments();

                    document.getElementById('commentForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        
                        fetch('<?php echo APP_URL; ?>/collaboration/comments_api.php?action=post_comment', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.reset();
                                loadComments();
                            } else {
                                alert('Error: ' + data.error);
                            }
                        });
                    });
                });

                function loadComments() {
                    fetch('<?php echo APP_URL; ?>/collaboration/comments_api.php?action=get_comments&project_id=<?php echo $id; ?>')
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('commentsList');
                        container.innerHTML = '';
                        
                        if (data.comments.length === 0) {
                            container.innerHTML = '<div class="text-center opacity-50 py-8">No comments yet.</div>';
                            return;
                        }

                        data.comments.forEach(c => {
                            container.innerHTML += `
                                <div class="card bg-base-100 border border-base-200 shadow-sm">
                                    <div class="card-body p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div class="flex items-center gap-2">
                                                <div class="avatar placeholder">
                                                    <div class="bg-neutral-focus text-neutral-content rounded-full w-8">
                                                        <span class="uppercase">${c.user_name.substr(0,1)}</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm">${c.user_name}</div>
                                                    <div class="text-[10px] opacity-50 uppercase">${c.role}</div>
                                                </div>
                                            </div>
                                            <div class="text-xs opacity-50">${c.created_at}</div>
                                        </div>
                                        <div class="text-sm whitespace-pre-wrap">${c.content}</div>
                                    </div>
                                </div>
                            `;
                        });
                    });
                }
                </script>
            <?php endif; ?>

            <!-- Milestones Tab -->
            <?php if ($active_tab == 'milestones'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Project Milestones</h3>
                    <button onclick="add_milestone_modal.showModal()" class="btn btn-sm btn-primary">Add Milestone</button>
                </div>

                <div id="milestones-list" class="space-y-4">
                    <?php include 'milestones_partial.php'; ?>
                </div>

                <!-- Add Milestone Modal -->
                <dialog id="add_milestone_modal" class="modal">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg mb-4">Add New Milestone</h3>
                        <form hx-post="<?php echo $_SERVER['REQUEST_URI']; ?>" hx-target="#milestones-list" hx-on::after-request="add_milestone_modal.close()" class="space-y-4">
                            <?php csrf_field(); ?>
                            <div class="form-control">
                                <label class="label"><span class="label-text">Milestone Title</span></label>
                                <input type="text" name="title" class="input input-bordered" required>
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text">Due Date</span></label>
                                <input type="date" name="due_date" class="input input-bordered" required>
                            </div>
                            <div class="modal-action">
                                <button type="button" onclick="add_milestone_modal.close()" class="btn">Cancel</button>
                                <button type="submit" name="add_milestone" class="btn btn-primary">Save Milestone</button>
                            </div>
                        </form>
                    </div>
                </dialog>
            <?php endif; ?>

            <!-- Reports Tab -->
            <?php if ($active_tab == 'reports'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Project Reports & Analytics</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Financial Summary -->
                    <div class="card bg-base-200 border border-base-300">
                        <div class="card-body">
                            <h4 class="font-bold text-md mb-4 flex items-center gap-2 text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Financial Summary
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between border-b border-base-300 pb-2">
                                    <span class="opacity-70">Total Budget (Gross)</span>
                                    <span class="font-mono"><?php echo format_money($project['budget']); ?></span>
                                </div>
                                <?php 
                                    $source = $project['source'] ?? 'Direct';
                                    $tax_rate = get_tax_rate($source);
                                    $tax_amount = $project['budget'] * $tax_rate;
                                    $net_amount = calculate_project_net($project['budget'], $source);
                                ?>
                                <div class="flex justify-between border-b border-base-300 pb-2">
                                    <span class="opacity-70">Platform Fee / Tax (<?php echo $tax_rate * 100; ?>%)</span>
                                    <span class="font-mono text-error">-<?php echo format_money($tax_amount); ?></span>
                                </div>
                                <div class="flex justify-between font-bold pt-2">
                                    <span>Net Potential Income</span>
                                    <span class="font-mono text-success text-xl"><?php echo format_money($net_amount); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Analytics -->
                    <div class="card bg-base-200 border border-base-300">
                        <div class="card-body">
                            <h4 class="font-bold text-md mb-4 flex items-center gap-2 text-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                Progress Analytics
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Overall Progress</span>
                                        <span class="font-bold"><?php echo $progress; ?>%</span>
                                    </div>
                                    <progress class="progress progress-primary w-full h-3" value="<?php echo $progress; ?>" max="100"></progress>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div class="bg-base-100 p-3 rounded-lg text-center">
                                        <div class="text-xs opacity-50">Milestones</div>
                                        <div class="text-lg font-bold"><?php echo $completed_milestones; ?>/<?php echo $total_milestones; ?></div>
                                    </div>
                                    <div class="bg-base-100 p-3 rounded-lg text-center">
                                        <div class="text-xs opacity-50">Tasks Done</div>
                                        <?php 
                                            $done_tasks = count(array_filter($tasks, fn($t) => $t['status'] == 'Done'));
                                            $total_t = count($tasks);
                                        ?>
                                        <div class="text-lg font-bold"><?php echo $done_tasks; ?>/<?php echo $total_t; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Time Stats -->
                    <div class="card bg-base-200 border border-base-300 md:col-span-2">
                        <div class="card-body">
                            <h4 class="font-bold text-md mb-4 flex items-center gap-2 text-accent">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Time Investment Summary
                            </h4>
                            <?php 
                                $total_seconds = 0;
                                foreach($time_entries as $te) {
                                    if($te['end_time']) {
                                        $total_seconds += (strtotime($te['end_time']) - strtotime($te['start_time']));
                                    }
                                }
                                $total_hrs = round($total_seconds / 3600, 1);
                                $hourly_rate = $total_hrs > 0 ? round($net_amount / $total_hrs, 2) : 0;
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="stat p-4 bg-base-100 rounded-xl shadow-sm">
                                    <div class="stat-title text-xs">Total Logged</div>
                                    <div class="stat-value text-xl"><?php echo $total_hrs; ?> hrs</div>
                                </div>
                                <div class="stat p-4 bg-base-100 rounded-xl shadow-sm">
                                    <div class="stat-title text-xs">Estimated Hourly Rate</div>
                                    <div class="stat-value text-xl text-success"><?php echo format_money($hourly_rate); ?>/hr</div>
                                </div>
                                <div class="stat p-4 bg-base-100 rounded-xl shadow-sm">
                                    <div class="stat-title text-xs">Efficiency Level</div>
                                    <div class="stat-value text-xl <?php echo $hourly_rate > 30 ? 'text-primary' : 'text-warning'; ?>">
                                        <?php echo $hourly_rate > 30 ? 'Premium' : ($hourly_rate > 0 ? 'Standard' : 'N/A'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Activity Tab -->
            <?php if ($active_tab == 'activity'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Activity Log</h3>
                </div>

                <?php 
                $logs = db_fetch_all("SELECT a.*, u.name as user_name 
                                      FROM activity_log a 
                                      JOIN users u ON a.user_id = u.id 
                                      WHERE (resource_type = 'Project' AND resource_id = $id) 
                                         OR (resource_type = 'Task' AND resource_id IN (SELECT id FROM tasks WHERE project_id = $id))
                                      ORDER BY a.created_at DESC LIMIT 50");
                ?>

                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($logs)): ?>
                                <tr><td colspan="4" class="text-center opacity-50 py-8">No activity recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td class="font-bold text-xs"><?php echo htmlspecialchars($log['user_name']); ?></td>
                                    <td><span class="badge badge-ghost badge-sm"><?php echo $log['action']; ?></span></td>
                                    <td class="text-sm"><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td class="text-xs opacity-50"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>


<!-- AI Suggest Tasks Modal -->
<?php if (get_setting('ai_enabled')): ?>
<div x-data="aiSuggestions(<?php echo $id; ?>)" 
     @open-ai-suggest-modal.window="openModal()"
     x-show="show" 
     class="modal modal-open" 
     style="display: none;"
     x-cloak>
    <div class="modal-box max-w-2xl bg-base-100 rounded-3xl border border-primary/20 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-2xl flex items-center gap-2">
                <div class="p-2 bg-primary/10 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                </div>
                AI Task Breakdown
            </h3>
            <button @click="show = false" class="btn btn-sm btn-circle btn-ghost">✕</button>
        </div>

        <div x-show="loading" class="flex flex-col items-center justify-center py-12 animate-pulse">
            <span class="loading loading-spinner loading-lg text-primary mb-4"></span>
            <p class="text-sm font-medium opacity-60 italic">Boss is analyzing project requirements...</p>
        </div>

        <div x-show="!loading" class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
            <template x-for="(task, index) in suggestions" :key="index">
                <div class="p-4 bg-base-200 rounded-2xl flex items-center justify-between group hover:bg-primary/5 transition-all">
                    <div class="flex-1 mr-4">
                        <div class="font-bold text-sm" x-text="task.title"></div>
                        <div class="text-xs opacity-60 mt-1" x-text="task.description"></div>
                        <div class="badge badge-xs mt-2" :class="task.priority === 'High' ? 'badge-error' : 'badge-ghost'" x-text="task.priority"></div>
                    </div>
                    <input type="checkbox" x-model="task.selected" class="checkbox checkbox-primary" />
                </div>
            </template>
        </div>

        <div class="modal-action flex justify-between items-center mt-8">
            <div class="text-xs opacity-50 italic">AI suggested 3-5 technical goals.</div>
            <div class="flex gap-2">
                <button @click="show = false" class="btn btn-ghost">Cancel</button>
                <button @click="applySuggestions()" 
                        :disabled="suggestions.length === 0 || applying"
                        class="btn btn-primary">
                    <span x-show="applying" class="loading loading-spinner loading-xs mr-2"></span>
                    Apply & Create Tasks
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function aiSuggestions(projectId) {
    return {
        show: false,
        loading: false,
        applying: false,
        suggestions: [],

        openModal() {
            this.show = true;
            this.fetchSuggestions();
        },

        fetchSuggestions() {
            this.loading = true;
            this.suggestions = [];
            
            fetch('<?php echo APP_URL; ?>/ai/chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    command: "Analyze the project ID " + projectId + ". Suggest 4 technical tasks. Return ONLY a JSON array of objects with keys: title, description, priority." 
                })
            })
            .then(res => res.json())
            .then(data => {
                this.loading = false;
                if (data.success && data.response.data) {
                    this.suggestions = data.response.data.map(t => ({...t, selected: true}));
                } else if (data.success && Array.isArray(data.response)) {
                    this.suggestions = data.response.map(t => ({...t, selected: true}));
                } else {
                    console.error('AI Suggestion raw:', data);
                    alert('AI could not parse requirements. Try adding more detail to project description.');
                }
            });
        },

        async applySuggestions() {
            this.applying = true;
            const selected = this.suggestions.filter(s => s.selected);
            
            for (const task of selected) {
                await fetch('<?php echo APP_URL; ?>/ai/execute_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'create_task', 
                        data: { project_id: projectId, title: task.title, description: task.description, priority: task.priority } 
                    })
                });
            }
            
            this.applying = false;
            this.show = false;
            window.location.reload();
        }
    }
}
</script>
<?php endif; ?>

<?php require_once '../footer.php'; ?>
