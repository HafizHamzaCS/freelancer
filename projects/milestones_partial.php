<?php
// This is a partial template for HTMX responses
if (empty($milestones)): ?>
    <div class="text-center py-8 opacity-50 italic">No milestones set.</div>
<?php else: ?>
    <?php foreach ($milestones as $m): ?>
        <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg group">
            <div class="flex items-center gap-4">
                <form hx-post="<?php echo $_SERVER['REQUEST_URI']; ?>" hx-target="#milestones-list" hx-swap="outerHTML">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="toggle_milestone" value="<?php echo $m['id']; ?>">
                    <input type="checkbox" class="checkbox checkbox-primary" 
                           <?php echo $m['status'] == 'Completed' ? 'checked' : ''; ?> 
                           onchange="this.form.dispatchEvent(new Event('submit'))">
                </form>
                <div>
                    <div class="font-bold <?php echo $m['status'] == 'Completed' ? 'line-through opacity-50' : ''; ?>">
                        <?php echo e($m['title']); ?>
                    </div>
                    <div class="text-xs opacity-50">Due: <?php echo date('M d, Y', strtotime($m['due_date'])); ?></div>
                </div>
            </div>
            <div class="badge <?php echo $m['status'] == 'Completed' ? 'badge-success' : 'badge-ghost'; ?>">
                <?php echo $m['status']; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
