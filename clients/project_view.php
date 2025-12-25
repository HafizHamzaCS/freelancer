<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

// Ensure user is logged in as a client
if (!is_logged_in() || !isset($_SESSION['client_logged_in'])) {
    redirect('../login.php');
}

$client_id = $_SESSION['user_id'];
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Project and verify ownership
$project = db_fetch_one("SELECT * FROM projects WHERE id = $project_id AND client_id = $client_id");

if (!$project) {
    redirect('portal.php');
}

// Fetch related data
$milestones = db_fetch_all("SELECT * FROM milestones WHERE project_id = $project_id ORDER BY due_date ASC");
$project_files = db_fetch_all("SELECT * FROM project_files WHERE project_id = $project_id ORDER BY uploaded_at DESC");

// Calculate Progress
$total_milestones = count($milestones);
$completed_milestones = count(array_filter($milestones, fn($m) => $m['status'] == 'Completed'));
$progress = $total_milestones > 0 ? round(($completed_milestones / $total_milestones) * 100) : 0;

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['project_file'])) {
    $file = $_FILES['project_file'];
    $filename = escape($file['name']);
    $target_dir = "../assets/uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $target_file = $target_dir . basename($file['name']);
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        db_query("INSERT INTO project_files (project_id, filename, filepath) VALUES ($project_id, '$filename', '$target_file')");
        redirect("project_view.php?id=$project_id&tab=files");
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

require_once '../header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($project['name']); ?></h2>
        <div class="text-sm breadcrumbs">
            <ul>
                <li><a href="portal.php">Portal</a></li>
                <li><?php echo htmlspecialchars($project['name']); ?></li>
            </ul>
        </div>
    </div>
    <a href="portal.php" class="btn btn-ghost">Back to Dashboard</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar / Stats -->
    <div class="lg:col-span-1 space-y-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-6">
                <h3 class="card-title text-xs uppercase font-bold text-base-content/50 mb-4">Project Status</h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="text-xs text-base-content/70 mb-1">Status</div>
                        <div class="badge <?php echo $project['status'] == 'Completed' ? 'badge-success' : 'badge-info'; ?>"><?php echo $project['status']; ?></div>
                    </div>

                    <div>
                        <div class="text-xs text-base-content/70 mb-1">Deadline</div>
                        <div class="font-bold"><?php echo date('M d, Y', strtotime($project['deadline'])); ?></div>
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
        <div role="tablist" class="tabs tabs-lifted tabs-lg mb-0">
            <a role="tab" class="tab <?php echo $active_tab == 'overview' ? 'tab-active font-bold' : ''; ?>" onclick="window.location='?id=<?php echo $project_id; ?>&tab=overview'">Overview</a>
            <a role="tab" class="tab <?php echo $active_tab == 'files' ? 'tab-active font-bold' : ''; ?>" onclick="window.location='?id=<?php echo $project_id; ?>&tab=files'">Files</a>
            <a role="tab" class="tab <?php echo $active_tab == 'chat' ? 'tab-active font-bold' : ''; ?>" onclick="window.location='?id=<?php echo $project_id; ?>&tab=chat'">Chat</a>
        </div>

        <div class="bg-base-100 border-base-300 rounded-b-box rounded-tr-box border p-6 min-h-[500px]">
            
            <!-- Overview Tab (Milestones Read-Only) -->
            <?php if ($active_tab == 'overview'): ?>
                <h3 class="font-bold text-lg mb-4">Project Roadmap</h3>
                <?php if (empty($milestones)): ?>
                    <p class="text-base-content/50">No milestones yet.</p>
                <?php else: ?>
                    <ul class="steps steps-vertical w-full">
                        <?php foreach ($milestones as $milestone): ?>
                        <li class="step <?php echo $milestone['status'] == 'Completed' ? 'step-primary' : ''; ?>">
                            <div class="flex justify-between items-center w-full text-left pl-4 py-2">
                                <div>
                                    <div class="font-bold <?php echo $milestone['status'] == 'Completed' ? 'opacity-50' : ''; ?>">
                                        <?php echo htmlspecialchars($milestone['title']); ?>
                                    </div>
                                    <div class="text-xs opacity-50">Due: <?php echo date('M d, Y', strtotime($milestone['due_date'])); ?></div>
                                </div>
                                <?php if ($milestone['status'] == 'Completed'): ?>
                                    <span class="badge badge-success badge-sm">Completed</span>
                                <?php else: ?>
                                    <span class="badge badge-ghost badge-sm">Pending</span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Files Tab -->
            <?php if ($active_tab == 'files'): ?>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Shared Files</h3>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <?php if (empty($project_files)): ?>
                        <div class="col-span-full text-center py-10 border-2 border-dashed border-base-300 rounded-xl">
                            <p class="text-base-content/50">No files shared yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($project_files as $file): ?>
                        <a href="<?php echo $file['filepath']; ?>" target="_blank" class="card bg-base-200 p-4 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-base-300 transition group">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-primary mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <span class="text-xs font-bold truncate w-full px-2"><?php echo htmlspecialchars($file['filename']); ?></span>
                            <span class="text-[10px] text-base-content/50 mt-1"><?php echo date('M d', strtotime($file['uploaded_at'])); ?></span>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="card bg-base-100 border border-base-300">
                    <div class="card-body p-4">
                        <div class="form-control w-full">
                            <label class="label"><span class="label-text">Upload File</span></label>
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
                <div class="flex flex-col h-[600px]" x-data="chatSystem(<?php echo $project_id; ?>)">
                    <!-- Chat Header -->
                    <div class="flex justify-between items-center pb-4 border-b border-base-200 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="avatar placeholder">
                                <div class="bg-neutral-focus text-neutral-content rounded-full w-10">
                                    <span class="text-xl">💬</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="font-bold">Project Discussion</h3>
                                <p class="text-xs opacity-50">Chat with the team</p>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="flex-1 overflow-y-auto space-y-4 p-4 bg-base-200/50 rounded-xl mb-4 scrollbar-thin scrollbar-thumb-base-content/20" id="chat-messages" x-ref="chatContainer">
                        <template x-if="loading">
                            <div class="flex justify-center py-4">
                                <span class="loading loading-dots loading-md"></span>
                            </div>
                        </template>

                        <template x-for="msg in messages" :key="msg.id">
                            <div class="chat" :class="msg.is_me ? 'chat-end' : 'chat-start'">
                                <div class="chat-header text-xs opacity-50 mb-1">
                                    <span x-text="msg.sender_name"></span>
                                    <time class="text-xs opacity-50 ml-1" x-text="msg.formatted_time"></time>
                                </div>
                                <div class="chat-bubble" :class="msg.is_me ? 'chat-bubble-primary' : 'chat-bubble-secondary'" x-text="msg.message"></div>
                            </div>
                        </template>
                        
                        <div x-show="messages.length === 0 && !loading" class="text-center text-base-content/50 py-10">
                            No messages yet. Start the conversation!
                        </div>
                    </div>

                    <!-- Input Area -->
                    <form @submit.prevent="sendMessage" class="flex gap-2 items-end">
                        <div class="flex-1 relative">
                            <textarea 
                                x-model="newMessage" 
                                class="textarea textarea-bordered w-full resize-none focus:outline-none focus:border-primary" 
                                placeholder="Type your message..." 
                                rows="2"
                                @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"
                            ></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-circle" :disabled="!newMessage.trim()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                        </button>
                    </form>
                </div>

                <script>
                function chatSystem(projectId) {
                    return {
                        messages: [],
                        newMessage: '',
                        loading: true,
                        interval: null,

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
                                const response = await fetch(`../projects/chat_api.php?project_id=${projectId}`);
                                const data = await response.json();
                                if (data.success) {
                                    const wasAtBottom = this.isAtBottom();
                                    this.messages = data.messages;
                                    this.loading = false;
                                    
                                    if (wasAtBottom) {
                                        this.$nextTick(() => this.scrollToBottom());
                                    }
                                }
                            } catch (error) {
                                console.error('Chat Error:', error);
                            }
                        },

                        async sendMessage() {
                            if (!this.newMessage.trim()) return;
                            
                            const msg = this.newMessage;
                            this.newMessage = ''; 

                            try {
                                const response = await fetch('../projects/chat_api.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        project_id: projectId,
                                        message: msg
                                    })
                                });
                                const result = await response.json();
                                if (result.success) {
                                    this.fetchMessages(); 
                                } else {
                                    alert('Failed to send: ' + result.message);
                                    this.newMessage = msg; 
                                }
                            } catch (error) {
                                console.error('Send Error:', error);
                                this.newMessage = msg; 
                            }
                        },

                        scrollToBottom() {
                            const container = this.$refs.chatContainer;
                            container.scrollTop = container.scrollHeight;
                        },

                        isAtBottom() {
                            const container = this.$refs.chatContainer;
                            return container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                        }
                    }
                }
                </script>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
