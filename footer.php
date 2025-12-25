<?php if (is_logged_in()): ?>
    </div> <!-- End Page Content -->
  </div> 
  <div class="drawer-side">
    <label for="my-drawer-2" aria-label="close sidebar" class="drawer-overlay"></label> 
    <ul class="menu p-4 w-80 min-h-full bg-base-100 text-base-content">
      <!-- Sidebar content here -->
      <li class="mb-4 text-2xl font-bold text-primary px-4"><?php echo APP_NAME; ?></li>
      
      <?php if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']): ?>
      <li><a href="<?php echo APP_URL; ?>/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
        Dashboard
      </a></li>

      <li>
        <details <?php echo strpos($current_page, 'project') !== false ? 'open' : ''; ?>>
          <summary>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
            Projects
          </summary>
          <ul>
            <li><a href="<?php echo APP_URL; ?>/projects/project_list.php" class="<?php echo $current_page == 'project_list.php' ? 'active' : ''; ?>">All Projects</a></li>
            <?php if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']): ?>
            <li><a href="<?php echo APP_URL; ?>/projects/project_add.php" class="<?php echo $current_page == 'project_add.php' ? 'active' : ''; ?>">Add New</a></li>
            <li><a href="<?php echo APP_URL; ?>/projects/project_kanban.php" class="<?php echo $current_page == 'project_kanban.php' ? 'active' : ''; ?>">Kanban Board</a></li>
            <?php endif; ?>
          </ul>
        </details>
      </li>

      <li><a href="<?php echo APP_URL; ?>/teams/index.php" class="<?php echo strpos($current_page, 'teams') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        Teams
      </a></li>

      <li><a href="<?php echo APP_URL; ?>/users/index.php" class="<?php echo strpos($current_page, 'users') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
        Users
      </a></li>
      
      <li>
        <details <?php echo strpos($current_page, 'client') !== false ? 'open' : ''; ?>>
          <summary>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            Clients
          </summary>
          <ul>
            <li><a href="<?php echo APP_URL; ?>/clients/client_list.php" class="<?php echo $current_page == 'client_list.php' ? 'active' : ''; ?>">All Clients</a></li>
            <li><a href="<?php echo APP_URL; ?>/clients/client_add.php" class="<?php echo $current_page == 'client_add.php' ? 'active' : ''; ?>">Add New</a></li>
            <li><a href="<?php echo APP_URL; ?>/clients/client_import.php" class="<?php echo $current_page == 'client_import.php' ? 'active' : ''; ?>">Import CSV</a></li>
            <li><a href="<?php echo APP_URL; ?>/clients/client_follow-up.php" class="<?php echo $current_page == 'client_follow-up.php' ? 'active' : ''; ?>">Follow Ups</a></li>
          </ul>
        </details>
      </li>
      <?php endif; ?>




      <?php if (!isset($_SESSION['is_client']) || !$_SESSION['is_client']): ?>
      
      <!-- Tasks Module -->
      <li><a href="<?php echo APP_URL; ?>/tasks/index.php" class="<?php echo strpos($current_page, 'tasks') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
        Tasks
      </a></li>

      <!-- Invoices Module -->
      <li><a href="<?php echo APP_URL; ?>/invoices/invoice_list.php" class="<?php echo strpos($current_page, 'invoice_list.php') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        Invoices
      </a></li>

      <!-- Reports Module -->
      <li><a href="<?php echo APP_URL; ?>/reports/index.php" class="<?php echo strpos($current_page, 'reports') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
        Reports
      </a></li>

      <!-- Workflows Module -->
      <li><a href="<?php echo APP_URL; ?>/workflows/index.php" class="<?php echo strpos($current_page, 'workflows') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
        Workflows
      </a></li>

      <li><a href="<?php echo APP_URL; ?>/promotions.php" class="<?php echo $current_page == 'promotions.php' ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
        Promotions
      </a></li>

      <li><a href="<?php echo APP_URL; ?>/events/index.php" class="<?php echo strpos($current_page, 'events') !== false ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        Events
      </a></li>

      <li><a href="<?php echo APP_URL; ?>/settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        Settings
      </a></li>
      <?php endif; ?>
    </ul>
  
  </div>
</div>
<?php endif; ?>
</body>
</html>
