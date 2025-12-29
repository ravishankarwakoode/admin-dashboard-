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

// Get user data for sidebar
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Check if settings table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'settings'");
if ($table_check->num_rows == 0) {
    // Create settings table
    $create_sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        site_name VARCHAR(100) DEFAULT 'Admin Panel',
        site_email VARCHAR(100) DEFAULT 'admin@example.com',
        site_description TEXT,
        maintenance_mode TINYINT(1) DEFAULT 0,
        user_registration TINYINT(1) DEFAULT 1,
        email_notifications TINYINT(1) DEFAULT 1,
        timezone VARCHAR(50) DEFAULT 'UTC',
        theme_color VARCHAR(20) DEFAULT '#3498db',
        max_login_attempts INT DEFAULT 5,
        session_timeout INT DEFAULT 30,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($create_sql);
    
    // Insert default settings
    $conn->query("INSERT INTO settings (id) VALUES (1)");
} else {
    // Check if new columns exist, if not add them
    $columns_to_check = [
        'site_description' => "ALTER TABLE settings ADD COLUMN site_description TEXT AFTER site_email",
        'theme_color' => "ALTER TABLE settings ADD COLUMN theme_color VARCHAR(20) DEFAULT '#3498db' AFTER timezone",
        'max_login_attempts' => "ALTER TABLE settings ADD COLUMN max_login_attempts INT DEFAULT 5 AFTER theme_color",
        'session_timeout' => "ALTER TABLE settings ADD COLUMN session_timeout INT DEFAULT 30 AFTER max_login_attempts"
    ];
    
    foreach ($columns_to_check as $column => $alter_query) {
        $column_check = $conn->query("SHOW COLUMNS FROM settings LIKE '$column'");
        if ($column_check->num_rows == 0) {
            $conn->query($alter_query);
        }
    }
}

