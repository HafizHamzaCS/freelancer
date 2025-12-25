<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

require_role(['admin', 'member']);

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$id = (int)$_GET['id'];
$task = db_fetch_one("SELECT t.*, p.name as project_name, u.name as assigned_name, c.name as creator_name 
                      FROM tasks t 
                      LEFT JOIN projects p ON t.project_id = p.id 
                      LEFT JOIN users u ON t.assigned_to = u.id 
                      LEFT JOIN users c ON t.created_by = c.id 
                      WHERE t.id = $id");

if (!$task) {
    die("Task not found.");
}

// Fetch Files
$files = db_fetch_all("SELECT * FROM task_files WHERE task_id = $id ORDER BY uploaded_at DESC");

// Fetch Activity (Initial)
$activities = db_fetch_all("SELECT ta.*, u.name as user_name FROM task_activity ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = $id ORDER BY ta.created_at DESC LIMIT 20");

$page_title = "Task: " . htmlspecialchars($task['title']);
require_once '../header.php';
?>

<div class="max-w-6xl mx-auto" x-data="taskManager(<?php echo $id; ?>)">
    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="text-sm breadcrumbs opacity-50">
                <ul>
                    <li><a href="index.php">Tasks</a></li>
                    <li>Task #<?php echo $id; ?></li>
                </ul>
            </div>
            <h1 class="text-3xl font-bold mt-1"><?php echo htmlspecialchars($task['title']); ?></h1>
        </div>
        <div class="flex gap-2">
            <button @click="saveStatus" class="btn btn-success" :disabled="loading">
                <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                Save Changes
            </button>
            <a href="edit_task.php?id=<?php echo $id; ?>" class="btn btn-outline">Edit</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Main Content (Tabs) -->
        <div class="lg:col-span-3">
            <div role="tablist" class="tabs tabs-lifted tabs-lg mb-6">
                <!-- Overview Tab -->
                <a role="tab" class="tab" :class="{ 'tab-active': tab === 'overview' }" @click="tab = 'overview'">Overview</a>
                
                <!-- Description Tab -->
                <a role="tab" class="tab" :class="{ 'tab-active': tab === 'description' }" @click="tab = 'description'">Description</a>
                
                <!-- Files Tab -->
                <a role="tab" class="tab" :class="{ 'tab-active': tab === 'files' }" @click="tab = 'files'">Files <span class="badge badge-sm badge-ghost ml-2"><?php echo count($files); ?></span></a>

                <!-- Chat Tab -->
                <a role="tab" class="tab" :class="{ 'tab-active': tab === 'chat' }" @click="tab = 'chat'">Chat</a>

                <!-- Activity Tab -->
                <a role="tab" class="tab" :class="{ 'tab-active': tab === 'activity' }" @click="tab = 'activity'">Activity</a>
            </div>

            <!-- Tab Contents -->
            <div class="bg-base-100 p-6 rounded-b-box rounded-tr-box shadow-xl min-h-[400px]">
                
                <!-- OVERVIEW -->
                <div x-show="tab === 'overview'">
                    <div class="stats shadow w-full mb-6">
                        <div class="stat">
                            <div class="stat-title">Status</div>
                            <div class="stat-value text-primary text-2xl">
                                <select x-model="status" class="select select-ghost w-full max-w-xs text-2xl font-bold p-0 h-auto">
                                    <option value="Todo">Todo</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="On Hold">On Hold</option>
                                    <option value="Done">Done</option>
                                </select>
                            </div>
                        </div>
                        <div class="stat">
                            <div class="stat-title">Progress</div>
                            <div class="stat-value text-secondary text-2xl">
                                <span x-text="progress + '%'"></span>
                            </div>
                            <input type="range" min="0" max="100" x-model="progress" class="range range-xs range-secondary mt-2" /> 
                        </div>
                        <div class="stat">
                            <div class="stat-title">Priority</div>
                            <div class="stat-value text-2xl <?php echo $task['priority']=='High'?'text-error':''; ?>">
                                <?php echo htmlspecialchars($task['priority']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Description Preview -->
                    <div class="prose max-w-none bg-base-50 p-4 rounded-lg">
                        <h3 class="mt-0">Description</h3>
                        <div class="line-clamp-4">
                            <?php echo $task['description'] ? $task['description'] : '<em>No description.</em>'; ?>
                        </div>
                        <button class="btn btn-link btn-xs p-0" @click="tab = 'description'">View Full Description</button>
                    </div>
                </div>

                <!-- DESCRIPTION -->
                <div x-show="tab === 'description'">
                    <div class="prose max-w-none">
                        <?php echo $task['description'] ? $task['description'] : '<div class="alert">No description provided.</div>'; ?>
                    </div>
                </div>

                <!-- FILES -->
                <div x-show="tab === 'files'">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Attachments</h3>
                        <button class="btn btn-sm btn-primary" onclick="document.getElementById('file-upload').click()">Upload File</button>
                        <input type="file" id="file-upload" class="hidden" @change="uploadFile" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Size</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="files.length === 0">
                                    <tr><td colspan="4" class="text-center opacity-50">No files uploaded.</td></tr>
                                </template>
                                <template x-for="file in files" :key="file.id">
                                    <tr class="hover:bg-base-200">
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                <span x-text="file.name" class="font-medium"></span>
                                            </div>
                                        </td>
                                        <td x-text="formatBytes(file.size)"></td>
                                        <td x-text="file.date"></td>
                                        <td>
                                            <a :href="file.url" download class="btn btn-xs btn-ghost text-primary">Download</a>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CHAT -->
                <div x-show="tab === 'chat'">
                    <div class="flex flex-col h-[500px]">
                        <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-base-200 rounded-lg mb-4" id="chat-box">
                            <template x-if="messages.length === 0">
                                <div class="text-center opacity-50 mt-10">Start the conversation...</div>
                            </template>
                            <template x-for="msg in messages" :key="msg.id">
                                <div class="chat" :class="msg.is_me ? 'chat-end' : 'chat-start'">
                                    <div class="chat-image avatar placeholder">
                                        <div class="bg-neutral-focus text-neutral-content rounded-full w-8">
                                            <span x-text="msg.avatar_letter"></span>
                                        </div>
                                    </div>
                                    <div class="chat-header text-xs opacity-50 mb-1">
                                        <span x-text="msg.user_name"></span>
                                        <time class="text-xs opacity-50" x-text="msg.time"></time>
                                    </div>
                                    <div class="chat-bubble" :class="msg.is_me ? 'chat-bubble-primary' : 'chat-bubble-secondary'" x-text="msg.message"></div>
                                </div>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" x-model="newMessage" @keyup.enter="sendMessage" class="input input-bordered w-full" placeholder="Type a message..." />
                            <button @click="sendMessage" class="btn btn-primary">Send</button>
                        </div>
                    </div>
                </div>

                <!-- ACTIVITY -->
                <div x-show="tab === 'activity'">
                    <ul class="steps steps-vertical w-full">
                        <template x-for="log in activities" :key="log.id">
                            <li class="step step-primary">
                                <div class="text-left w-full pl-4 pb-4">
                                    <div class="font-bold text-sm" x-text="log.user_name + ' - ' + log.date_formatted"></div>
                                    <div class="text-sm opacity-70" x-text="log.description"></div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body p-6 text-sm">
                    <h3 class="font-bold text-gray-500 uppercase text-xs mb-4">Details</h3>
                    
                    <div class="mb-4">
                        <span class="block opacity-50 text-xs mb-1">Assigned To</span>
                       
                        <div class="flex items-center gap-2">
                            <div class="avatar placeholder">
                                <div class="bg-neutral text-neutral-content rounded-full w-8">
                                    <span><?php echo substr($task['assigned_name'] ?? '?', 0, 1); ?></span>
                                </div>
                            </div>
                            <span class="font-bold"><?php echo htmlspecialchars($task['assigned_name'] ?? 'Unassigned'); ?></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <span class="block opacity-50 text-xs mb-1">Project</span>
                        <a href="../projects/project_view.php?id=<?php echo $task['project_id']; ?>" class="link link-primary font-bold">
                            <?php echo htmlspecialchars($task['project_name']); ?>
                        </a>
                    </div>

                    <div class="mb-4">
                        <span class="block opacity-50 text-xs mb-1">Due Date</span>
                        <div class="font-mono"><?php echo $task['due_date']; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('taskManager', (taskId) => ({
        tab: 'overview',
        status: '<?php echo $task['status']; ?>',
        progress: <?php echo (int)($task['progress'] ?? 0); ?>,
        loading: false,
        files: <?php echo json_encode(array_map(function($f){ 
            return [
                'id' => $f['id'], 
                'name' => $f['original_name'], 
                'size' => $f['file_size'], 
                'url' => APP_URL . '/' . $f['file_path'],
                'date' => date('M d, Y', strtotime($f['uploaded_at']))
            ]; 
        }, $files)); ?>,
        messages: [],
        newMessage: '',
        lastMsgId: 0,
        activities: <?php echo json_encode(array_map(function($a){
            $a['date_formatted'] = date('M d, H:i', strtotime($a['created_at']));
            return $a;
        }, $activities)); ?>,

        init() {
            this.fetchChat();
            setInterval(() => { if(this.tab === 'chat') this.fetchChat(); }, 5000);
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        saveStatus() {
            this.loading = true;
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('task_id', taskId);
            formData.append('status', this.status);
            formData.append('progress', this.progress);

            fetch('task_actions.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    this.loading = false;
                    if(d.success) alert('Saved!');
                });
        },

        uploadFile(e) {
            const file = e.target.files[0];
            if(!file) return;

            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('file', file);

            fetch('upload_file.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if(d.success) {
                        this.files.unshift({
                            id: d.file.id,
                            name: d.file.name,
                            size: file.size,
                            url: d.file.url,
                            date: d.file.date
                        });
                        alert('File uploaded!');
                    } else {
                        alert('Error: ' + d.error);
                    }
                });
        },

        sendMessage() {
            if(!this.newMessage.trim()) return;
            const msg = this.newMessage;
            this.newMessage = ''; // Optimistic

            const formData = new FormData();
            formData.append('action', 'send_chat');
            formData.append('task_id', taskId);
            formData.append('message', msg);

            fetch('task_actions.php', { method: 'POST', body: formData })
                .then(() => this.fetchChat());
        },

        fetchChat() {
            const formData = new FormData();
            formData.append('action', 'get_chat');
            formData.append('task_id', taskId);
            formData.append('last_id', this.lastMsgId);

            fetch('task_actions.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if(d.success && d.messages.length > 0) {
                        this.messages.push(...d.messages);
                        this.lastMsgId = this.messages[this.messages.length - 1].id;
                        // Scroll to bottom
                        setTimeout(() => {
                            const chatBox = document.getElementById('chat-box');
                            if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
                        }, 100);
                    }
                });
        }
    }));
});
</script>

<?php require_once '../footer.php'; ?>
