<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$current_user_id = $_SESSION['user_id'];

// Get current user role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

if ($current_user['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Get selected users from form
$selected_users = [];
if (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    $selected_users = array_map('intval', $_POST['selected_users']);
}

// Remove current user from selection
$selected_users = array_diff($selected_users, [$current_user_id]);

// Get user details for confirmation
$users_to_delete = [];
if (!empty($selected_users)) {
    $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
    $types = str_repeat('i', count($selected_users));
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$selected_users);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users_to_delete[] = $row;
    }
    $stmt->close();
}

// Handle deletion
$deleted_count = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (!empty($selected_users)) {
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $types = str_repeat('i', count($selected_users));
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$selected_users);
        
        if ($stmt->execute()) {
            $deleted_count = $stmt->affected_rows;
            $_SESSION['success'] = "$deleted_count user(s) deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting users!";
        }
        $stmt->close();
    }
    
    header("Location: users.php");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Multiple Users - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        
        .content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        
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
        
        /* Delete Container */
        .delete-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .warning-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .warning-icon {
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        .warning-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .warning-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        /* Users List */
        .users-list-container {
            margin: 30px 0;
        }
        
        .users-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .users-list-header h3 {
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .user-count {
            background: #e74c3c;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .users-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 8px;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .user-item:hover {
            background: #f8f9fa;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 600;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .user-info-row {
            display: flex;
            gap: 20px;
            font-size: 0.85rem;
            color: #95a5a6;
        }
        
        .user-role-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .role-admin {
            background: #ffebee;
            color: #c62828;
        }
        
        .role-user {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Danger Zone */
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #e74c3c;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .danger-zone h3 {
            color: #e74c3c;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .danger-list {
            text-align: left;
            color: #721c24;
            margin: 20px 0;
            padding-left: 20px;
        }
        
        .danger-list li {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        /* Confirmation Check */
        .confirmation-check {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .confirmation-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-top: 3px;
        }
        
        .confirmation-text {
            flex: 1;
        }
        
        .confirmation-check strong {
            color: #856404;
            display: block;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .confirmation-check p {
            color: #856404;
            line-height: 1.5;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-confirm-delete {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-confirm-delete:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h2, .sidebar-header p, .sidebar-menu span {
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
            .delete-container {
                padding: 20px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn-confirm-delete, .btn-cancel {
                width: 100%;
                justify-content: center;
            }
            .user-item {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
            .user-avatar {
                margin-right: 0;
                margin-bottom: 15px;
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
            <a href="users.php">
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
                    <h1>Delete Multiple Users</h1>
                    <p>Confirm deletion of selected users</p>
                </div>
                <div class="user-badge">Admin</div>
            </div>
            <a href="includes/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <!-- Delete Confirmation -->
        <div class="delete-container">
            <?php if(empty($users_to_delete)): ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <h2>No Users Selected</h2>
                    <p>No users were selected for deletion.</p>
                    <a href="users.php" class="btn-cancel" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
            <?php else: ?>
                <div class="warning-header">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2>Delete <?php echo count($users_to_delete); ?> User(s)</h2>
                    <p>You are about to permanently delete multiple user accounts. This action cannot be undone.</p>
                </div>
                
                <!-- Users List -->
                <div class="users-list-container">
                    <div class="users-list-header">
                        <h3>Users to be deleted</h3>
                        <span class="user-count"><?php echo count($users_to_delete); ?> users</span>
                    </div>
                    
                    <div class="users-list">
                        <?php foreach($users_to_delete as $user): ?>
                        <div class="user-item">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                                    <span class="user-role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="user-info-row">
                                    <span>ID: #<?php echo $user['id']; ?></span>
                                    <span>Username: <?php echo htmlspecialchars($user['username']); ?></span>
                                    <span>Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                </div>
                            </div>
                            <div style="color: #e74c3c; font-size: 1.5rem;">
                                <i class="fas fa-trash"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h3><i class="fas fa-radiation"></i> Warning: This action is irreversible!</h3>
                    <p style="color: #721c24; margin-bottom: 15px; font-weight: 600;">Deleting these users will:</p>
                    <ul class="danger-list">
                        <li>Permanently remove <?php echo count($users_to_delete); ?> user accounts from the database</li>
                        <li>Delete all associated user data permanently</li>
                        <li>Remove all user-related information and history</li>
                        <li>This action <strong>cannot be undone or recovered</strong></li>
                        <li>There is no backup or restore option for deleted users</li>
                    </ul>
                </div>
                
                <!-- Confirmation Check -->
                <div class="confirmation-check">
                    <input type="checkbox" id="confirmDelete" name="confirmDelete" required>
                    <div class="confirmation-text">
                        <strong>I understand the consequences</strong>
                        <p>I confirm that I want to permanently delete <?php echo count($users_to_delete); ?> user(s) from the system. I understand that this action cannot be undone and I accept full responsibility for this deletion.</p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <form method="POST" action="" onsubmit="return validateDelete()">
                    <?php foreach($selected_users as $id): ?>
                        <input type="hidden" name="selected_users[]" value="<?php echo $id; ?>">
                    <?php endforeach; ?>
                    
                    <div class="action-buttons">
                        <button type="submit" name="confirm_delete" class="btn-confirm-delete">
                            <i class="fas fa-trash-alt"></i> Delete All Selected Users
                        </button>
                        <a href="users.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function validateDelete() {
            const checkbox = document.getElementById('confirmDelete');
            if (!checkbox.checked) {
                alert('Please confirm that you understand this action cannot be undone.');
                return false;
            }
            
            const count = <?php echo count($users_to_delete); ?>;
            if (!confirm(`ARE YOU ABSOLUTELY SURE?\n\nYou are about to permanently delete ${count} user(s).\n\nThis action is PERMANENT and cannot be undone!`)) {
                return false;
            }
            
            return true;
        }
        
        // Disable form submission if checkbox not checked
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!document.getElementById('confirmDelete').checked) {
                e.preventDefault();
                alert('Please confirm that you understand this action cannot be undone.');
                return false;
            }
        });
        
        // Auto focus on checkbox
        document.getElementById('confirmDelete')?.focus();
        
        // Add some visual feedback
        document.addEventListener('DOMContentLoaded', function() {
            const deleteBtn = document.querySelector('.btn-confirm-delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('mouseover', function() {
                    this.style.transform = 'scale(1.02)';
                });
                deleteBtn.addEventListener('mouseout', function() {
                    this.style.transform = 'scale(1)';
                });
            }
        });
    </script>
</body>
</html>