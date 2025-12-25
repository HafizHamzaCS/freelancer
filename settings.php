<?php
require_once 'header.php';
require_role(['admin', 'member']);

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_token();
    // Password Change Logic
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        $user_id = $_SESSION['user_id'];
        $user = db_fetch_one("SELECT password FROM users WHERE id = $user_id");
        
        if (password_verify($current, $user['password'])) {
            if ($new === $confirm) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = $user_id");
                log_system_activity('Settings', 'Changed password');
                $success = "Password updated successfully.";
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $clean_key = substr($key, 8); // Remove 'setting_' prefix
            $clean_value = escape($value);
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('$clean_key', '$clean_value') 
                    ON DUPLICATE KEY UPDATE setting_value = '$clean_value'";
            mysqli_query($conn, $sql);
        }
    }
    $success = "Settings saved successfully!";
}

// Fetch all settings
$settings_result = db_fetch_all("SELECT * FROM settings");
$settings = [];
foreach ($settings_result as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// Helper to get value safely
function val($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}
?>

<div class="max-w-6xl mx-auto" x-data="{ tab: 'general' }">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Settings</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success shadow-lg max-w-md">
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error shadow-lg max-w-md">
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Sidebar -->
        <div class="md:col-span-1">
            <ul class="menu bg-base-100 w-full rounded-box shadow-xl">
                <li><a @click="tab = 'general'" :class="{ 'active': tab === 'general' }">General</a></li>
                <li><a @click="tab = 'security'" :class="{ 'active': tab === 'security' }">Security</a></li>
                <li><a @click="tab = 'ai'" :class="{ 'active': tab === 'ai' }">AI Settings</a></li>
                <li><a @click="tab = 'smtp'" :class="{ 'active': tab === 'smtp' }">Email SMTP</a></li>
                <li><a @click="tab = 'currency'" :class="{ 'active': tab === 'currency' }">Currency Rates</a></li>
                <li><a @click="tab = 'followup'" :class="{ 'active': tab === 'followup' }">Follow-up Rules</a></li>
                <li><a @click="tab = 'templates'" :class="{ 'active': tab === 'templates' }">Email Templates</a></li>
                <li><a @click="tab = 'promo'" :class="{ 'active': tab === 'promo' }">Promotion Calendar</a></li>
                <li><a @click="tab = 'backup'" :class="{ 'active': tab === 'backup' }">Backup & Data</a></li>
                <li><a @click="tab = 'permalinks'" :class="{ 'active': tab === 'permalinks' }">Permalinks</a></li>
            </ul>
        </div>

        <!-- Content -->
        <div class="md:col-span-3">
            <form method="POST">
                <?php csrf_field(); ?>
                <!-- General Tab -->
                <div x-show="tab === 'general'" class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title mb-4">General Settings</h3>
                        <div class="form-control w-full">
                            <label class="label"><span class="label-text">Business Name</span></label>
                            <input type="text" name="setting_business_name" value="<?php echo val('business_name', APP_NAME); ?>" class="input input-bordered w-full" />
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">Currency Symbol</span></label>
                            <input type="text" name="setting_currency_symbol" value="<?php echo val('currency_symbol', '$'); ?>" class="input input-bordered w-full" />
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">Date Format</span></label>
                            <select name="setting_date_format" class="select select-bordered">
                                <option value="Y-m-d" <?php echo val('date_format') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="d/m/Y" <?php echo val('date_format') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="M d, Y" <?php echo val('date_format') == 'M d, Y' ? 'selected' : ''; ?>>MMM DD, YYYY</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Security Tab -->
                <div x-show="tab === 'security'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Security</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Change Password -->
                            <div>
                                <h4 class="font-bold mb-4">Change Password</h4>
                                <input type="hidden" name="change_password" value="1" disabled x-ref="pw_trigger">
                                <div class="form-control w-full">
                                    <label class="label"><span class="label-text">Current Password</span></label>
                                    <input type="password" name="current_password" class="input input-bordered w-full" />
                                </div>
                                <div class="form-control w-full mt-2">
                                    <label class="label"><span class="label-text">New Password</span></label>
                                    <input type="password" name="new_password" class="input input-bordered w-full" />
                                </div>
                                <div class="form-control w-full mt-2">
                                    <label class="label"><span class="label-text">Confirm Password</span></label>
                                    <input type="password" name="confirm_password" class="input input-bordered w-full" />
                                </div>
                                <button type="submit" name="change_password" value="1" class="btn btn-warning mt-4 w-full">Update Password</button>
                            </div>
                            
                            <!-- Session Settings -->
                            <div>
                                <h4 class="font-bold mb-4">Session Security</h4>
                                <div class="form-control w-full">
                                    <label class="label"><span class="label-text">Session Timeout (Minutes)</span></label>
                                    <input type="number" name="setting_session_timeout" value="<?php echo val('session_timeout', '30'); ?>" class="input input-bordered w-full" />
                                </div>
                                
                                <h4 class="font-bold mt-8 mb-4">Recent Activity</h4>
                                <div class="overflow-x-auto h-48 border rounded">
                                    <table class="table table-xs w-full">
                                        <thead><tr><th>Action</th><th>Time</th><th>IP</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            // Mock/Fetch logs if table exists
                                            $logs = db_fetch_all("SELECT * FROM system_activity ORDER BY id DESC LIMIT 10");
                                            if(empty($logs)) echo "<tr><td colspan='3'>No activity logged.</td></tr>";
                                            foreach($logs as $l): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($l['action_type']); ?></td>
                                                <td><?php echo date('m-d H:i', strtotime($l['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($l['ip_address']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permalinks Tab -->
                <div x-show="tab === 'permalinks'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Permalinks</h3>
                        <div class="form-control w-full">
                            <label class="label cursor-pointer">
                                <span class="label-text">Enable Pretty URLs (e.g. /project/my-project)</span> 
                                <input type="checkbox" name="setting_enable_pretty_urls" value="1" class="toggle toggle-primary" <?php echo val('enable_pretty_urls') ? 'checked' : ''; ?> />
                            </label>
                            <p class="text-xs text-gray-500 mt-2">Requires .htaccess file to be writable.</p>
                        </div>
                        <div class="mt-4">
                            <button type="button" class="btn btn-outline btn-sm" onclick="alert('Regenerating .htaccess... (Mock)')">Regenerate .htaccess</button>
                        </div>
                    </div>
                </div>

                <!-- AI Tab -->
                <div x-show="tab === 'ai'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">AI Settings</h3>
                        <div class="form-control w-full">
                            <label class="label cursor-pointer">
                                <span class="label-text">Enable AI Features</span> 
                                <input type="checkbox" name="setting_ai_enabled" value="1" class="toggle toggle-primary" <?php echo val('ai_enabled') ? 'checked' : ''; ?> />
                            </label>
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">AI Model</span></label>
                            <select name="setting_ai_model" class="select select-bordered">
                                <option value="none">None</option>
                                <option value="gpt-4o" <?php echo val('ai_model') == 'gpt-4o' ? 'selected' : ''; ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?php echo val('ai_model') == 'gpt-4o-mini' ? 'selected' : ''; ?>>GPT-4o-mini</option>
                                <option value="claude" <?php echo val('ai_model') == 'claude' ? 'selected' : ''; ?>>Claude 3.5 Sonnet</option>
                                <option value="grok" <?php echo val('ai_model') == 'grok' ? 'selected' : ''; ?>>Grok Beta</option>
                            </select>
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">API Key</span></label>
                            <input type="password" name="setting_ai_api_key" value="<?php echo val('ai_api_key'); ?>" class="input input-bordered w-full" placeholder="sk-..." />
                        </div>
                    </div>
                </div>

                <!-- SMTP Tab -->
                <div x-show="tab === 'smtp'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Email SMTP</h3>
                        <div class="alert alert-info text-sm mb-4">
                            Using PHP mail() wrapper. Configure SMTP details if using a library later.
                        </div>
                        <div class="form-control w-full">
                            <label class="label"><span class="label-text">SMTP Host</span></label>
                            <input type="text" name="setting_smtp_host" value="<?php echo val('smtp_host', 'smtp.gmail.com'); ?>" class="input input-bordered w-full" />
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">SMTP User (Email)</span></label>
                            <input type="email" name="setting_smtp_user" value="<?php echo val('smtp_user'); ?>" class="input input-bordered w-full" />
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">SMTP Password (App Password)</span></label>
                            <input type="password" name="setting_smtp_pass" value="<?php echo val('smtp_pass'); ?>" class="input input-bordered w-full" />
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">Port</span></label>
                            <input type="text" name="setting_smtp_port" value="<?php echo val('smtp_port', '587'); ?>" class="input input-bordered w-full" />
                        </div>
                        <button type="button" class="btn btn-outline mt-4">Test Email</button>
                    </div>
                </div>

                <!-- Currency Tab -->
                <div x-show="tab === 'currency'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Currency Rates</h3>
                        <div class="form-control w-full">
                            <label class="label"><span class="label-text">USD to PKR Rate</span></label>
                            <input type="number" step="0.01" name="setting_usd_pkr_rate" value="<?php echo val('usd_pkr_rate', '278.50'); ?>" class="input input-bordered w-full" />
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Used for converting earnings in reports.</p>
                    </div>
                </div>

                <!-- Follow-up Tab -->
                <div x-show="tab === 'followup'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Follow-up Rules</h3>
                        <div class="form-control w-full">
                            <label class="label"><span class="label-text">Remind after (days of no contact)</span></label>
                            <input type="number" name="setting_followup_days" value="<?php echo val('followup_days', '3'); ?>" class="input input-bordered w-full" />
                        </div>
                        <div class="form-control w-full mt-4">
                            <label class="label"><span class="label-text">Mark 'Ghost' after (days)</span></label>
                            <input type="number" name="setting_ghost_days" value="<?php echo val('ghost_days', '30'); ?>" class="input input-bordered w-full" />
                        </div>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div x-show="tab === 'templates'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Email Templates</h3>
                        <p class="text-gray-500 mb-4">Variables: {client_name}, {amount}, {due_date}, {my_name}</p>
                        
                        <div class="collapse collapse-arrow bg-base-200 mb-2">
                            <input type="radio" name="my-accordion-2" checked="checked" /> 
                            <div class="collapse-title text-xl font-medium">Invoice Email</div>
                            <div class="collapse-content"> 
                                <textarea name="setting_template_invoice" class="textarea textarea-bordered w-full h-32"><?php echo val('template_invoice', "Hi {client_name},\n\nPlease find attached invoice for {amount}. Due on {due_date}.\n\nThanks,\n{my_name}"); ?></textarea>
                            </div>
                        </div>
                        <div class="collapse collapse-arrow bg-base-200 mb-2">
                            <input type="radio" name="my-accordion-2" /> 
                            <div class="collapse-title text-xl font-medium">Follow-up Email</div>
                            <div class="collapse-content"> 
                                <textarea name="setting_template_followup" class="textarea textarea-bordered w-full h-32"><?php echo val('template_followup', "Hi {client_name},\n\nJust checking in on our last conversation.\n\nBest,\n{my_name}"); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Promo Tab -->
                <div x-show="tab === 'promo'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Promotion Calendar</h3>
                        <div class="form-control w-full">
                            <label class="label cursor-pointer">
                                <span class="label-text">Enable Black Friday Event</span> 
                                <input type="checkbox" name="setting_promo_bf" value="1" class="toggle toggle-accent" <?php echo val('promo_bf') ? 'checked' : ''; ?> />
                            </label>
                        </div>
                        <div class="form-control w-full mt-2">
                            <label class="label cursor-pointer">
                                <span class="label-text">Enable Cyber Monday Event</span> 
                                <input type="checkbox" name="setting_promo_cm" value="1" class="toggle toggle-accent" <?php echo val('promo_cm') ? 'checked' : ''; ?> />
                            </label>
                        </div>
                        <div class="form-control w-full mt-2">
                            <label class="label cursor-pointer">
                                <span class="label-text">Enable New Year Event</span> 
                                <input type="checkbox" name="setting_promo_ny" value="1" class="toggle toggle-accent" <?php echo val('promo_ny') ? 'checked' : ''; ?> />
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Backup Tab -->
                <div x-show="tab === 'backup'" class="card bg-base-100 shadow-xl" x-cloak>
                    <div class="card-body">
                        <h3 class="card-title mb-4">Backup & Data</h3>
                        <p class="text-sm text-gray-500 mb-4">Download a full SQL dump of your database.</p>
                        <button type="button" class="btn btn-primary" onclick="alert('Backup download started (mock)')">Download SQL Backup</button>
                        <div class="divider"></div>
                        <button type="button" class="btn btn-error btn-outline" onclick="confirm('Are you sure? This will wipe all data.')">Reset Database</button>
                    </div>
                </div>

                <!-- Save Button (Floating) -->
                <div class="fixed bottom-6 right-6">
                    <button type="submit" class="btn btn-primary btn-lg shadow-lg">Save All Settings</button>
                </div>
                </form>
        </div>
    </div>
</div>

<?php require_once 'functions.php';
require_once 'auth.php'; ?>
<?php require_once 'footer.php'; ?>
