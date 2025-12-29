<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $conn = getDBConnection();
        
        // Check if username/email exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or Email already exists!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $insert_sql = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                // Store success message in session
                $_SESSION['registration_success'] = "Registration successful! Please login with your credentials.";
                
                // Redirect to login page
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed! Please try again.";
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        .left-section {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .left-section::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            top: -100px;
            right: -100px;
        }

        .left-section::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            bottom: -80px;
            left: -80px;
        }

        .welcome-text {
            position: relative;
            z-index: 1;
        }

        .welcome-text h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .features {
            margin-top: 30px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .feature-item i {
            background: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .right-section {
            flex: 1.2;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h2 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .register-header p {
            color: #666;
            font-size: 1rem;
        }

        .logo {
            font-size: 50px;
            color: #667eea;
            margin-bottom: 20px;
            display: inline-block;
            background: rgba(102, 126, 234, 0.1);
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 18px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #667eea;
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .register-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.3);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .error {
            background: #ffeaea;
            color: #d32f2f;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #d32f2f;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .links-section {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
        }

        .links-section a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .links-section a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .powered-by {
            text-align: center;
            margin-top: 30px;
            font-size: 0.8rem;
            color: #999;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }

        .strength-meter {
            height: 5px;
            background: #e0e0e0;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .requirement i {
            font-size: 0.7rem;
        }

        .requirement.valid {
            color: #2e7d32;
        }

        .requirement.invalid {
            color: #d32f2f;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .register-container {
            animation: fadeIn 0.5s ease;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .register-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .left-section {
                padding: 40px 30px;
            }
            
            .right-section {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            .left-section, .right-section {
                padding: 30px 20px;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
            }
            
            .register-header h2 {
                font-size: 1.8rem;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left Section - Welcome & Info -->
        <div class="left-section">
            <div class="welcome-text">
                <h1>Join Our Platform</h1>
                <p>Create your Admin Dashboard account to access powerful tools for managing users, analyzing data, and controlling your application.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-user-plus"></i>
                        <span>Quick and Easy Registration</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Bank-Level Security</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-rocket"></i>
                        <span>Instant Dashboard Access</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-headset"></i>
                        <span>24/7 Support Available</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Section - Registration Form -->
        <div class="right-section">
            <div class="register-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Account</h2>
                <p>Fill in your details to get started</p>
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               placeholder="Enter your full name"
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="username"><i class="fas fa-at"></i> Username</label>
                    <div class="input-with-icon">
                        <i class="fas fa-at"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required 
                               placeholder="Choose a username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required 
                               placeholder="Enter your email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Create a password"
                               onkeyup="checkPasswordStrength()">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <span id="strengthText">Password strength</span>
                    </div>
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="requirement invalid" id="reqLength">
                            <i class="fas fa-circle"></i>
                            <span>At least 6 characters</span>
                        </div>
                        <div class="requirement invalid" id="reqUpperCase">
                            <i class="fas fa-circle"></i>
                            <span>One uppercase letter</span>
                        </div>
                        <div class="requirement invalid" id="reqLowerCase">
                            <i class="fas fa-circle"></i>
                            <span>One lowercase letter</span>
                        </div>
                        <div class="requirement invalid" id="reqNumber">
                            <i class="fas fa-circle"></i>
                            <span>One number</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required 
                               placeholder="Confirm your password"
                               onkeyup="checkPasswordMatch()">
                        <button type="button" class="password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchMessage" class="password-requirements"></div>
                </div>

                <button type="submit" class="register-btn" id="registerButton">
                    <span id="buttonText">Create Account</span>
                    <i class="fas fa-user-plus"></i>
                </button>
            </form>

            <div class="links-section">
                Already have an account? <a href="login.php">Sign in here</a>
                <br>
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>

            <div class="powered-by">
                <p>By registering, you agree to our Terms of Service & Privacy Policy</p>
                <p>© 2024 Admin Dashboard Panel. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
        
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Password strength checker
        function checkPasswordStrength() {
            const pass = password.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // Check requirements
            const hasLength = pass.length >= 6;
            const hasUpperCase = /[A-Z]/.test(pass);
            const hasLowerCase = /[a-z]/.test(pass);
            const hasNumber = /[0-9]/.test(pass);
            
            // Update requirement indicators
            updateRequirement('reqLength', hasLength);
            updateRequirement('reqUpperCase', hasUpperCase);
            updateRequirement('reqLowerCase', hasLowerCase);
            updateRequirement('reqNumber', hasNumber);
            
            // Calculate strength score
            let score = 0;
            if (hasLength) score += 25;
            if (hasUpperCase) score += 25;
            if (hasLowerCase) score += 25;
            if (hasNumber) score += 25;
            
            // Update strength meter
            strengthFill.style.width = score + '%';
            
            // Update strength text and color
            if (score === 0) {
                strengthFill.style.backgroundColor = '#e0e0e0';
                strengthText.textContent = 'Enter a password';
                strengthText.style.color = '#666';
            } else if (score <= 25) {
                strengthFill.style.backgroundColor = '#ff4444';
                strengthText.textContent = 'Very Weak';
                strengthText.style.color = '#ff4444';
            } else if (score <= 50) {
                strengthFill.style.backgroundColor = '#ff8800';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#ff8800';
            } else if (score <= 75) {
                strengthFill.style.backgroundColor = '#ffbb33';
                strengthText.textContent = 'Good';
                strengthText.style.color = '#ffbb33';
            } else {
                strengthFill.style.backgroundColor = '#00C851';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#00C851';
            }
            
            // Check password match if confirm password has value
            if (confirmPassword.value.length > 0) {
                checkPasswordMatch();
            }
        }
        
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                element.querySelector('i').className = 'fas fa-check-circle';
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                element.querySelector('i').className = 'fas fa-circle';
            }
        }
        
        function checkPasswordMatch() {
            const pass = password.value;
            const confirmPass = confirmPassword.value;
            const messageElement = document.getElementById('passwordMatchMessage');
            
            if (confirmPass.length === 0) {
                messageElement.innerHTML = '';
                return;
            }
            
            if (pass === confirmPass) {
                messageElement.innerHTML = '<div class="requirement valid"><i class="fas fa-check-circle"></i><span>Passwords match!</span></div>';
            } else {
                messageElement.innerHTML = '<div class="requirement invalid"><i class="fas fa-times-circle"></i><span>Passwords do not match</span></div>';
            }
        }
        
        // Form submission
        const registerForm = document.getElementById('registerForm');
        const registerButton = document.getElementById('registerButton');
        const buttonText = document.getElementById('buttonText');
        
        registerForm.addEventListener('submit', function(e) {
            // Validate password match before submitting
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                document.getElementById('passwordMatchMessage').innerHTML = 
                    '<div class="requirement invalid"><i class="fas fa-exclamation-triangle"></i><span>Passwords must match!</span></div>';
                return;
            }
            
            if (password.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }
            
            // Show loading state
            registerButton.disabled = true;
            buttonText.innerHTML = 'Creating Account...';
            registerButton.innerHTML = '<div class="loading"></div>';
        });
        
        // Auto-focus first input
        document.getElementById('full_name').focus();
        
        // Auto-hide error messages after 5 seconds
        setTimeout(function() {
            const errorDiv = document.querySelector('.error');
            if (errorDiv) {
                errorDiv.style.opacity = '0';
                errorDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => errorDiv.style.display = 'none', 500);
            }
        }, 5000);
        
        // Real-time validation
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length > 0) {
                    this.style.borderColor = '#667eea';
                } else {
                    this.style.borderColor = '#e0e0e0';
                }
            });
        });
        
        // Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'TEXTAREA') {
                if (!registerForm.contains(document.activeElement)) return;
                registerForm.dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>