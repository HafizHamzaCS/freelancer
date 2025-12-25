/**
 * Bulk Actions Handler
 * Manages checkboxes and the floating action bar for bulk operations
 */
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    const bulkBar = document.getElementById('bulkActionBar');
    const selectedCount = document.getElementById('selectedCount');
    const bulkIdsInput = document.getElementById('bulkIds');

    if (!selectAll || !bulkBar) return;

    function updateBulkBar() {
        const checked = document.querySelectorAll('.bulk-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        
        if (checked.length > 0) {
            bulkBar.classList.remove('hidden');
            bulkBar.classList.add('flex');
            selectedCount.textContent = checked.length;
            if (bulkIdsInput) bulkIdsInput.value = JSON.stringify(ids);
        } else {
            bulkBar.classList.add('hidden');
            bulkBar.classList.remove('flex');
        }
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => {
            cb.checked = selectAll.checked;
        });
        updateBulkBar();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });
});

function confirmBulkDelete() {
    return confirm('Are you sure you want to delete all selected items? This cannot be undone.');
}
