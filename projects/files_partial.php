<?php if (empty($project_files)): ?>
    <div class="col-span-full text-center py-10 border-2 border-dashed border-base-300 rounded-xl">
        <p class="text-base-content/50">No files uploaded yet.</p>
    </div>
<?php else: ?>
    <?php foreach ($project_files as $file): ?>
    <div class="card bg-base-200 p-4 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-base-300 transition group relative">
        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <form hx-post="<?php echo $_SERVER['REQUEST_URI']; ?>" hx-target="#project-files-grid" hx-confirm="Are you sure you want to delete this file?">
                <?php csrf_field(); ?>
                <input type="hidden" name="delete_project_file" value="<?php echo $file['id']; ?>">
                <button type="submit" class="btn btn-xs btn-circle btn-error">✕</button>
            </form>
        </div>
        <a href="<?php echo $file['filepath']; ?>" target="_blank" class="flex flex-col items-center w-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-primary mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            <span class="text-xs font-bold truncate w-full px-2"><?php echo htmlspecialchars($file['filename']); ?></span>
            <span class="text-[10px] text-base-content/50 mt-1"><?php echo date('M d', strtotime($file['uploaded_at'])); ?></span>
        </a>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
