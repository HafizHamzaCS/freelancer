<?php
require_once '../config.php';
require_once '../functions.php';

// Handle Start Timer
if (isset($_POST['start_timer'])) {
    $project_id = (int)$_POST['project_id'];
    $description = escape($_POST['description']);
    $start_time = date('Y-m-d H:i:s');
    
    // Stop any currently running timer first? For simplicity, allow multiple or just one.
    // Let's assume one active timer at a time per user (but we don't track user_id in time_entries yet, just project_id. I'll assume single user app for now).
    
    mysqli_query($conn, "INSERT INTO time_entries (project_id, start_time, description) VALUES ($project_id, '$start_time', '$description')");
    redirect('projects/project_time-tracker.php');
}

// Handle Stop Timer
if (isset($_POST['stop_timer'])) {
    $entry_id = (int)$_POST['entry_id'];
    $end_time = date('Y-m-d H:i:s');
    
    mysqli_query($conn, "UPDATE time_entries SET end_time = '$end_time' WHERE id = $entry_id");
    redirect('projects/project_time-tracker.php');
}

require_once '../header.php';

// Fetch Active Timer
$active_timer = db_fetch_one("SELECT t.*, p.name as project_name FROM time_entries t JOIN projects p ON t.project_id = p.id WHERE t.end_time IS NULL ORDER BY t.start_time DESC LIMIT 1");

// Fetch Recent Entries
$recent_entries = db_fetch_all("SELECT t.*, p.name as project_name FROM time_entries t JOIN projects p ON t.project_id = p.id WHERE t.end_time IS NOT NULL ORDER BY t.end_time DESC LIMIT 10");

$projects = db_fetch_all("SELECT * FROM projects WHERE status = 'In Progress'");
?>

<div class="max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold mb-6">Time Tracker</h2>

    <!-- Active Timer / Start New -->
    <div class="card bg-base-100 shadow-xl mb-8 border-l-8 <?php echo $active_timer ? 'border-success' : 'border-primary'; ?>">
        <div class="card-body">
            <?php if ($active_timer): ?>
                <div class="flex justify-between items-center">
                    <div>
                        <div class="text-sm text-gray-500">Currently Working On</div>
                        <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($active_timer['project_name']); ?></h3>
                        <p class="opacity-70"><?php echo htmlspecialchars($active_timer['description']); ?></p>
                        <div class="text-xl font-mono mt-2" x-data="{ start: new Date('<?php echo $active_timer['start_time']; ?>'), current: new Date() }" x-init="setInterval(() => current = new Date(), 1000)">
                            <span x-text="new Date(current - start).toISOString().substr(11, 8)">00:00:00</span>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="entry_id" value="<?php echo $active_timer['id']; ?>">
                        <button type="submit" name="stop_timer" class="btn btn-error btn-lg">Stop Timer</button>
                    </form>
                </div>
            <?php else: ?>
                <form method="POST" class="flex gap-4 items-end">
                    <div class="form-control flex-1">
                        <label class="label"><span class="label-text">Project</span></label>
                        <select name="project_id" class="select select-bordered w-full" required>
                            <option value="">Select Project...</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control flex-2 w-full">
                        <label class="label"><span class="label-text">Description</span></label>
                        <input type="text" name="description" placeholder="What are you working on?" class="input input-bordered w-full" />
                    </div>
                    <button type="submit" name="start_timer" class="btn btn-primary">Start Timer</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Entries -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h3 class="card-title text-lg mb-4">Recent Activity</h3>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_entries)): ?>
                            <tr><td colspan="4" class="text-center text-gray-500">No time entries recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_entries as $entry): ?>
                            <tr>
                                <td class="font-bold"><?php echo htmlspecialchars($entry['project_name']); ?></td>
                                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($entry['start_time'])); ?></td>
                                <td class="font-mono">
                                    <?php 
                                        $start = new DateTime($entry['start_time']);
                                        $end = new DateTime($entry['end_time']);
                                        echo $start->diff($end)->format('%H:%I:%S');
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
