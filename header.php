<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check auth on all pages except login
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page != 'login.php' && $current_page != 'logout.php') {
    check_auth();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>" hx-boost="true" hx-target="#main-content" hx-select="#main-content">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <?php
    // --- Self-Healing: Notifications Table ---
    $check_notif = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if (mysqli_num_rows($check_notif) == 0) {
        mysqli_query($conn, "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255),
            message TEXT,
            link VARCHAR(255),
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // --- Fetch Data for Header ---
    if (is_logged_in()) {
        $uid = $_SESSION['user_id'];
        // Notifications
        $unread_count = db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE user_id = $uid AND is_read = 0")['c'];
        $notifs = db_fetch_all("SELECT * FROM notifications WHERE user_id = $uid ORDER BY created_at DESC LIMIT 5");
        
        // Mark as Read Logic (Simple GET)
        if (isset($_GET['mark_read'])) {
            db_query("UPDATE notifications SET is_read = 1 WHERE user_id = $uid");
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); // Reload without query
            exit;
        }
    }
    
    // --- Breadcrumbs Logic ---
    $crumbs = [];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    
    $crumbs[] = ['name' => 'Home', 'url' => APP_URL . '/dashboard.php'];
    
    foreach ($segments as $key => $segment) {
        if ($segment == 'freelancer' || $segment == 'dashboard.php') continue; // Skip root/dashboard
        
        $name = ucfirst(str_replace(['.php', '_'], ['', ' '], $segment));
        $url = '#'; // Default to non-clickable if not mapped
        
        // Smart URL Builder
        if ($segment == 'projects') $url = APP_URL . '/projects/project_list.php';
        if ($segment == 'clients') $url = APP_URL . '/clients/client_list.php';
        if ($segment == 'users') $url = APP_URL . '/users/index.php';
        if ($segment == 'tasks') $url = APP_URL . '/tasks/index.php';
        if ($segment == 'invoices') $url = APP_URL . '/invoices/invoice_list.php';
        if ($segment == 'reports') $url = APP_URL . '/reports/index.php';
        if ($segment == 'events') $url = APP_URL . '/events/index.php';
        if ($segment == 'workflows') $url = APP_URL . '/workflows/index.php';
        if ($segment == 'teams') $url = APP_URL . '/teams/index.php';

        if ($segment == 'project_view.php' && isset($project)) {
            $name = $project['name'] ?? 'Project'; 
            $url = '#';
        }
        if ($segment == 'client_view.php' && isset($client)) {
            $name = 'Client view'; 
            $url = '#';
        }
        
        $crumbs[] = ['name' => $name, 'url' => $url];
    }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <style>
        /* Global HTMX Progress Bar */
        .htmx-indicator {
            display: none;
        }
        .htmx-request .htmx-indicator {
            display: block;
        }
        .htmx-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: #3b82f6;
            z-index: 10000;
            transition: width 0.3s ease-in-out;
        }
        .htmx-request .htmx-progress {
            width: 100%;
            animation: progress 2s infinite ease-in-out;
        }
        @keyframes progress {
            0% { width: 0%; left: 0; }
            50% { width: 70%; left: 15%; }
            100% { width: 0%; left: 100%; }
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme Toggle Logic
            const themeController = document.querySelector('.theme-controller');
            
            // Set initial state based on server-rendered data-theme
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                themeController.checked = true;
            }

            themeController.addEventListener('change', function() {
                const theme = this.checked ? 'dark' : 'light';
                
                // Update UI immediately
                document.documentElement.setAttribute('data-theme', theme);
                
                // Persist to Session
                fetch('<?php echo APP_URL; ?>/set_theme.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ theme: theme })
                });
            });

            function initPlugins() {
                flatpickr('input[type="date"]', {
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "F j, Y",
                    allowInput: true,
                    static: true
                });
            }

            // Initial call
            initPlugins();

            // HTMX: Update sidebar active state after page swap
            document.addEventListener('htmx:afterSwap', function(evt) {
                if (evt.detail.target.id === 'main-content') {
                    // Update sidebar active classes
                    const currentPath = window.location.pathname + window.location.search;
                    const sidebarLinks = document.querySelectorAll('.drawer-side .menu a');
                    
                    sidebarLinks.forEach(link => {
                        const linkUrl = new URL(link.href, window.location.origin);
                        const linkPath = linkUrl.pathname + linkUrl.search;
                        
                        if (currentPath === linkPath || (currentPath.includes(linkUrl.pathname) && linkUrl.pathname !== '/' && linkUrl.pathname !== '<?php echo parse_url(APP_URL, PHP_URL_PATH); ?>/')) {
                            link.classList.add('active');
                            const details = link.closest('details');
                            if (details) details.setAttribute('open', '');
                        } else {
                            link.classList.remove('active');
                        }
                    });

                    // Re-initialize plugins
                    initPlugins();
                    
                    // Close mobile drawer if open
                    const drawerToggle = document.getElementById('my-drawer-2');
                    if (drawerToggle) drawerToggle.checked = false;
                }
            });
        });
    </script>
    <style>
        [x-cloak] { display: none !important; }
        
        /* DARK MODE OVERRIDES */
        html[data-theme="dark"] {
            --bc: 100% 0 0;
            color: #ffffff;
        }

        /* 1. General Text Whitening */
        html[data-theme="dark"] :is(body, h1, h2, h3, h4, h5, h6, p, span, div, li, td, th, a) {
            color: #ffffff;
        }
        
        /* 2. Invert '.bg-white' containers to Dark */
        html[data-theme="dark"] .bg-white {
            background-color: #191e24 !important; /* Darker than base-100 */
            border-color: #383f47 !important;
            color: #ffffff !important;
        }
        
        /* 3. Handle Inputs/Selects explicitly */
        html[data-theme="dark"] :is(input, select, textarea) {
            background-color: #15191e !important; 
            border-color: #383f47 !important;
            color: #ffffff !important;
            border-width: 1px;
        }

         /* 4. Fix specific utility classes for text visibility */
        html[data-theme="dark"] [class*="text-gray-"] {
            color: #d1d5db !important; /* Light Gray */
        }
        
        html[data-theme="dark"] .text-base-content,
        html[data-theme="dark"] .text-base-content\/70 {
             color: #ffffff !important;
        }

        /* 5. Preserve semantic colors (Error/Success/Primary) if they are explicitly set */
        html[data-theme="dark"] .text-red-400,
        html[data-theme="dark"] .text-red-500,
        html[data-theme="dark"] .text-red-600,
        html[data-theme="dark"] .text-error {
            color: #ff6b6b !important;
        }
        
        /* 6. Pagination & Buttons adjustments */
        html[data-theme="dark"] .btn-active {
            color: #ffffff !important;
        }
        html[data-theme="dark"] .join-item.btn-active {
            background-color: #3abff8 !important; /* Primary Blue */
            border-color: #3abff8 !important;
            color: #000000 !important; /* Black text on blue usually, or white */
        }

        /* 7. Icon Colors on Pastel Backgrounds (Dashboard Cards) */
        /* Restore contrast by using darker shade of the background color for the icon */
        html[data-theme="dark"] .bg-red-100 { color: #dc2626; }    /* red-600 */
        html[data-theme="dark"] .bg-blue-100 { color: #2563eb; }   /* blue-600 */
        html[data-theme="dark"] .bg-green-100 { color: #16a34a; }  /* green-600 */
        html[data-theme="dark"] .bg-purple-100 { color: #9333ea; } /* purple-600 */
        html[data-theme="dark"] .bg-yellow-100 { color: #ca8a04; } /* yellow-600 */
        html[data-theme="dark"] .bg-orange-100 { color: #ea580c; } /* orange-600 */

        /* Ensure hover state turns icon white (as per default behavior) */
        html[data-theme="dark"] .group:hover .group-hover\:text-white {
            color: #ffffff !important;
        }
    </style>
    <script src="<?php echo APP_URL; ?>/assets/bulk-actions.js"></script>
</head>
<body class="bg-base-200 min-h-screen font-sans">
    <div class="htmx-progress htmx-indicator" id="global-progress"></div>

<?php if (is_logged_in()): ?>
<div class="drawer lg:drawer-open">
  <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
  <div class="drawer-content flex flex-col" id="main-content">
    <div class="htmx-progress htmx-indicator"></div>
    <!-- Navbar -->
    <div class="w-full navbar bg-base-100 shadow-sm lg:hidden">
      <div class="flex-none lg:hidden">
        <label for="my-drawer-2" aria-label="open sidebar" class="btn btn-square btn-ghost">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-6 h-6 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </label>
      </div> 
      <div class="flex-1 px-2 mx-2 text-xl font-bold text-primary"><?php echo APP_NAME; ?></div>
    </div>

    <!-- Page Content -->
    <div class="p-6">
        <!-- Top Bar (Desktop) -->
        <div class="hidden lg:flex flex-col mb-6 bg-base-100 p-4 rounded-lg shadow-sm">
            <div class="flex justify-between items-center w-full">
            <h1 class="text-2xl font-bold text-base-content"><?php echo isset($page_title) ? $page_title : ucfirst(str_replace(['.php', '_'], ['', ' '], $current_page)); ?></h1>
            <div class="flex items-center gap-4">
                <!-- Global Search -->
                <div class="form-control mr-2">
                    <form action="<?php echo APP_URL; ?>/search.php" method="GET" hx-target="#main-content" hx-select="#main-content" hx-push-url="true">
                        <input type="text" name="q" placeholder="Search..." class="input input-sm input-bordered w-24 md:w-auto" />
                    </form>
                </div>

                <!-- Dark Mode Toggle -->
                <label class="swap swap-rotate mr-2">
                    <input type="checkbox" class="theme-controller" value="dark" />
                    <svg class="swap-on fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>
                    <svg class="swap-off fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>
                </label>

                <!-- Notifications -->
                <div class="dropdown dropdown-end">
                    <button role="button" class="btn btn-ghost btn-circle">
                        <div class="indicator">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge badge-xs badge-primary indicator-item"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </div>
                    </button>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-80">
                        <li class="menu-title flex flex-row justify-between">
                            <span>Notifications</span>
                            <?php if($unread_count > 0): ?><a href="?mark_read=1" class="text-xs text-primary">Mark all read</a><?php endif; ?>
                        </li>
                        <?php if (empty($notifs)): ?>
                            <li><a class="text-gray-500">No notifications</a></li>
                        <?php else: ?>
                            <?php foreach ($notifs as $n): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($n['link']); ?>" class="<?php echo $n['is_read'] ? 'opacity-50' : 'font-bold'; ?>">
                                        <div class="flex flex-col">
                                            <span><?php echo htmlspecialchars($n['title']); ?></span>
                                            <span class="text-xs"><?php echo htmlspecialchars($n['message']); ?></span>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                        <div class="w-10 rounded-full">
                            <img alt="User" src="https://ui-avatars.com/api/?name=<?php echo urlencode(get_user_name()); ?>&background=random" />
                        </div>
                    </div>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-52">
                        <li><a href="<?php echo APP_URL; ?>/projects/project_list.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'projects/') !== false ? 'active' : ''; ?>">Projects</a></li>
                <li><a href="<?php echo APP_URL; ?>/tasks/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'tasks/') !== false ? 'active' : ''; ?>">Tasks</a></li>
                <li><a href="<?php echo APP_URL; ?>/clients/client_list.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'clients/') !== false ? 'active' : ''; ?>">Clients</a></li>
                <li><a href="<?php echo APP_URL; ?>/reports/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'reports/') !== false ? 'active' : ''; ?>">Reports</a></li>
                        <li><a href="<?php echo APP_URL; ?>/settings.php">Settings</a></li>
                        <li><a href="<?php echo APP_URL; ?>/workflows/index.php">Workflows</a></li>
                        <li><a href="<?php echo APP_URL; ?>/logout.php" hx-boost="false">Logout</a></li>
                    </ul>
                </div>
            </div>
                </div>

            <!-- Breadcrumbs -->
            <div class="text-sm breadcrumbs mt-2">
                <ul>
                    <?php foreach ($crumbs as $crumb): ?>
                        <li><a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
<?php endif; ?>
