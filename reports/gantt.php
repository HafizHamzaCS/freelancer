<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role('admin');

$page_title = "Gantt Timeline";
require_once '../header.php';

// Fetch Active Projects
$projects = db_fetch_all("SELECT * FROM projects WHERE status != 'Completed' ORDER BY start_date ASC");

// Calculate timeline range
$min_date = time();
$max_date = time();

foreach($projects as $p) {
    if($p['start_date']) {
        $t = strtotime($p['start_date']);
        if($t < $min_date) $min_date = $t;
    }
    if($p['deadline']) {
        $t = strtotime($p['deadline']);
        if($t > $max_date) $max_date = $t;
    }
}

// Add buffer
$min_date = strtotime('-1 week', $min_date);
$max_date = strtotime('+4 weeks', $max_date); // Ensure future visibility
$total_days = ($max_date - $min_date) / 86400;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <div class="text-sm breadcrumbs opacity-50">
            <ul>
                <li><a href="index.php">Reports</a></li>
                <li>Gantt</li>
            </ul>
        </div>
        <h1 class="text-3xl font-bold mt-2">Project Timelines</h1>
    </div>
</div>

<div class="card bg-base-100 shadow-xl overflow-x-auto">
    <div class="card-body min-w-[1000px]">
        
        <!-- Timeline Header -->
        <div class="flex border-b border-base-200 pb-2 mb-4">
            <div class="w-48 shrink-0 font-bold opacity-50">Project</div>
            <div class="flex-1 reltaive h-6">
                <!-- Months / Weeks markers could go here -->
                <div class="absolute left-0 text-xs opacity-50"><?php echo date('M d', $min_date); ?></div>
                <div class="absolute right-0 text-xs opacity-50"><?php echo date('M d', $max_date); ?></div>
            </div>
        </div>

        <!-- Gantt Bars -->
        <div class="space-y-4">
            <?php foreach($projects as $p): 
                if(!$p['start_date'] || !$p['deadline']) continue;
                
                $start = strtotime($p['start_date']);
                $end = strtotime($p['deadline']);
                
                // Calculate position percentage
                $left_percent = max(0, (($start - $min_date) / 86400) / $total_days * 100);
                $width_percent = (($end - $start) / 86400) / $total_days * 100;
                
                // Cap width
                if(($left_percent + $width_percent) > 100) $width_percent = 100 - $left_percent;
            ?>
            <div class="flex items-center group">
                <div class="w-48 shrink-0 truncate pr-4 text-sm font-bold group-hover:text-primary transition-colors">
                    <a href="../projects/project_view.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                </div>
                <div class="flex-1 relative h-8 bg-base-200 rounded-full overflow-hidden">
                    <div class="absolute top-0 bottom-0 bg-primary/80 rounded-full shadow-sm group-hover:bg-primary transition-colors flex items-center px-2"
                         style="left: <?php echo $left_percent; ?>%; width: <?php echo $width_percent; ?>%;">
                         <span class="text-[10px] text-white font-bold truncate">
                            <?php echo date('M d', $start) . ' - ' . date('M d', $end); ?>
                         </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php require_once '../footer.php'; ?>
