<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$view_id = $_GET['id'];

$conn = getDBConnection();

// Get current user
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Get user to view
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $view_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
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
    <title>View User - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --sidebar-bg: #1a2035;
            --content-bg: #f8fafc;
            --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            display: flex;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            background: linear-gradient(45deg, #fff, #a8c6ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-role {
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 5px 12px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            display: inline-block;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: #667eea;
            padding-left: 30px;
        }
        
        .sidebar-menu a i {
            width: 25px;
            font-size: 1.1rem;
            margin-right: 15px;
        }
        
        .sidebar-menu a.active {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            border-left-color: #667eea;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }
        
        /* Header Styles */
        .header {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            border-left: 5px solid #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .welcome-message h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 5px;
            background: linear-gradient(45deg, #2c3e50, #4a6fa5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .welcome-message p {
            color: #7f8c8d;
            font-size: 0.95rem;
        }
        
        .user-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
        }
        
        /* User Profile Styles */
        .user-profile {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 40px;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        
        .user-profile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--primary-gradient);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px dashed #eef2f7;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .avatar::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            100% { transform: translateX(100%); }
        }
        
        .profile-info h2 {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .profile-info p {
            color: #667eea;
            font-size: 1.1rem;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.1);
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #eef2f7;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }
        
        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--secondary-gradient);
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-card h3 i {
            color: #667eea;
            font-size: 1.2rem;
            background: rgba(102, 126, 234, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .info-item {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #eef2f7;
        }
        
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #718096;
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .role-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-admin { background: linear-gradient(45deg, #ff6b6b, #ee5a52); color: white; }
        .role-user { background: linear-gradient(45deg, #4ecdc4, #44a08d); color: white; }
        .role-editor { background: linear-gradient(45deg, #45b7d1, #96c93d); color: white; }
        
        .status-active {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #38a169;
            font-weight: 600;
            background: rgba(56, 161, 105, 0.1);
            padding: 6px 15px;
            border-radius: 15px;
        }
        
        .status-active::before {
            content: '';
            width: 10px;
            height: 10px;
            background: #38a169;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(56, 161, 105, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(56, 161, 105, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 161, 105, 0); }
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eef2f7;
        }
        
        .btn-action {
            padding: 15px 35px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-edit {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-edit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-cancel {
            background: #f8f9fa;
            color: #718096;
            border: 2px solid #e2e8f0;
        }
        
        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-3px);
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2,
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 20px;
            }
            
            .sidebar-menu a i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            
            .content {
                margin-left: 80px;
                padding: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                justify-content: center;
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
    
    <div class="content">
        <div class="header">
            <div class="user-info">
                <div class="welcome-message">
                    <h1>View User Profile</h1>
                    <p>Detailed information about user account</p>
                </div>
                <div class="user-badge">Administrator</div>
            </div>
            <a href="includes/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <div class="user-profile">
            <div class="profile-header">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                </div>
            </div>
            
            <div class="profile-grid">
                <div class="info-card">
                    <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                    <div class="info-item">
                        <span class="info-label">User ID</span>
                        <span class="info-value">#<?php echo $user['id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['full_name'] ?: 'Not set'); ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-shield-alt"></i> Account Details</h3>
                    <div class="info-item">
                        <span class="info-label">Role</span>
                        <span class="info-value">
                            <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            <span class="status-active">Active</span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Created</span>
                        <span class="info-value"><?php echo date('F j, Y, g:i a', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Updated</span>
                        <span class="info-value"><?php echo date('F j, Y, g:i a', strtotime($user['updated_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit">
                    <i class="fas fa-edit"></i> Edit User Profile
                </a>
                <a href="users.php" class="btn-action btn-cancel">
                    <i class="fas fa-arrow-left"></i> Back to Users List
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements on load
            const cards = document.querySelectorAll('.info-card');
            cards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.5s ease ${index * 0.1}s forwards`;
                card.style.opacity = '0';
            });
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>