<!-- In users.php, replace the bulk actions form with this: -->
<div class="bulk-actions" id="bulkActionsBar">
    <div class="bulk-select">
        <input type="checkbox" id="selectAll" class="select-all-checkbox" onchange="toggleSelectAll(this)">
        <label for="selectAll">Select All</label>
    </div>
    
    <span class="selected-count" id="selectedCount">0 users selected</span>
    
    <form method="POST" action="delete_multiple.php" id="bulkActionForm">
        <button type="submit" class="btn-bulk-action btn-bulk-delete">
            <i class="fas fa-trash"></i> Delete Selected
        </button>
    </form>
    
    <button class="btn-bulk-action" onclick="clearSelection()">
        <i class="fas fa-times"></i> Clear Selection
    </button>
</div>

<script>
// Update the validateBulkDelete function to this:
function validateBulkDelete() {
    if (selectedUsers.length === 0) {
        alert('Please select at least one user to delete.');
        return false;
    }
    
    const currentUserId = '<?php echo $user_id; ?>';
    const hasCurrentUser = selectedUsers.includes(currentUserId);
    
    if (hasCurrentUser) {
        alert('You cannot delete your own account. Please deselect your account and try again.');
        return false;
    }
    
    // Add selected users to form
    const form = document.getElementById('bulkActionForm');
    selectedUsers.forEach(userId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_users[]';
        input.value = userId;
        form.appendChild(input);
    });
    
    return true;
}

// Add form submission event
document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
    if (selectedUsers.length === 0) {
        e.preventDefault();
        alert('Please select at least one user to delete.');
        return false;
    }
    
    if (!confirm(`Are you sure you want to delete ${selectedUsers.length} user(s)?\n\nThis action cannot be undone!`)) {
        e.preventDefault();
        return false;
    }
});
</script>