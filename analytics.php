<?php
// live_users.php - Live Users Monitoring Dashboard
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

// Initialize variables
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get current user data
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get admin user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_user = $result->fetch_assoc();

// Get total users count
$count_query = "SELECT COUNT(*) as total FROM users WHERE id != ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $count_query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

if ($status_filter !== 'all') {
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($role_filter !== 'all') {
    $count_query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$stmt = $conn->prepare($count_query);
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users with pagination
$query = "SELECT 
            u.*,
            (SELECT MAX(login_time) FROM user_sessions WHERE user_id = u.id) as last_activity,
            (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND logout_time IS NULL) as active_sessions,
            (SELECT activity_type FROM user_activity WHERE user_id = u.id ORDER BY activity_time DESC LIMIT 1) as last_action
          FROM users u 
          WHERE u.id != ?";

$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

if ($status_filter !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$query .= " ORDER BY 
            CASE 
                WHEN active_sessions > 0 THEN 0
                WHEN last_activity IS NOT NULL THEN 1
                ELSE 2
            END,
            last_activity DESC 
            LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get real-time statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
        (SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE logout_time IS NULL) as online_now,
        (SELECT COUNT(*) FROM user_sessions WHERE DATE(login_time) = CURDATE()) as today_logins
    FROM users 
    WHERE id != ?
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get role distribution
$role_stmt = $conn->prepare("
    SELECT role, COUNT(*) as count 
    FROM users 
    WHERE id != ?
    GROUP BY role 
    ORDER BY count DESC
");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_distribution = $role_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent activities
$activities_stmt = $conn->prepare("
    SELECT 
        ua.*,
        u.username,
        u.full_name,
        u.role
    FROM user_activity ua
    JOIN users u ON ua.user_id = u.id
    ORDER BY ua.activity_time DESC 
    LIMIT 10
");
$activities_stmt->execute();
$recent_activities = $activities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Close statements
$stmt->close();
$stats_stmt->close();
$role_stmt->close();
$activities_stmt->close();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_user_id = intval($_POST['user_id']);
    
    switch ($action) {
        case 'activate':
            $update_stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $update_stmt->execute();
            break;
            
        case 'deactivate':
            $update_stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $update_stmt->execute();
            break;
            
        case 'suspend':
            $update_stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $update_stmt->execute();
            break;
            
        case 'delete':
            // Don't actually delete, just mark as deleted
            $update_stmt = $conn->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $update_stmt->execute();
            break;
            
        case 'promote_admin':
            $update_stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $update_stmt->execute();
            break;
            
        case 'demote_user':
            $update_stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $update_stmt->bind_param("i", $target_user_id);
            $update_stmt->execute();
            break;
    }
    
    // Log the action
    $log_stmt = $conn->prepare("
        INSERT INTO user_activity (user_id, activity_type, details) 
        VALUES (?, 'admin_action', ?)
    ");
    $log_details = json_encode([
        'action' => $action,
        'target_user' => $target_user_id,
        'performed_by' => $user_id
    ]);
    $log_stmt->bind_param("is", $user_id, $log_details);
    $log_stmt->execute();
    
    // Refresh the page
    header("Location: live_users.php?" . $_SERVER['QUERY_STRING']);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Users - Admin Dashboard</title>
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
            --info-color: #4299e1;
            --online-color: #38a169;
            --offline-color: #a0aec0;
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
        
        /* Live Users Container */
        .live-users-container {
            animation: slideUp 0.5s ease-out;
        }
        
        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }
        
        .stat-icon.online {
            background: linear-gradient(45deg, var(--online-color), #2f855a);
        }
        
        .stat-icon.total {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-icon.active {
            background: linear-gradient(45deg, var(--success-color), #38a169);
        }
        
        .stat-icon.today {
            background: linear-gradient(45deg, var(--info-color), #3182ce);
        }
        
        .stat-content h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-input, .filter-select {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            transition: var(--transition);
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filter-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .reset-btn {
            background: #e2e8f0;
            color: var(--text-dark);
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .reset-btn:hover {
            background: #cbd5e0;
        }
        
        /* Users Table */
        .users-table-container {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .users-table th {
            background: #f8fafc;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        
        .users-table td {
            padding: 18px 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .users-table tbody tr {
            transition: var(--transition);
        }
        
        .users-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .users-table tbody tr.online {
            border-left: 4px solid var(--online-color);
        }
        
        .users-table tbody tr.offline {
            border-left: 4px solid var(--offline-color);
        }
        
        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .user-details h4 {
            font-size: 1rem;
            margin-bottom: 4px;
            color: var(--text-dark);
        }
        
        .user-details p {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background: rgba(237, 137, 54, 0.1);
            color: var(--warning-color);
        }
        
        .status-suspended {
            background: rgba(245, 101, 101, 0.1);
            color: var(--danger-color);
        }
        
        .status-online {
            background: rgba(56, 161, 105, 0.1);
            color: var(--online-color);
        }
        
        .status-offline {
            background: rgba(160, 174, 192, 0.1);
            color: var(--offline-color);
        }
        
        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .role-badge.admin {
            background: rgba(245, 101, 101, 0.1);
            color: var(--danger-color);
        }
        
        .role-badge.moderator {
            background: rgba(237, 137, 54, 0.1);
            color: var(--warning-color);
        }
        
        .role-badge.user {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
        }
        
        .last-activity {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn.activate {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
        }
        
        .action-btn.activate:hover {
            background: var(--success-color);
            color: white;
        }
        
        .action-btn.deactivate {
            background: rgba(237, 137, 54, 0.1);
            color: var(--warning-color);
        }
        
        .action-btn.deactivate:hover {
            background: var(--warning-color);
            color: white;
        }
        
        .action-btn.suspend {
            background: rgba(245, 101, 101, 0.1);
            color: var(--danger-color);
        }
        
        .action-btn.suspend:hover {
            background: var(--danger-color);
            color: white;
        }
        
        .action-btn.promote {
            background: rgba(66, 153, 225, 0.1);
            color: var(--info-color);
        }
        
        .action-btn.promote:hover {
            background: var(--info-color);
            color: white;
        }
        
        .action-btn.view {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .action-btn.view:hover {
            background: var(--primary-color);
            color: white;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-btn {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .page-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Recent Activities */
        .recent-activities {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .recent-activities h3 {
            font-size: 1.4rem;
            color: var(--text-dark);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            background: var(--primary-color);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content h4 {
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .activity-content p {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .activity-time {
            color: var(--text-light);
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        /* Animations */
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
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes onlinePulse {
            0% { box-shadow: 0 0 0 0 rgba(56, 161, 105, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(56, 161, 105, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 161, 105, 0); }
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--online-color);
            display: inline-block;
            margin-right: 8px;
            animation: onlinePulse 2s infinite;
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
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-actions {
                flex-direction: column;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
        }
        
        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
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
                    <?php echo strtoupper(substr($admin_user['username'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($admin_user['full_name'] ?? 'Admin'); ?></h4>
                    <span>Administrator</span>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="live_users.php" class="menu-item active">
                <i class="fas fa-users"></i>
                <span>Live Users</span>
            </a>
            <a href="analytics.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
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
            <a href="activity_log.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Live Users Monitoring</h1>
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
        
        <!-- Live Users Container -->
        <div class="live-users-container">
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-item">
                    <div class="stat-icon online">
                        <i class="fas fa-signal"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['online_now'] ?? 0; ?></h3>
                        <p>Online Now</p>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon active">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active_users'] ?? 0; ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon today">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['today_logins'] ?? 0; ?></h3>
                        <p>Today's Logins</p>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="search"><i class="fas fa-search"></i> Search</label>
                            <input type="text" id="search" name="search" class="filter-input" 
                                   placeholder="Search by name, username, or email" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status"><i class="fas fa-circle"></i> Status</label>
                            <select id="status" name="status" class="filter-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="role"><i class="fas fa-user-tag"></i> Role</label>
                            <select id="role" name="role" class="filter-select">
                                <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="moderator" <?php echo $role_filter === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                                <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="live_users.php" class="reset-btn">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <span style="margin-left: auto; color: var(--text-light); font-size: 0.9rem;">
                            <?php echo $total_users; ?> users found
                        </span>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th>Last Activity</th>
                            <th>Current Session</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $is_online = ($user['active_sessions'] ?? 0) > 0;
                            $last_activity = $user['last_activity'] ? date('M d, Y H:i', strtotime($user['last_activity'])) : 'Never';
                            $current_session = $is_online ? 'Online now' : 'Offline';
                        ?>
                        <tr class="<?php echo $is_online ? 'online' : 'offline'; ?>">
                            <td>
                                <div class="user-info-cell">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h4><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="status-badge status-<?php echo $user['status'] ?? 'inactive'; ?>">
                                    <?php if ($is_online): ?>
                                        <span class="online-indicator"></span>
                                    <?php endif; ?>
                                    <?php echo ucfirst($user['status'] ?? 'inactive'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge <?php echo $user['role'] ?? 'user'; ?>">
                                    <?php echo ucfirst($user['role'] ?? 'user'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="last-activity">
                                    <?php echo $last_activity; ?>
                                    <?php if ($user['last_action']): ?>
                                        <br><small><?php echo htmlspecialchars($user['last_action']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $is_online ? 'status-online' : 'status-offline'; ?>">
                                    <?php echo $current_session; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['status'] !== 'active'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="action-btn activate" title="Activate User">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['status'] !== 'inactive'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="action-btn deactivate" title="Deactivate User">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['status'] !== 'suspended'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="action-btn suspend" title="Suspend User">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['role'] !== 'admin'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="promote_admin">
                                        <button type="submit" class="action-btn promote" title="Promote to Admin">
                                            <i class="fas fa-crown"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn view" 
                                            onclick="viewUserDetails(<?php echo $user['id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <i class="fas fa-users-slash" style="font-size: 3rem; color: var(--text-light); margin-bottom: 20px; display: block;"></i>
                                <h3 style="color: var(--text-light); margin-bottom: 10px;">No users found</h3>
                                <p style="color: var(--text-light);">Try adjusting your search filters</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <button class="page-btn" 
                            onclick="changePage(<?php echo max(1, $page - 1); ?>)" 
                            <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    
                    <?php for ($i = 1; $i <= min(5, $total_pages); $i++): ?>
                    <button class="page-btn <?php echo $i === $page ? 'active' : ''; ?>" 
                            onclick="changePage(<?php echo $i; ?>)">
                        <?php echo $i; ?>
                    </button>
                    <?php endfor; ?>
                    
                    <?php if ($total_pages > 5): ?>
                    <span style="padding: 10px; color: var(--text-light);">...</span>
                    <button class="page-btn <?php echo $page === $total_pages ? 'active' : ''; ?>" 
                            onclick="changePage(<?php echo $total_pages; ?>)">
                        <?php echo $total_pages; ?>
                    </button>
                    <?php endif; ?>
                    
                    <button class="page-btn" 
                            onclick="changePage(<?php echo min($total_pages, $page + 1); ?>)" 
                            <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activities -->
            <div class="recent-activities">
                <h3><i class="fas fa-history"></i> Recent User Activities</h3>
                <div class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php 
                            $icon = 'fa-user';
                            switch ($activity['activity_type']) {
                                case 'login': $icon = 'fa-sign-in-alt'; break;
                                case 'logout': $icon = 'fa-sign-out-alt'; break;
                                case 'profile_update': $icon = 'fa-user-edit'; break;
                                case 'password_change': $icon = 'fa-key'; break;
                                case 'admin_action': $icon = 'fa-cog'; break;
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <h4><?php echo htmlspecialchars($activity['full_name'] ?? $activity['username']); ?></h4>
                            <p><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></p>
                        </div>
                        <div class="activity-time">
                            <?php echo date('H:i', strtotime($activity['activity_time'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recent_activities)): ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-light);">
                        <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        <p>No recent activities</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <script>
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
        
        // Pagination function
        function changePage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }
        
        // View user details
        function viewUserDetails(userId) {
            // This would typically open a modal or redirect to user details page
            // For now, let's show an alert with the user ID
            alert(`Viewing details for user ID: ${userId}\n\nIn a real implementation, this would:\n1. Open a modal with user details\n2. Show complete activity history\n3. Allow editing user information\n4. Show statistics for this user`);
        }
        
        // Real-time updates
        let onlineCount = <?php echo $stats['online_now'] ?? 0; ?>;
        
        function updateOnlineStatus() {
            // Simulate real-time updates
            const onlineIndicator = document.querySelectorAll('.online-indicator');
            onlineIndicator.forEach(indicator => {
                if (Math.random() > 0.95) {
                    indicator.style.animation = 'none';
                    setTimeout(() => {
                        indicator.style.animation = 'onlinePulse 2s infinite';
                    }, 10);
                }
            });
            
            // Update online count randomly (for demo)
            if (Math.random() > 0.7) {
                const change = Math.random() > 0.5 ? 1 : -1;
                const newCount = Math.max(0, onlineCount + change);
                if (newCount !== onlineCount) {
                    onlineCount = newCount;
                    document.querySelector('.stat-item:nth-child(1) h3').textContent = onlineCount;
                    
                    // Add animation to stat
                    const stat = document.querySelector('.stat-item:nth-child(1) h3');
                    stat.style.animation = 'none';
                    setTimeout(() => {
                        stat.style.animation = 'pulse 0.5s';
                    }, 10);
                }
            }
        }
        
        // Update every 10 seconds
        setInterval(updateOnlineStatus, 10000);
        
        // Auto-refresh page every 60 seconds
        let refreshTimer = 60;
        const refreshInterval = setInterval(() => {
            refreshTimer--;
            document.title = `Live Users (Auto-refresh in ${refreshTimer}s) - Admin Dashboard`;
            
            if (refreshTimer <= 0) {
                clearInterval(refreshInterval);
                window.location.reload();
            }
        }, 1000);
        
        // Add pulse animation for stats
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            
            @keyframes onlinePulse {
                0% { box-shadow: 0 0 0 0 rgba(56, 161, 105, 0.4); }
                70% { box-shadow: 0 0 0 10px rgba(56, 161, 105, 0); }
                100% { box-shadow: 0 0 0 0 rgba(56, 161, 105, 0); }
            }
        `;
        document.head.appendChild(style);
        
        // Confirm destructive actions
        document.querySelectorAll('form').forEach(form => {
            const button = form.querySelector('button[type="submit"]');
            if (button && (button.classList.contains('suspend') || button.classList.contains('deactivate'))) {
                form.addEventListener('submit', function(e) {
                    const action = this.querySelector('input[name="action"]').value;
                    const confirmMsg = action === 'suspend' 
                        ? 'Are you sure you want to suspend this user?' 
                        : 'Are you sure you want to deactivate this user?';
                    
                    if (!confirm(confirmMsg + '\n\nThis action can be reversed later.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>