<?php
require_once 'header.php';

$q = isset($_GET['q']) ? escape($_GET['q']) : '';
?>

<div class="max-w-6xl mx-auto p-4">
    <h2 class="text-2xl font-bold mb-6">Search Results for "<?php echo htmlspecialchars($q); ?>"</h2>

    <?php if ($q): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Projects -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title text-primary">Projects</h3>
                    <?php
                    $projects = db_fetch_all("SELECT * FROM projects WHERE name LIKE '%$q%' LIMIT 5");
                    if ($projects): ?>
                        <ul class="menu bg-base-100 w-full rounded-box">
                            <?php foreach ($projects as $p): ?>
                                <li><a href="<?php echo get_project_url($p); ?>"><?php echo htmlspecialchars($p['name']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500">No projects found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tasks -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title text-secondary">Tasks</h3>
                    <?php
                    $tasks = db_fetch_all("SELECT * FROM tasks WHERE title LIKE '%$q%' OR description LIKE '%$q%' LIMIT 5");
                    if ($tasks): ?>
                        <ul class="menu bg-base-100 w-full rounded-box">
                            <?php foreach ($tasks as $t): ?>
                                <li><a href="<?php echo APP_URL; ?>/projects/project_view.php?id=<?php echo $t['project_id']; ?>"><?php echo htmlspecialchars($t['title']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500">No tasks found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Clients -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title text-accent">Clients</h3>
                    <?php
                    $clients = db_fetch_all("SELECT * FROM clients WHERE name LIKE '%$q%' OR email LIKE '%$q%' LIMIT 5");
                    if ($clients): ?>
                        <ul class="menu bg-base-100 w-full rounded-box">
                            <?php foreach ($clients as $c): ?>
                                <li><a href="<?php echo get_client_url($c); ?>"><?php echo htmlspecialchars($c['name']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500">No clients found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Types (Users/Teams) -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Users & Teams</h3>
                    <?php
                    // Users
                    $users = db_fetch_all("SELECT * FROM users WHERE name LIKE '%$q%' OR email LIKE '%$q%' LIMIT 5");
                    // Teams (if table exists)
                    // $teams = db_fetch_all("SELECT * FROM teams WHERE name LIKE '%$q%' LIMIT 5");
                    
                    if ($users): ?>
                        <ul class="menu bg-base-100 w-full rounded-box">
                            <?php foreach ($users as $u): ?>
                                <li><a><?php echo htmlspecialchars($u['name']); ?> <span class="badge badge-sm"><?php echo $u['role']; ?></span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500">No users found.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <?php else: ?>
        <div class="alert alert-info">Please enter a search term above.</div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
