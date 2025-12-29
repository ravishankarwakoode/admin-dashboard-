<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin (optional, but recommended)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
    header("Location: dashboard.php");
    exit();
}

require_once 'config/database.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get current user data for sidebar
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
$message = '';
$message_type = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Validate form data
    $errors = [];
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username cannot exceed 50 characters";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists";
        }
        $stmt->close();
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (strlen($email) > 100) {
        $errors[] = "Email cannot exceed 100 characters";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        $stmt->close();
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Validate full name
    if (!empty($full_name) && strlen($full_name) > 100) {
        $errors[] = "Full name cannot exceed 100 characters";
    }
    
    // If no errors, proceed with user creation
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare SQL statement
            $sql = "INSERT INTO users (username, email, password, full_name, role, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $username, $email, $hashed_password, $full_name, $role, $status);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                
                // Log the action (optional - you can create a logging function)
                // logAction("Admin added new user: $username ($email)");
                
                // Set success message
                $message = "🎉 User '$username' has been successfully created!";
                $message_type = 'success';
                
                // Clear form data
                $form_data = [];
                
                // Redirect to users page after 2 seconds
                header("refresh:2;url=users.php");
            } else {
                $message = "❌ Error creating user: " . $conn->error;
                $message_type = 'error';
                // Keep form data for re-filling
                $form_data = $_POST;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $message = "❌ Database error: " . $e->getMessage();
            $message_type = 'error';
            $form_data = $_POST;
        }
    } else {
        // Display errors
        $message = "❌ " . implode("<br>", $errors);
        $message_type = 'error';
        $form_data = $_POST;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Admin Panel</title>
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
        
        /* Back Button */
        .back-btn {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        /* Message Alert */
        .message-alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideDown 0.5s ease-out;
        }
        
        .message-alert.success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .message-alert.error {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Add User Container */
        .add-user-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
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
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group label .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            padding-right: 40px;
        }
        
        /* Password Strength Meter */
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-meter {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-bottom: 5px;
            overflow: hidden;
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 2px;
        }
        
        .strength-text {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        /* Help Text */
        .help-text {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .help-text i {
            color: #3498db;
        }
        
        /* Button Group */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-submit {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn-reset {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }
        
        .btn-cancel {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        /* Role Badge Preview */
        .role-badge-preview {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .role-admin-preview {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
        }
        
        .role-user-preview {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .role-moderator-preview {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
        }
        
        /* Status Badge Preview */
        .status-badge-preview {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-active-preview {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        
        .status-inactive-preview {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .status-suspended-preview {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
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
            
            .sidebar-header {
                padding: 20px 10px;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .add-user-container {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn-submit, .btn-reset, .btn-cancel {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .content {
                padding: 10px;
            }
            
            .add-user-container {
                padding: 15px;
            }
            
            .form-control {
                padding: 10px 12px;
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
            <a href="add_user.php" class="active">
                <i class="fas fa-user-plus"></i>
                <span>Add User</span>
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
                    <h1>➕ Add New User</h1>
                    <p>Create a new user account in the system</p>
                </div>
                <div class="user-badge">
                    <i class="fas fa-user-shield"></i>
                    <?php echo ucfirst($current_user['role']); ?>
                </div>
            </div>
            <a href="includes/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
        
        <!-- Back Button -->
        <a href="users.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
        
        <!-- Message Alert -->
        <?php if($message): ?>
        <div class="message-alert <?php echo $message_type; ?>">
            <div>
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <button class="close-message">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Add User Form -->
        <div class="add-user-container">
            <div class="section-header">
                <h2><i class="fas fa-user-plus"></i> User Information</h2>
                <p>Fill in the details below to create a new user account</p>
            </div>
            
            <form method="POST" action="" id="addUserForm">
                <div class="form-grid">
                    <!-- Left Column -->
                    <div>
                        <!-- Username -->
                        <div class="form-group">
                            <label for="username">
                                <i class="fas fa-user"></i> Username 
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                                   placeholder="Enter username"
                                   required
                                   minlength="3"
                                   maxlength="50">
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> 3-50 characters, letters, numbers, and underscores only
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email Address 
                                <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                   placeholder="Enter email address"
                                   required>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> A valid email address is required
                            </div>
                        </div>
                        
                        <!-- Full Name -->
                        <div class="form-group">
                            <label for="full_name">
                                <i class="fas fa-id-card"></i> Full Name (Optional)
                            </label>
                            <input type="text" 
                                   id="full_name" 
                                   name="full_name" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>"
                                   placeholder="Enter full name"
                                   maxlength="100">
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <!-- Password -->
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i> Password 
                                <span class="required">*</span>
                            </label>
                            <div class="password-wrapper">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Enter password"
                                       required
                                       minlength="8">
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter">
                                    <div class="strength-meter-fill" id="passwordStrength"></div>
                                </div>
                                <div class="strength-text" id="passwordStrengthText">Password strength: None</div>
                            </div>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> Minimum 8 characters with uppercase, lowercase, number, and special character
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i> Confirm Password 
                                <span class="required">*</span>
                            </label>
                            <div class="password-wrapper">
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       placeholder="Confirm password"
                                       required
                                       minlength="8">
                                <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> Re-enter the password for confirmation
                            </div>
                        </div>
                        
                        <!-- Role -->
                        <div class="form-group">
                            <label for="role">
                                <i class="fas fa-user-tag"></i> User Role 
                                <span class="required">*</span>
                            </label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="">Select a role</option>
                                <option value="user" <?php echo (isset($form_data['role']) && $form_data['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                <option value="moderator" <?php echo (isset($form_data['role']) && $form_data['role'] == 'moderator') ? 'selected' : ''; ?>>Moderator</option>
                                <option value="admin" <?php echo (isset($form_data['role']) && $form_data['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> Select the user's permission level
                                <span id="rolePreview" class="role-badge-preview"></span>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="form-group">
                            <label for="status">
                                <i class="fas fa-circle"></i> Account Status 
                                <span class="required">*</span>
                            </label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="">Select status</option>
                                <option value="active" <?php echo (isset($form_data['status']) && $form_data['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($form_data['status']) && $form_data['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo (isset($form_data['status']) && $form_data['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> Set the initial account status
                                <span id="statusPreview" class="status-badge-preview"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Button Group -->
                <div class="button-group">
                    <button type="submit" name="add_user" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                    <button type="reset" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                    <a href="users.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthMeter = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            // Check password criteria
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[\W_]/.test(password)) strength++;
            
            // Update strength meter
            const width = (strength / 5) * 100;
            strengthMeter.style.width = width + '%';
            
            // Update strength text and color
            switch (strength) {
                case 0:
                    text = 'None';
                    color = '#e74c3c';
                    break;
                case 1:
                    text = 'Very Weak';
                    color = '#e74c3c';
                    break;
                case 2:
                    text = 'Weak';
                    color = '#e67e22';
                    break;
                case 3:
                    text = 'Fair';
                    color = '#f39c12';
                    break;
                case 4:
                    text = 'Strong';
                    color = '#2ecc71';
                    break;
                case 5:
                    text = 'Very Strong';
                    color = '#27ae60';
                    break;
            }
            
            strengthMeter.style.backgroundColor = color;
            strengthText.textContent = 'Password strength: ' + text;
            strengthText.style.color = color;
        });
        
        // Role badge preview
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const preview = document.getElementById('rolePreview');
            
            if (role) {
                preview.textContent = role.charAt(0).toUpperCase() + role.slice(1);
                preview.className = 'role-badge-preview role-' + role + '-preview';
                preview.style.display = 'inline-block';
            } else {
                preview.style.display = 'none';
            }
        });
        
        // Status badge preview
        document.getElementById('status').addEventListener('change', function() {
            const status = this.value;
            const preview = document.getElementById('statusPreview');
            
            if (status) {
                preview.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                preview.className = 'status-badge-preview status-' + status + '-preview';
                preview.style.display = 'inline-block';
            } else {
                preview.style.display = 'none';
            }
        });
        
        // Initialize previews if values are already selected
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const statusSelect = document.getElementById('status');
            
            if (roleSelect.value) {
                roleSelect.dispatchEvent(new Event('change'));
            }
            
            if (statusSelect.value) {
                statusSelect.dispatchEvent(new Event('change'));
            }
            
            // Check password strength if there's a value
            const passwordInput = document.getElementById('password');
            if (passwordInput.value) {
                passwordInput.dispatchEvent(new Event('input'));
            }
        });
        
        // Form validation
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const role = document.getElementById('role').value;
            const status = document.getElementById('status').value;
            
            let errors = [];
            
            // Validate username
            if (username.length < 3) {
                errors.push('Username must be at least 3 characters long');
            }
            
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                errors.push('Username can only contain letters, numbers, and underscores');
            }
            
            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errors.push('Please enter a valid email address');
            }
            
            // Validate password
            if (password.length < 8) {
                errors.push('Password must be at least 8 characters long');
            }
            
            if (!/[A-Z]/.test(password)) {
                errors.push('Password must contain at least one uppercase letter');
            }
            
            if (!/[a-z]/.test(password)) {
                errors.push('Password must contain at least one lowercase letter');
            }
            
            if (!/[0-9]/.test(password)) {
                errors.push('Password must contain at least one number');
            }
            
            if (!/[\W_]/.test(password)) {
                errors.push('Password must contain at least one special character');
            }
            
            // Validate password match
            if (password !== confirmPassword) {
                errors.push('Passwords do not match');
            }
            
            // Validate role and status
            if (!role) {
                errors.push('Please select a user role');
            }
            
            if (!status) {
                errors.push('Please select an account status');
            }
            
            // If there are errors, prevent submission and show alert
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
            
            // Show loading animation
            const submitBtn = document.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating User...';
            submitBtn.disabled = true;
            
            // Re-enable button after 3 seconds (in case form doesn't submit)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
            
            return true;
        });
        
        // Close message alert
        document.querySelectorAll('.close-message').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.style.animation = 'slideDown 0.5s ease-out reverse';
                setTimeout(() => this.parentElement.remove(), 500);
            });
        });
        
        // Auto-hide success message after 5 seconds
        setTimeout(() => {
            const successAlert = document.querySelector('.message-alert.success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 5000);
        
        // Username validation on blur
        document.getElementById('username').addEventListener('blur', function() {
            const username = this.value.trim();
            if (username.length > 0 && username.length < 3) {
                this.style.borderColor = '#e74c3c';
            } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#2ecc71';
            }
        });
        
        // Email validation on blur
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.length > 0 && !emailRegex.test(email)) {
                this.style.borderColor = '#e74c3c';
            } else if (email.length > 0) {
                this.style.borderColor = '#2ecc71';
            }
        });
        
        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#2ecc71';
                } else {
                    this.style.borderColor = '#e74c3c';
                }
            }
        });
    </script>
</body>
</html>