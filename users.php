<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Get all users from database
$users_result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$total_users = $users_result->num_rows;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .sidebar-header .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
            background: rgba(255,255,255,0.1);
            padding: 3px 10px;
            border-radius: 20px;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            border-left: 4px solid #3498db;
            padding-left: 25px;
        }
        
        .sidebar-menu a.active {
            background: rgba(52, 152, 219, 0.2);
            border-left: 4px solid #3498db;
        }
        
        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        
        /* Header */
        .header {
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .welcome-message h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .welcome-message p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .user-badge {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .logout-btn {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        /* User Management Container */
        .user-management-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        
        /* Section Header */
        .section-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-header h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header p {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-top: 5px;
        }
        
        /* Bulk Actions Bar */
        .bulk-actions {
            display: none;
            align-items: center;
            gap: 20px;
            padding: 15px 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
            flex-wrap: wrap;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .bulk-select {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .bulk-select input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .selected-count {
            font-weight: 600;
            color: #495057;
            margin-right: auto;
        }
        
        .btn-bulk-action {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-bulk-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-bulk-delete:hover {
            background: #c82333;
        }
        
        .btn-bulk-clear {
            background: #6c757d;
            color: white;
        }
        
        .btn-bulk-clear:hover {
            background: #5a6268;
        }
        
        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            min-width: 200px;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        
        .btn-add-user {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-add-user:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        /* Users Table */
        .users-table-container {
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .users-table thead {
            background: #f8f9fa;
        }
        
        .users-table th {
            padding: 15px;
            text-align: left;
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid #eee;
        }
        
        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .users-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Checkbox column */
        .checkbox-column {
            width: 50px;
            padding-left: 20px !important;
        }
        
        .user-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* User Avatar */
        .user-avatar {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-username {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        /* Role Badge */
        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-admin {
            background: #ffebee;
            color: #c62828;
        }
        
        .role-user {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-view {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .btn-view:hover {
            background: #1565c0;
            color: white;
        }
        
        .btn-edit {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .btn-edit:hover {
            background: #2e7d32;
            color: white;
        }
        
        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn-delete:hover {
            background: #c62828;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #95a5a6;
            font-size: 0.95rem;
        }
        
        /* Stats Summary */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 4px solid #3498db;
        }
        
        .stat-card:nth-child(2) {
            border-top-color: #2ecc71;
        }
        
        .stat-card:nth-child(3) {
            border-top-color: #e74c3c;
        }
        
        .stat-card i {
            font-size: 2rem;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-card:nth-child(2) i {
            color: #2ecc71;
        }
        
        .stat-card:nth-child(3) i {
            color: #e74c3c;
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .stat-card h2 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 700;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2,
            .sidebar-header p,
            .sidebar-menu span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .content {
                margin-left: 70px;
                width: calc(100% - 70px);
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
            }
            
            .action-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .users-table {
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .selected-count {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Admin Panel</h2>
            <p class="user-role"><?php echo ucfirst($current_user['role']); ?></p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="active">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="profile.php">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="includes/logout.php">
                <i class="fas fa-power-off"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <!-- Header -->
        <div class="header">
            <div class="user-info">
                <div class="welcome-message">
                    <h1>User Management</h1>
                    <p>Manage all user accounts in the system</p>
                </div>
                <div class="user-badge">
                    <?php echo ucfirst($current_user['role']); ?>
                </div>
            </div>
            <a href="includes/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
        
        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Users</h3>
                <h2><?php echo $total_users; ?></h2>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <h3>Active Users</h3>
                <h2>0</h2>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-shield"></i>
                <h3>Admin Users</h3>
                <h2>0</h2>
            </div>
        </div>
        
        <!-- User Management Container -->
        <div class="user-management-container">
            <div class="section-header">
                <h2><i class="fas fa-users-cog"></i> All Users</h2>
                <p>View and manage all registered users in the system</p>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions" id="bulkActionsBar">
                <div class="bulk-select">
                    <input type="checkbox" id="selectAll" class="select-all-checkbox" onchange="toggleSelectAll(this)">
                    <label for="selectAll">Select All</label>
                </div>
                
                <span class="selected-count" id="selectedCount">0 users selected</span>
                
                <form method="POST" action="delete_multiple.php" id="bulkActionForm">
                    <button type="submit" class="btn-bulk-action btn-bulk-delete" onclick="return validateBulkDelete()">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </form>
                
                <button type="button" class="btn-bulk-action btn-bulk-clear" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
            
            <!-- Action Bar -->
            <div class="action-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search users..." id="searchInput">
                </div>
                <a href="add_user.php" class="btn-add-user">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
            </div>
            
            <!-- Users Table -->
            <div class="users-table-container">
                <?php if($total_users > 0): ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" class="select-all-checkbox" onchange="toggleSelectAll(this)">
                                </th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php while($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td class="checkbox-column">
                                    <input type="checkbox" 
                                           class="user-checkbox" 
                                           value="<?php echo $user['id']; ?>"
                                           onchange="updateSelection(this)"
                                           <?php echo ($user['id'] == $user_id) ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <div class="user-avatar">
                                        <div class="avatar-circle">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                                            <span class="user-username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-active">
                                        Active
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                            <span>View</span>
                                        </a>
                                        <a href="profile.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                            <span>Edit</span>
                                        </a>
                                        <?php if($user['id'] != $user_id): ?>
                                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn-action btn-delete" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash"></i>
                                                <span>Delete</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Users Found</h3>
                        <p>There are no users registered in the system yet.</p>
                        <a href="add_user.php" class="btn-add-user" style="margin-top: 20px;">
                            <i class="fas fa-user-plus"></i> Add First User
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Global variable to store selected users
        let selectedUsers = [];
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Sort table functionality
        function sortTable(columnIndex) {
            const table = document.querySelector('.users-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex + 1].textContent.trim(); // +1 for checkbox column
                const bText = b.cells[columnIndex + 1].textContent.trim();
                return aText.localeCompare(bText);
            });
            
            // Clear and re-add sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }
        
        // Add click event to table headers for sorting (skip checkbox column)
        document.querySelectorAll('.users-table th').forEach((th, index) => {
            if (index > 0) { // Skip first column (checkbox)
                th.style.cursor = 'pointer';
                th.addEventListener('click', () => sortTable(index - 1));
            }
        });
        
        // Bulk selection functions
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.user-checkbox:not(:disabled)');
            const isChecked = source.checked;
            
            selectedUsers = [];
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
                if (isChecked) {
                    selectedUsers.push(checkbox.value);
                }
            });
            
            updateBulkActions();
        }
        
        function updateSelection(checkbox) {
            const userId = checkbox.value;
            
            if (checkbox.checked) {
                if (!selectedUsers.includes(userId)) {
                    selectedUsers.push(userId);
                }
            } else {
                const index = selectedUsers.indexOf(userId);
                if (index > -1) {
                    selectedUsers.splice(index, 1);
                }
                // Uncheck select all checkbox if any is unchecked
                document.getElementById('selectAll').checked = false;
                document.querySelectorAll('.select-all-checkbox').forEach(cb => cb.checked = false);
            }
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedUsers.length > 0) {
                bulkBar.style.display = 'flex';
                selectedCount.textContent = `${selectedUsers.length} user(s) selected`;
            } else {
                bulkBar.style.display = 'none';
                selectedCount.textContent = '0 users selected';
            }
        }
        
        function clearSelection() {
            selectedUsers = [];
            const checkboxes = document.querySelectorAll('.user-checkbox, .select-all-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }
        
        function validateBulkDelete() {
            if (selectedUsers.length === 0) {
                alert('Please select at least one user to delete.');
                return false;
            }
            
            // Check if current user is included
            const currentUserId = '<?php echo $user_id; ?>';
            if (selectedUsers.includes(currentUserId)) {
                alert('You cannot delete your own account. Please deselect your account and try again.');
                return false;
            }
            
            // Add selected users to form as hidden inputs
            const form = document.getElementById('bulkActionForm');
            
            // Clear any existing hidden inputs
            const existingInputs = form.querySelectorAll('input[name="selected_users[]"]');
            existingInputs.forEach(input => input.remove());
            
            // Add new hidden inputs for selected users
            selectedUsers.forEach(userId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_users[]';
                input.value = userId;
                form.appendChild(input);
            });
            
            // Confirm action
            if (!confirm(`Are you sure you want to delete ${selectedUsers.length} user(s)?\n\nThis action cannot be undone!`)) {
                return false;
            }
            
            return true;
        }
        
        // Add form submission event
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            if (!validateBulkDelete()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-refresh every 60 seconds (optional)
        setInterval(() => {
            console.log('Auto-refresh user list');
        }, 60000);
        
        // Export users function
        function exportUsers(format) {
            alert(`Exporting users in ${format} format...`);
            // Implement export functionality here
        }
        
        // Print user list
        function printUsers() {
            window.print();
        }
        
        // Quick actions
        function quickAction(userId, action) {
            if (action === 'activate') {
                if (confirm('Activate this user?')) {
                    // Implement activation
                }
            } else if (action === 'deactivate') {
                if (confirm('Deactivate this user?')) {
                    // Implement deactivation
                }
            }
        }
    </script>
</body>
</html>