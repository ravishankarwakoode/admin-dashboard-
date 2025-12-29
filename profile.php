<?php
// profile.php - User profile editing page
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
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
            --shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background Elements */
        .bg-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite linear;
        }
        
        .circle:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -150px;
            left: -150px;
            animation-delay: 0s;
        }
        
        .circle:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -100px;
            right: 20%;
            animation-delay: 5s;
            animation-duration: 25s;
        }
        
        .circle:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 30%;
            right: -75px;
            animation-delay: 10s;
            animation-duration: 30s;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-1000px) rotate(720deg); }
        }
        
        .profile-container {
            max-width: 900px;
            width: 100%;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 50px;
            border-radius: 25px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
            animation: slideUp 0.8s ease-out;
        }
        
        .profile-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: var(--primary-gradient);
            animation: progressBar 2s ease-in-out;
        }
        
        .profile-container::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .avatar-container {
            position: relative;
            width: 100px;
            height: 100px;
        }
        
        .avatar {
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .avatar::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 2.8rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            flex: 1;
        }
        
        .user-role {
            display: inline-block;
            padding: 8px 20px;
            background: var(--secondary-gradient);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .form-section {
            background: rgba(248, 250, 252, 0.5);
            padding: 30px;
            border-radius: 20px;
            border: 1px solid rgba(226, 232, 240, 0.5);
            transition: var(--transition);
        }
        
        .form-section:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.1);
        }
        
        h3 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        h3 i {
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.4rem;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        
        label i {
            color: #667eea;
            width: 20px;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 18px 20px 18px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            font-size: 16px;
            transition: var(--transition);
            background: #f8fafc;
            color: #2d3748;
        }
        
        .input-with-icon .icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            transition: var(--transition);
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
            transform: translateY(-2px);
        }
        
        input[type="text"]:focus + .icon,
        input[type="email"]:focus + .icon,
        input[type="password"]:focus + .icon {
            color: #667eea;
            transform: translateY(-50%) scale(1.1);
        }
        
        .form-actions {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid rgba(226, 232, 240, 0.5);
        }
        
        .btn {
            flex: 1;
            padding: 20px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: rgba(226, 232, 240, 0.5);
            color: #4a5568;
            border: 2px solid #e2e8f0;
            text-decoration: none;
            text-align: center;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary:hover {
            background: white;
            border-color: #667eea;
            color: #667eea;
        }
        
        .success {
            background: var(--success-gradient);
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            animation: slideIn 0.5s ease-out;
            box-shadow: 0 10px 25px rgba(67, 233, 123, 0.3);
        }
        
        .error {
            background: var(--warning-gradient);
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            animation: slideIn 0.5s ease-out;
            box-shadow: 0 10px 25px rgba(250, 112, 154, 0.3);
        }
        
        .password-strength {
            margin-top: 10px;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: var(--transition);
            background: var(--primary-gradient);
        }
        
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
        
        @keyframes progressBar {
            from { width: 0; }
            to { width: 100%; }
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-container {
                padding: 30px 20px;
                margin: 15px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Elements -->
    <div class="bg-elements">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <div class="profile-container">
        <div class="profile-header">
            <div class="avatar-container">
                <div class="avatar">
                    <?php 
                    // Get first letter of username for avatar
                    if (isset($user['username']) && !empty($user['username'])) {
                        echo strtoupper(substr($user['username'], 0, 1));
                    } else {
                        echo 'U';
                    }
                    ?>
                </div>
            </div>
            <div>
                <h1>Edit Profile</h1>
                <div class="user-role">
                    <i class="fas fa-user-tag"></i> 
                    <?php 
                    echo isset($user['role']) && !empty($user['role']) 
                        ? htmlspecialchars($user['role']) 
                        : 'Member';
                    ?>
                </div>
            </div>
        </div>
        
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
        
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <div class="input-with-icon">
                            <input type="text" value="<?php echo isset($user['username']) ? htmlspecialchars($user['username']) : ''; ?>" disabled>
                            <i class="fas fa-lock icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Full Name</label>
                        <div class="input-with-icon">
                            <input type="text" name="full_name" value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : ''; ?>" placeholder="Enter your full name">
                            <i class="fas fa-signature icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <div class="input-with-icon">
                            <input type="email" name="email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required placeholder="Enter your email">
                            <i class="fas fa-at icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-key"></i> Security Settings</h3>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Current Password</label>
                        <div class="input-with-icon">
                            <input type="password" name="current_password" placeholder="Enter current password">
                            <i class="fas fa-key icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> New Password</label>
                        <div class="input-with-icon">
                            <input type="password" name="new_password" id="new_password" placeholder="Enter new password">
                            <i class="fas fa-unlock-alt icon"></i>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Confirm Password</label>
                        <div class="input-with-icon">
                            <input type="password" name="confirm_password" placeholder="Confirm new password">
                            <i class="fas fa-check-circle icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
    
    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthMeter = document.getElementById('strengthMeter');
            let strength = 0;
            
            // Check password strength
            if (password.length >= 6) strength += 20;
            if (password.length >= 8) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            
            strengthMeter.style.width = strength + '%';
            
            // Change color based on strength
            if (strength < 40) {
                strengthMeter.style.background = 'linear-gradient(135deg, #ff0000 0%, #ff5e5e 100%)';
            } else if (strength < 80) {
                strengthMeter.style.background = 'linear-gradient(135deg, #ffa500 0%, #ffd700 100%)';
            } else {
                strengthMeter.style.background = 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)';
            }
        });
        
        // Form animations
        document.addEventListener('DOMContentLoaded', function() {
            const formSections = document.querySelectorAll('.form-section');
            formSections.forEach((section, index) => {
                section.style.animationDelay = `${index * 0.1}s`;
                section.style.animation = 'slideIn 0.5s ease-out forwards';
                section.style.opacity = '0';
            });
            
            // Input focus effects
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.parentElement.style.transform = 'translateY(-5px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.parentElement.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Prevent form submission if new passwords don't match
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>