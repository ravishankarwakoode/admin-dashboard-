<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

// Get user data from database
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get total users count
$total_users_result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $total_users_result->fetch_assoc()['total'];

// Get today's registrations
$today_users_result = $conn->query("SELECT COUNT(*) as today FROM users WHERE DATE(created_at) = CURDATE()");
$today_users = $today_users_result->fetch_assoc()['today'];

// Get admin count
$admin_count_result = $conn->query("SELECT COUNT(*) as admins FROM users WHERE role = 'admin'");
$admin_count = $admin_count_result->fetch_assoc()['admins'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
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
        
        /* Sidebar Styles */
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
            display: inline-block;
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
        
        /* Main Content Styles */
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
        
        /* Cards Grid */
        .cards { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px;
        }
        
        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid #3498db;
        }
        
        .card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .card:nth-child(2) {
            border-top-color: #2ecc71;
        }
        
        .card:nth-child(3) {
            border-top-color: #e74c3c;
        }
        
        .card:nth-child(4) {
            border-top-color: #f39c12;
        }
        
        .card-icon { 
            font-size: 2rem; 
            margin-bottom: 15px;
            color: #3498db;
        }
        
        .card:nth-child(2) .card-icon {
            color: #2ecc71;
        }
        
        .card:nth-child(3) .card-icon {
            color: #e74c3c;
        }
        
        .card:nth-child(4) .card-icon {
            color: #f39c12;
        }
        
        .card h3 { 
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card h2 { 
            font-size: 2.2rem; 
            color: #2c3e50;
            font-weight: 700;
        }
        
        /* Profile Section */
        .profile-section { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            margin-top: 25px;
        }
        
        .profile-section h2 { 
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .profile-info { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
        }
        
        .info-group { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .info-group label { 
            display: block; 
            color: #7f8c8d; 
            font-size: 0.9rem; 
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .info-group .value { 
            font-size: 1.1rem; 
            color: #2c3e50; 
            font-weight: 600;
        }
        
        /* Quick Actions */
        .quick-actions { 
            margin-top: 30px; 
        }
        
        .quick-actions h2 { 
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .action-buttons { 
            display: flex; 
            gap: 15px; 
            flex-wrap: wrap;
        }
        
        .action-btn { 
            padding: 12px 25px; 
            background: white; 
            color: #3498db; 
            border: 2px solid #3498db; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover { 
            background: #3498db; 
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .action-btn.delete-btn {
            border-color: #e74c3c;
            color: #e74c3c;
        }
        
        .action-btn.delete-btn:hover {
            background: #e74c3c;
            color: white;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .action-btn.success-btn {
            border-color: #2ecc71;
            color: #2ecc71;
        }
        
        .action-btn.success-btn:hover {
            background: #2ecc71;
            color: white;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        /* Responsive Design */
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
            
            .sidebar-menu i {
                font-size: 1.2rem;
            }
            
            .content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
            }
            
            .action-buttons {
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
            <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="active">
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
           
            <a href="reset.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Reset Session</span>
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
                    <h1>Welcome, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!</h1>
                    <p>Last login: <?php echo date('F j, Y, g:i a'); ?></p>
                </div>
                <div class="user-badge">
                    <?php echo ucfirst($user['role']); ?>
                </div>
            </div>
            <a href="includes/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Total Users</h3>
                <h2><?php echo $total_users; ?></h2>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3>Today's Registrations</h3>
                <h2><?php echo $today_users; ?></h2>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3>Admin Users</h3>
                <h2><?php echo $admin_count; ?></h2>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <h3>Current Date</h3>
                <h2><?php echo date('M d, Y'); ?></h2>
            </div>
        </div>
        
        <!-- User Profile Section -->
        <div class="profile-section">
            <h2><i class="fas fa-id-card"></i> Your Profile Information</h2>
            <div class="profile-info">
                <div class="info-group">
                    <label>User ID</label>
                    <div class="value">#<?php echo $user['id']; ?></div>
                </div>
                
                <div class="info-group">
                    <label>Username</label>
                    <div class="value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                
                <div class="info-group">
                    <label>Email Address</label>
                    <div class="value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                
                <div class="info-group">
                    <label>Full Name</label>
                    <div class="value"><?php echo htmlspecialchars($user['full_name'] ?: 'Not set'); ?></div>
                </div>
                
                <div class="info-group">
                    <label>Account Role</label>
                    <div class="value">
                        <span style="background: <?php echo $user['role'] == 'admin' ? '#e74c3c' : '#3498db'; ?>; 
                              color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-group">
                    <label>Member Since</label>
                    <div class="value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                </div>
                
                <div class="info-group">
                    <label>Last Updated</label>
                    <div class="value"><?php echo date('F j, Y, g:i a', strtotime($user['updated_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="action-buttons">
                <button onclick="location.href='users.php'" class="action-btn">
                    <i class="fas fa-users-cog"></i> Manage Users
                </button>
                <button onclick="location.href='register.php'" class="action-btn success-btn">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
                <button onclick="location.href='profile.php'" class="action-btn">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </button>
                <button onclick="location.href='reset.php'" class="action-btn delete-btn">
                    <i class="fas fa-redo"></i> Reset Session
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
        
        // Show welcome notification
        window.onload = function() {
            if(!sessionStorage.getItem('dashboardWelcome')) {
                setTimeout(function() {
                    alert('Welcome to your dashboard, <?php echo htmlspecialchars($user['username']); ?>!');
                }, 500);
                sessionStorage.setItem('dashboardWelcome', 'true');
            }
        };
        
        // Logout confirmation
        document.querySelector('.logout-btn').addEventListener('click', function(e) {
            if(!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>