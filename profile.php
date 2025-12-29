<?php
// profile.php - User profile editing page with dashboard
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

// Initialize variables at the top
$success = '';
$error = '';

// Get current user data
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Update basic info
    if (!empty($full_name) || $email != $user['email']) {
        $update_sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $full_name, $email, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $success = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile!";
        }
    }
    
    // Update password if provided
    if (!empty($current_password) && !empty($new_password)) {
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pwd_sql = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_pwd_sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = (!empty($success) ? $success . " " : "") . "Password updated successfully!";
                    } else {
                        $error = (!empty($error) ? $error . " " : "") . "Failed to update password!";
                    }
                } else {
                    $error = (!empty($error) ? $error . " " : "") . "New password must be at least 6 characters!";
                }
            } else {
                $error = (!empty($error) ? $error . " " : "") . "New passwords do not match!";
            }
        } else {
            $error = (!empty($error) ? $error . " " : "") . "Current password is incorrect!";
        }
    }
    
    // Refresh user data after update
    if ($success) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    }
}

// Close statement and connection
if (isset($stmt) && $stmt) {
    $stmt->close();
}
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #764ba2;
            --sidebar-bg: #1a202c;
            --sidebar-hover: #2d3748;
            --card-bg: #ffffff;
            --text-light: #718096;
            --text-dark: #2d3748;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --danger-color: #f56565;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            color: var(--text-dark);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 100;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 30px 25px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .avatar-small {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.4rem;
            color: white;
        }
        
        .user-info h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .user-info span {
            font-size: 0.85rem;
            color: var(--text-light);
            background: rgba(255, 255, 255, 0.1);
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .sidebar-menu {
            padding: 25px 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 25px;
            color: #cbd5e0;
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: var(--sidebar-hover);
            color: white;
            border-left-color: var(--primary-color);
        }
        
        .menu-item i {
            width: 20px;
            font-size: 1.2rem;
        }
        
        .menu-item span {
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--text-dark);
            font-size: 1.3rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .notification-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Profile Container */
        .profile-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            animation: slideUp 0.5s ease-out;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
        }
        
        .profile-info h2 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .user-role {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .form-section {
            background: #f8fafc;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .form-section h3 {
            color: var(--text-dark);
            margin-bottom: 25px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .form-section h3 i {
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: var(--transition);
            background: white;
        }
        
        .input-with-icon .icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Messages */
        .success, .error {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease-out;
        }
        
        .success {
            background: linear-gradient(45deg, #48bb78, #38a169);
            color: white;
        }
        
        .error {
            background: linear-gradient(45deg, #f56565, #e53e3e);
            color: white;
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e2e8f0;
        }
        
        .btn {
            padding: 18px 35px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: var(--text-dark);
            text-decoration: none;
            text-align: center;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        /* Password Strength */
        .password-strength {
            margin-top: 10px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: var(--transition);
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }
        
        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 99;
                background: var(--primary-color);
                color: white;
                border: none;
                padding: 12px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
            <div class="user-profile">
                <div class="avatar-small">
                    <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h4>
                    <span><?php echo htmlspecialchars($user['role'] ?? 'Member'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="menu-item active">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="users.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="analytics.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="messages.php" class="menu-item">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <span class="notification-badge">3</span>
            </a>
            <a href="reports.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="help.php" class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span>Help & Support</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Profile Settings</h1>
            <div class="header-actions">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">5</span>
                </button>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </div>
        </div>
        
        <!-- Profile Container -->
        <div class="profile-container">
            <div class="profile-header">
                <div class="avatar-large">
                    <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h2>
                    <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <div class="user-role">
                        <?php echo htmlspecialchars($user['role'] ?? 'Member'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (!empty($success)): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Profile Form -->
            <form method="POST" action="">
                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        
                        <div class="form-group">
                            <label>Username</label>
                            <div class="input-with-icon">
                                <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                                <i class="fas fa-user icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <div class="input-with-icon">
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="Enter your full name">
                                <i class="fas fa-signature icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-with-icon">
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required placeholder="Enter your email">
                                <i class="fas fa-envelope icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Settings -->
                    <div class="form-section">
                        <h3><i class="fas fa-key"></i> Security Settings</h3>
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <div class="input-with-icon">
                                <input type="password" name="current_password" placeholder="Enter current password">
                                <i class="fas fa-lock icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-with-icon">
                                <input type="password" name="new_password" id="new_password" placeholder="Enter new password">
                                <i class="fas fa-key icon"></i>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter" id="strengthMeter"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="input-with-icon">
                                <input type="password" name="confirm_password" placeholder="Confirm new password">
                                <i class="fas fa-check-circle icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthMeter = document.getElementById('strengthMeter');
            let strength = 0;
            
            if (password.length >= 6) strength += 20;
            if (password.length >= 8) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            
            strengthMeter.style.width = strength + '%';
            
            // Update color based on strength
            if (strength < 40) {
                strengthMeter.style.background = 'linear-gradient(45deg, #f56565, #e53e3e)';
            } else if (strength < 80) {
                strengthMeter.style.background = 'linear-gradient(45deg, #ed8936, #dd6b20)';
            } else {
                strengthMeter.style.background = 'linear-gradient(45deg, #48bb78, #38a169)';
            }
        });
        
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        // Show menu toggle on mobile
        if (window.innerWidth <= 992) {
            menuToggle.style.display = 'block';
        }
        
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        });
        
        // Update notification count
        function updateNotificationCount() {
            // Simulate new notifications
            const badge = document.querySelector('.notification-badge');
            let count = parseInt(badge.textContent);
            if (Math.random() > 0.7) {
                count++;
                badge.textContent = count;
                badge.style.animation = 'none';
                setTimeout(() => {
                    badge.style.animation = 'pulse 0.5s';
                }, 10);
            }
        }
        
        // Check for new notifications every 30 seconds
        setInterval(updateNotificationCount, 30000);
        
        // Add pulse animation for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
