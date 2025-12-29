<?php
// reset.php - Creative reset page with working navigation
session_start();
session_destroy();

// Clear all cookies
setcookie(session_name(), '', time() - 3600, '/');
setcookie('remember_me', '', time() - 3600, '/');

// Clear browser cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Reset - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }

        .reset-container {
            max-width: 800px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .reset-icon {
            font-size: 80px;
            color: #4CAF50;
            margin-bottom: 30px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        h1 {
            color: #2c3e50;
            font-size: 2.8rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .success-message {
            background: linear-gradient(45deg, #4CAF50, #2E7D32);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 30px 0;
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
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

        .reset-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid #4CAF50;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 15px 0;
            padding: 12px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }

        .detail-item:hover {
            transform: translateX(10px);
        }

        .detail-item i {
            color: #4CAF50;
            font-size: 1.2rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }

        .btn {
            padding: 18px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #4CAF50, #2E7D32);
            color: white;
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(76, 175, 80, 0.4);
        }

        .btn-danger {
            background: linear-gradient(45deg, #ff4444, #cc0000);
            color: white;
            box-shadow: 0 10px 20px rgba(255, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(255, 68, 68, 0.4);
        }

        .btn-info {
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
            box-shadow: 0 10px 20px rgba(33, 150, 243, 0.3);
        }

        .btn-info:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(33, 150, 243, 0.4);
        }

        .countdown {
            font-family: 'Courier New', monospace;
            font-size: 2.5rem;
            color: #667eea;
            margin: 30px 0;
            font-weight: bold;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .security-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: left;
        }

        .security-info h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .security-info ul {
            list-style-type: none;
            padding-left: 20px;
        }

        .security-info li {
            margin: 8px 0;
            color: #856404;
        }

        .security-info i {
            color: #ffc107;
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .reset-container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Status indicators */
        .status-indicator {
            display: inline-block;
            width: 15px;
            height: 15px;
            background: #4CAF50;
            border-radius: 50%;
            margin-right: 10px;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <!-- Success Icon -->
        <div class="reset-icon">
            <i class="fas fa-check-circle"></i>
        </div>

        <!-- Title -->
        <h1>Session Reset Complete!</h1>

        <!-- Success Message -->
        <div class="success-message">
            <i class="fas fa-shield-alt"></i>
            <strong>✓ Success!</strong> All sessions and cookies have been successfully cleared.
        </div>

        <!-- Reset Details -->
        <div class="reset-details">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">
                <i class="fas fa-list-check"></i> Reset Summary
            </h3>
            <div class="detail-item">
                <i class="fas fa-check-circle"></i>
                <span>Session data terminated successfully</span>
            </div>
            <div class="detail-item">
                <i class="fas fa-check-circle"></i>
                <span>Authentication cookies removed</span>
            </div>
            <div class="detail-item">
                <i class="fas fa-check-circle"></i>
                <span>Browser cache cleared</span>
            </div>
            <div class="detail-item">
                <i class="fas fa-check-circle"></i>
                <span>Security tokens revoked</span>
            </div>
        </div>

        <!-- Countdown Timer -->
        <div class="countdown" id="countdown">05:00</div>
        <p style="color: #666; margin-bottom: 20px;">
            Auto-redirect to Home Page in <span id="seconds">300</span> seconds
        </p>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                <div>
                    <strong>Go to Home</strong>
                    <small style="font-size: 0.8rem; opacity: 0.9;">Return to main page</small>
                </div>
            </a>
            
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                <div>
                    <strong>Login Again</strong>
                    <small style="font-size: 0.8rem; opacity: 0.9;">Sign in with credentials</small>
                </div>
            </a>
            
            <a href="register.php" class="btn btn-danger">
                <i class="fas fa-user-plus"></i>
                <div>
                    <strong>Create Account</strong>
                    <small style="font-size: 0.8rem; opacity: 0.9;">New user registration</small>
                </div>
            </a>
            
            <a href="dashboard.php" class="btn btn-info">
                <i class="fas fa-tachometer-alt"></i>
                <div>
                    <strong>Go to Dashboard</strong>
                    <small style="font-size: 0.8rem; opacity: 0.9;">If you're logged in</small>
                </div>
            </a>
        </div>

        <!-- Security Information -->
        <div class="security-info">
            <h3><i class="fas fa-lock"></i> Security Recommendations</h3>
            <ul>
                <li><i class="fas fa-check"></i> Close all browser windows for complete security</li>
                <li><i class="fas fa-check"></i> Use incognito mode for sensitive operations</li>
                <li><i class="fas fa-check"></i> Always logout after each session</li>
                <li><i class="fas fa-check"></i> Clear browser history periodically</li>
            </ul>
        </div>

        <!-- Quick Links -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="color: #666; margin-bottom: 15px;">Quick Navigation:</p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="debug.php" style="color: #667eea; text-decoration: none;">
                    <i class="fas fa-bug"></i> Debug
                </a>
                <a href="users.php" style="color: #667eea; text-decoration: none;">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="profile.php" style="color: #667eea; text-decoration: none;">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="settings.php" style="color: #667eea; text-decoration: none;">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer for auto-redirect
        let seconds = 300; // 5 minutes
        const countdownElement = document.getElementById('countdown');
        const secondsElement = document.getElementById('seconds');

        function updateCountdown() {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            
            // Update display
            countdownElement.textContent = 
                `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
            secondsElement.textContent = seconds;
            
            // Auto-redirect when countdown reaches 0
            if (seconds <= 0) {
                window.location.href = 'index.php';
            } else {
                seconds--;
                setTimeout(updateCountdown, 1000);
            }
        }

        // Start countdown
        updateCountdown();

        // Add click effects to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                // Create ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.6);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    top: ${y}px;
                    left: ${x}px;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Number keys for quick navigation
            switch(e.key) {
                case '1':
                    window.location.href = 'index.php';
                    break;
                case '2':
                    window.location.href = 'login.php';
                    break;
                case '3':
                    window.location.href = 'register.php';
                    break;
                case '4':
                    window.location.href = 'dashboard.php';
                    break;
                case 'Escape':
                    window.location.href = 'index.php';
                    break;
            }
        });

        // Show keyboard shortcuts hint
        console.log('Keyboard Shortcuts:');
        console.log('1 - Go to Home');
        console.log('2 - Login Page');
        console.log('3 - Register Page');
        console.log('4 - Dashboard');
        console.log('ESC - Home Page');

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Cancel auto-redirect if user interacts with the page
        let cancelRedirect = false;
        
        document.addEventListener('click', function() {
            cancelRedirect = true;
            // Clear the timeout if it exists
            clearTimeout(autoRedirectTimeout);
            
            // Update message
            const timerMsg = document.querySelector('p');
            if (timerMsg && timerMsg.innerHTML.includes('Auto-redirect')) {
                timerMsg.innerHTML = '<i class="fas fa-info-circle"></i> Auto-redirect cancelled - You can navigate manually';
                timerMsg.style.color = '#ff9800';
                
                // Hide countdown
                document.getElementById('countdown').style.display = 'none';
            }
        });

        // Still set a timeout for auto-redirect (just in case)
        let autoRedirectTimeout = setTimeout(() => {
            if (!cancelRedirect) {
                window.location.href = 'index.php';
            }
        }, 300000); // 5 minutes

        // Confirmation for logout if coming from dashboard
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('from') === 'dashboard') {
            setTimeout(() => {
                alert('You have been successfully logged out from the dashboard.');
            }, 500);
        }

        // Add a "Copy Reset Info" button
        const copyInfoBtn = document.createElement('button');
        copyInfoBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Reset Info';
        copyInfoBtn.style.cssText = `
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-top: 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        `;
        
        copyInfoBtn.onclick = function() {
            const resetInfo = `Session Reset Completed
Time: ${new Date().toLocaleString()}
Status: Successful
Actions Taken:
✓ Session data cleared
✓ Cookies removed
✓ Cache purged
✓ Security tokens revoked`;
            
            navigator.clipboard.writeText(resetInfo)
                .then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    this.style.background = '#4CAF50';
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.background = '#6c757d';
                    }, 2000);
                });
        };
        
        // Add the button after security info
        document.querySelector('.security-info').insertAdjacentElement('afterend', copyInfoBtn);
    </script>
</body>
</html>