// Get system settings
$settings_stmt = $conn->query("SELECT * FROM settings WHERE id = 1");
$settings = $settings_stmt->fetch_assoc() ?? [];

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Basic settings
    $site_name = trim($_POST['site_name']);
    $site_email = trim($_POST['site_email']);
    $site_description = trim($_POST['site_description'] ?? '');
    $timezone = $_POST['timezone'];
    $theme_color = $_POST['theme_color'] ?? '#3498db';
    $max_login_attempts = intval($_POST['max_login_attempts'] ?? 5);
    $session_timeout = intval($_POST['session_timeout'] ?? 30);
    
    // Checkbox settings
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $user_registration = isset($_POST['user_registration']) ? 1 : 0;
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    
    // Validate email
    if (!filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format";
        $message_type = 'error';
    } elseif ($max_login_attempts < 1 || $max_login_attempts > 10) {
        $message = "Max login attempts must be between 1 and 10";
        $message_type = 'error';
    } elseif ($session_timeout < 5 || $session_timeout > 1440) {
        $message = "Session timeout must be between 5 and 1440 minutes";
        $message_type = 'error';
    } else {
        // Check which columns exist before building the query
        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        
        // Always existing columns
        $columns[] = 'site_name';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $site_name;
        
        $columns[] = 'site_email';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $site_email;
        
        // Check for site_description column
        $column_check = $conn->query("SHOW COLUMNS FROM settings LIKE 'site_description'");
        if ($column_check->num_rows > 0) {
            $columns[] = 'site_description';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $site_description;
        }
        
        // Checkbox columns
        $columns[] = 'maintenance_mode';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $maintenance_mode;
        
        $columns[] = 'user_registration';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $user_registration;
        
        $columns[] = 'email_notifications';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $email_notifications;
        
        $columns[] = 'timezone';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $timezone;
        
        // Check for theme_color column
        $column_check = $conn->query("SHOW COLUMNS FROM settings LIKE 'theme_color'");
        if ($column_check->num_rows > 0) {
            $columns[] = 'theme_color';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $theme_color;
        }
        
        // Check for max_login_attempts column
        $column_check = $conn->query("SHOW COLUMNS FROM settings LIKE 'max_login_attempts'");
        if ($column_check->num_rows > 0) {
            $columns[] = 'max_login_attempts';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $max_login_attempts;
        }
        
        // Check for session_timeout column
        $column_check = $conn->query("SHOW COLUMNS FROM settings LIKE 'session_timeout'");
        if ($column_check->num_rows > 0) {
            $columns[] = 'session_timeout';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $session_timeout;
        }
        
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
        
        // Build the update query
        $set_clause = [];
        for ($i = 0; $i < count($columns); $i++) {
            if ($columns[$i] == 'updated_at') {
                $set_clause[] = "updated_at = NOW()";
            } else {
                $set_clause[] = "{$columns[$i]} = {$placeholders[$i]}";
            }
        }
        
        $update_sql = "UPDATE settings SET " . implode(', ', $set_clause) . " WHERE id = 1";
        
        $update_stmt = $conn->prepare($update_sql);
        
        if ($update_stmt) {
            // Bind parameters for the placeholders
            if (!empty($values)) {
                $update_stmt->bind_param($types, ...$values);
            }
            
            if ($update_stmt->execute()) {
                $message = "🎉 Settings updated successfully!";
                $message_type = 'success';
                
                // Update local settings array
                $settings['site_name'] = $site_name;
                $settings['site_email'] = $site_email;
                $settings['site_description'] = $site_description;
                $settings['maintenance_mode'] = $maintenance_mode;
                $settings['user_registration'] = $user_registration;
                $settings['email_notifications'] = $email_notifications;
                $settings['timezone'] = $timezone;
                $settings['theme_color'] = $theme_color;
                $settings['max_login_attempts'] = $max_login_attempts;
                $settings['session_timeout'] = $session_timeout;
            } else {
                $message = "❌ Error updating settings: " . $conn->error;
                $message_type = 'error';
            }
            $update_stmt->close();
        } else {
            $message = "❌ Error preparing statement: " . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get system info for display
$system_info = [
    'PHP Version' => PHP_VERSION,
    'MySQL Version' => $conn->server_info,
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server Name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
    'Current Time' => date('Y-m-d H:i:s'),
    'Timezone' => date_default_timezone_get(),
    'Memory Limit' => ini_get('memory_limit'),
    'Max Upload Size' => ini_get('upload_max_filesize'),
    'Max Execution Time' => ini_get('max_execution_time') . ' seconds'
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ System Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($settings['theme_color'] ?? '#3498db'); ?>;
            --primary-gradient: linear-gradient(135deg, var(--primary-color) 0%, #2980b9 100%);
            --secondary-gradient: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            --success-gradient: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            --warning-gradient: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            --danger-gradient: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            animation: gradientBG 10s ease infinite;
            background-size: 400% 400%;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Floating Background Elements */
        .floating-bg {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            color: white;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 30px rgba(0,0,0,0.2);
            border-right: 1px solid rgba(255,255,255,0.2);
            z-index: 100;
            transition: var(--transition);
        }
        
        .sidebar:hover {
            backdrop-filter: blur(25px);
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            border: 4px solid rgba(255,255,255,0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 25px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.15);
            border-left: 4px solid var(--primary-color);
            padding-left: 30px;
            color: white;
        }
        
        .sidebar-menu a.active {
            background: rgba(52, 152, 219, 0.3);
            border-left: 4px solid var(--primary-color);
            color: white;
        }
        
        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .sidebar-menu a:hover::before {
            left: 100%;
        }
        
        /* Main Content */
        .content {
            margin-left: 280px;
            padding: 30px;
            width: calc(100% - 280px);
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Header */
        .header {
            background: var(--glass-bg);
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .welcome-message h1 {
            font-size: 2.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .welcome-message p {
            color: #666;
            font-size: 1rem;
        }
        
        .header-badges {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .user-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .logout-btn {
            background: var(--danger-gradient);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(231, 76, 60, 0.4);
        }
        
        /* Message Alert */
        .message-alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideDown 0.5s ease-out;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .message-alert.success {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.9), rgba(39, 174, 96, 0.9));
            color: white;
        }
        
        .message-alert.error {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.9), rgba(192, 57, 43, 0.9));
            color: white;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .stat-card p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* Settings Container */
        .settings-container {
            background: var(--glass-bg);
            border-radius: 25px;
            box-shadow: var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        /* Section Header */
        .section-header {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(52, 152, 219, 0.2);
            position: relative;
        }
        
        .section-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100px;
            height: 2px;
            background: var(--primary-gradient);
        }
        
        .section-header h2 {
            font-size: 1.8rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-header h2 i {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 15px 30px;
            background: transparent;
            border: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-weight: 600;
            color: #7f8c8d;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tab-btn.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .tab-btn:hover:not(.active) {
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 12px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group label i {
            color: var(--primary-color);
        }
        
        .form-control {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(248, 250, 252, 0.8);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
            transform: translateY(-2px);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Color Picker */
        .color-picker-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .color-picker {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            border: 3px solid #fff;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Switch Toggle */
        .switch-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            transition: var(--transition);
        }
        
        .switch-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .switch-label {
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background: var(--success-gradient);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .switch-text {
            flex: 1;
        }
        
        .switch-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .switch-description {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* Range Slider */
        .range-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .range-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-top: 10px;
        }
        
        /* Save Button */
        .save-section {
            text-align: center;
            margin: 50px 0 30px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-save {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 20px 50px;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-save::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        
        .btn-save:hover::before {
            left: 100%;
        }
        
        .btn-save:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 40px rgba(52, 152, 219, 0.4);
        }
        
        /* System Info */
        .system-info {
            background: var(--glass-bg);
            border-radius: 20px;
            padding: 30px;
            margin-top: 40px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2,
            .sidebar-menu span {
                display: none;
            }
            
            .sidebar-header {
                padding: 20px 10px;
            }
            
            .user-avatar {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
            
            .content {
                margin-left: 80px;
                width: calc(100% - 80px);
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-badges {
                flex-direction: column;
                width: 100%;
            }
            
            .settings-tabs {
                flex-wrap: wrap;
            }
            
            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-bg" id="floatingBg"></div>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
            </div>
            <h2>Admin Panel</h2>
            <p class="user-role"><?php echo isset($user['role']) ? ucfirst($user['role']) : 'User'; ?></p>
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
            <a href="settings.php" class="active">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
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
            <div class="welcome-message">
                <h1>⚙️ System Settings</h1>
                <p>Configure and customize your application settings</p>
            </div>
            <div class="header-badges">
                <div class="user-badge">
                    <i class="fas fa-user-shield"></i>
                    <?php echo isset($user['role']) ? ucfirst($user['role']) : 'User'; ?>
                </div>
                <a href="includes/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-cogs"></i>
                <h3>12</h3>
                <p>Active Settings</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo rand(50, 200); ?></h3>
                <p>Registered Users</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shield-alt"></i>
                <h3>99.9%</h3>
                <p>System Uptime</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-bolt"></i>
                <h3><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?>MB</h3>
                <p>Memory Usage</p>
            </div>
        </div>
        
        <!-- Message Alert -->
        <?php if($message): ?>
        <div class="message-alert <?php echo $message_type; ?>">
            <div>
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <button class="close-message">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Settings Container -->
        <div class="settings-container">
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-btn active" data-tab="general">
                    <i class="fas fa-cog"></i> General
                </button>
                <button class="tab-btn" data-tab="security">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button class="tab-btn" data-tab="appearance">
                    <i class="fas fa-palette"></i> Appearance
                </button>
                <button class="tab-btn" data-tab="advanced">
                    <i class="fas fa-sliders-h"></i> Advanced
                </button>
            </div>
            
            <form method="POST" action="">
                <!-- General Tab -->
                <div class="tab-content active" id="general">
                    <div class="section-header">
                        <h2><i class="fas fa-info-circle"></i> General Settings</h2>
                        <p>Configure your application's basic information and features</p>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="site_name"><i class="fas fa-globe"></i> Site Name</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Admin Panel'); ?>" required
                                       placeholder="Enter your site name">
                            </div>
                            
                            <div class="form-group">
                                <label for="site_email"><i class="fas fa-envelope"></i> Site Email</label>
                                <input type="email" id="site_email" name="site_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['site_email'] ?? 'admin@example.com'); ?>" required
                                       placeholder="Enter contact email">
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone"><i class="fas fa-clock"></i> Timezone</label>
                                <select id="timezone" name="timezone" class="form-control">
                                    <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') == 'UTC' ? 'selected' : ''; ?>>UTC (Coordinated Universal Time)</option>
                                    <option value="America/New_York" <?php echo ($settings['timezone'] ?? 'UTC') == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (ET)</option>
                                    <option value="America/Chicago" <?php echo ($settings['timezone'] ?? 'UTC') == 'America/Chicago' ? 'selected' : ''; ?>>Central Time (CT)</option>
                                    <option value="America/Denver" <?php echo ($settings['timezone'] ?? 'UTC') == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time (MT)</option>
                                    <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? 'UTC') == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (PT)</option>
                                    <option value="Europe/London" <?php echo ($settings['timezone'] ?? 'UTC') == 'Europe/London' ? 'selected' : ''; ?>>London (GMT)</option>
                                    <option value="Europe/Paris" <?php echo ($settings['timezone'] ?? 'UTC') == 'Europe/Paris' ? 'selected' : ''; ?>>Paris (CET)</option>
                                    <option value="Asia/Kolkata" <?php echo ($settings['timezone'] ?? 'UTC') == 'Asia/Kolkata' ? 'selected' : ''; ?>>India (IST)</option>
                                    <option value="Asia/Tokyo" <?php echo ($settings['timezone'] ?? 'UTC') == 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo (JST)</option>
                                    <option value="Australia/Sydney" <?php echo ($settings['timezone'] ?? 'UTC') == 'Australia/Sydney' ? 'selected' : ''; ?>>Sydney (AEST)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="site_description"><i class="fas fa-align-left"></i> Site Description</label>
                                <textarea id="site_description" name="site_description" class="form-control" 
                                          placeholder="Describe your application or website..."><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Feature Toggles -->
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                               value="1" <?php echo ($settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">🛠️ Maintenance Mode</div>
                                        <div class="switch-description">When enabled, only administrators can access the site</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="user_registration" name="user_registration" 
                                               value="1" <?php echo ($settings['user_registration'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">👥 User Registration</div>
                                        <div class="switch-description">Allow new users to register accounts</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="email_notifications" name="email_notifications" 
                                               value="1" <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">📧 Email Notifications</div>
                                        <div class="switch-description">Send email notifications for system events</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-content" id="security">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                        <p>Configure security and authentication settings</p>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <?php if (isset($settings['max_login_attempts'])): ?>
                            <div class="range-container">
                                <label for="max_login_attempts"><i class="fas fa-sign-in-alt"></i> Max Login Attempts</label>
                                <input type="range" id="max_login_attempts" name="max_login_attempts" 
                                       min="1" max="10" step="1" 
                                       value="<?php echo $settings['max_login_attempts'] ?? 5; ?>"
                                       class="form-control" style="width: 100%;">
                                <div class="range-value" id="loginAttemptsValue"><?php echo $settings['max_login_attempts'] ?? 5; ?> attempts</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($settings['session_timeout'])): ?>
                            <div class="range-container">
                                <label for="session_timeout"><i class="fas fa-hourglass-half"></i> Session Timeout</label>
                                <input type="range" id="session_timeout" name="session_timeout" 
                                       min="5" max="1440" step="5" 
                                       value="<?php echo $settings['session_timeout'] ?? 30; ?>"
                                       class="form-control" style="width: 100%;">
                                <div class="range-value" id="sessionTimeoutValue"><?php echo $settings['session_timeout'] ?? 30; ?> minutes</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="force_https" name="force_https" value="1">
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">🔒 Force HTTPS</div>
                                        <div class="switch-description">Redirect all HTTP traffic to HTTPS</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="password_reset" name="password_reset" value="1" checked>
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">🔑 Password Reset</div>
                                        <div class="switch-description">Allow users to reset forgotten passwords</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="login_attempts" name="login_attempts" value="1" checked>
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">🚫 Login Attempt Limit</div>
                                        <div class="switch-description">Block IP after multiple failed login attempts</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="two_factor" name="two_factor" value="1">
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">🔐 Two-Factor Authentication</div>
                                        <div class="switch-description">Enable 2FA for enhanced security</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Appearance Tab -->
                <div class="tab-content" id="appearance">
                    <div class="section-header">
                        <h2><i class="fas fa-palette"></i> Appearance Settings</h2>
                        <p>Customize the look and feel of your application</p>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <?php if (isset($settings['theme_color'])): ?>
                            <div class="form-group">
                                <label for="theme_color"><i class="fas fa-fill-drip"></i> Theme Color</label>
                                <div class="color-picker-container">
                                    <input type="color" id="theme_color" name="theme_color" 
                                           value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#3498db'); ?>"
                                           class="color-picker">
                                    <input type="text" id="theme_color_text" 
                                           value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#3498db'); ?>"
                                           class="form-control" style="flex: 1;">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="theme_mode"><i class="fas fa-moon"></i> Theme Mode</label>
                                <select id="theme_mode" name="theme_mode" class="form-control">
                                    <option value="light">🌞 Light Mode</option>
                                    <option value="dark">🌙 Dark Mode</option>
                                    <option value="auto">🤖 Auto (System Preference)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="font_family"><i class="fas fa-font"></i> Font Family</label>
                                <select id="font_family" name="font_family" class="form-control">
                                    <option value="Segoe UI">Segoe UI (System Default)</option>
                                    <option value="Arial">Arial</option>
                                    <option value="Helvetica">Helvetica</option>
                                    <option value="Georgia">Georgia</option>
                                    <option value="Times New Roman">Times New Roman</option>
                                    <option value="Courier New">Courier New</option>
                                    <option value="Verdana">Verdana</option>
                                    <option value="Tahoma">Tahoma</option>
                                </select>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="animations" name="animations" value="1" checked>
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">🎭 Animations</div>
                                        <div class="switch-description">Enable smooth animations and transitions</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="switch-container">
                                <label class="switch-label">
                                    <div class="switch">
                                        <input type="checkbox" id="sidebar_collapse" name="sidebar_collapse" value="1">
                                        <span class="slider"></span>
                                    </div>
                                    <div class="switch-text">
                                        <div class="switch-title">📱 Collapsible Sidebar</div>
                                        <div class="switch-description">Allow sidebar to collapse on mobile</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Tab -->
                <div class="tab-content" id="advanced">
                    <div class="section-header">
                        <h2><i class="fas fa-sliders-h"></i> Advanced Settings</h2>
                        <p>Advanced configuration options for power users</p>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="cache_duration"><i class="fas fa-database"></i> Cache Duration</label>
                                <select id="cache_duration" name="cache_duration" class="form-control">
                                    <option value="0">No Caching</option>
                                    <option value="300">5 Minutes</option>
                                    <option value="900">15 Minutes</option>
                                    <option value="1800">30 Minutes</option>
                                    <option value="3600">1 Hour</option>
                                    <option value="86400">24 Hours</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="log_level"><i class="fas fa-clipboard-list"></i> Log Level</label>
                                <select id="log_level" name="log_level" class="form-control">
                                    <option value="error">Errors Only</option>
                                    <option value="warning">Warnings & Errors</option>
                                    <option value="info" selected>Info, Warnings & Errors</option>
                                    <option value="debug">Debug (All Messages)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="backup_frequency"><i class="fas fa-save"></i> Backup Frequency</label>
                                <select id="backup_frequency" name="backup_frequency" class="form-control">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="never">Never</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="api_rate_limit"><i class="fas fa-tachometer-alt"></i> API Rate Limit</label>
                                <select id="api_rate_limit" name="api_rate_limit" class="form-control">
                                    <option value="10">10 requests/minute</option>
                                    <option value="30">30 requests/minute</option>
                                    <option value="60" selected>60 requests/minute</option>
                                    <option value="100">100 requests/minute</option>
                                    <option value="unlimited">Unlimited</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="save-section">
                    <button type="submit" name="update_settings" class="btn-save">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </form>
        </div>
        
        <!-- System Information -->
        <div class="system-info">
            <div class="section-header">
                <h2><i class="fas fa-server"></i> System Information</h2>
                <p>Current system configuration and status</p>
            </div>
            
            <div class="info-grid">
                <?php foreach ($system_info as $label => $value): ?>
                <div class="info-item">
                    <div class="info-label"><?php echo htmlspecialchars($label); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($value); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Floating background elements
        const floatingBg = document.getElementById('floatingBg');
        for (let i = 0; i < 15; i++) {
            const circle = document.createElement('div');
            circle.className = 'floating-circle';
            circle.style.width = Math.random() * 200 + 50 + 'px';
            circle.style.height = circle.style.width;
            circle.style.left = Math.random() * 100 + '%';
            circle.style.top = Math.random() * 100 + '%';
            circle.style.animationDelay = Math.random() * 20 + 's';
            circle.style.animationDuration = Math.random() * 20 + 20 + 's';
            floatingBg.appendChild(circle);
        }
        
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all tabs
                document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                btn.classList.add('active');
                document.getElementById(btn.dataset.tab).classList.add('active');
            });
        });
        
        // Range slider value display
        const loginAttemptsSlider = document.getElementById('max_login_attempts');
        const loginAttemptsValue = document.getElementById('loginAttemptsValue');
        const sessionTimeoutSlider = document.getElementById('session_timeout');
        const sessionTimeoutValue = document.getElementById('sessionTimeoutValue');
        
        if (loginAttemptsSlider) {
            loginAttemptsSlider.addEventListener('input', () => {
                if (loginAttemptsValue) {
                    loginAttemptsValue.textContent = loginAttemptsSlider.value + ' attempts';
                }
            });
        }
        
        if (sessionTimeoutSlider) {
            sessionTimeoutSlider.addEventListener('input', () => {
                if (sessionTimeoutValue) {
                    sessionTimeoutValue.textContent = sessionTimeoutSlider.value + ' minutes';
                }
            });
        }
        
        // Color picker synchronization
        const colorPicker = document.getElementById('theme_color');
        const colorText = document.getElementById('theme_color_text');
        
        if (colorPicker && colorText) {
            colorPicker.addEventListener('input', () => {
                colorText.value = colorPicker.value;
                document.documentElement.style.setProperty('--primary-color', colorPicker.value);
            });
            
            colorText.addEventListener('input', () => {
                if (/^#[0-9A-F]{6}$/i.test(colorText.value)) {
                    colorPicker.value = colorText.value;
                    document.documentElement.style.setProperty('--primary-color', colorText.value);
                }
            });
        }
        
        // Close message alert
        document.querySelectorAll('.close-message').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.style.animation = 'slideDown 0.5s ease-out reverse';
                setTimeout(() => this.parentElement.remove(), 500);
            });
        });
        
        // Auto-hide message after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.message-alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const siteName = document.getElementById('site_name').value.trim();
            const siteEmail = document.getElementById('site_email').value.trim();
            
            if (!siteName || !siteEmail) {
                e.preventDefault();
                showNotification('❌ Please fill in all required fields.', 'error');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(siteEmail)) {
                e.preventDefault();
                showNotification('❌ Please enter a valid email address.', 'error');
                return false;
            }
            
            // Show saving animation
            const saveBtn = document.querySelector('.btn-save');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;
            
            setTimeout(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }, 2000);
            
            return true;
        });
        
        // Notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `message-alert ${type}`;
            notification.innerHTML = `
                <div>
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
                <button class="close-message">&times;</button>
            `;
            
            document.querySelector('.content').insertBefore(notification, document.querySelector('.stats-grid'));
            
            // Add close functionality
            notification.querySelector('.close-message').addEventListener('click', () => {
                notification.remove();
            });
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Add some interactive effects
        document.querySelectorAll('.form-control, select').forEach(el => {
            el.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-5px)';
            });
            
            el.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
        
        // Animate stats cards on load
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'slideIn 0.5s ease-out forwards';
            card.style.opacity = '0';
        });
    </script>
</body>
</html>