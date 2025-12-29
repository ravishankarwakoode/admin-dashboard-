<?php
// index.php - Always show index page first
session_start();

// Force clear any dashboard redirects
if (isset($_GET['force'])) {
    $_SESSION = array();
    session_destroy();
}

// Don't auto-redirect, always show index first
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Admin Dashboard System</title>
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
        
        .main-container {
            width: 100%;
            max-width: 1200px;
            text-align: center;
        }
        
        .header {
            margin-bottom: 40px;
        }
        
        .logo {
            font-size: 60px;
            color: white;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        h1 {
            color: white;
            font-size: 3.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }
        
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            color: white;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        
        .card-icon {
            font-size: 40px;
            margin-bottom: 20px;
        }
        
        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .card p {
            opacity: 0.9;
            line-height: 1.5;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 16px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 200px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 65, 108, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 65, 108, 0.6);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
        }
        
        .btn-tertiary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .btn-tertiary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .session-info {
            margin-top: 30px;
            padding: 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }
        
        .session-info a {
            color: #ffcc00;
            text-decoration: none;
        }
        
        .session-info a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }
            
            .cards-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="main-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1>Admin Dashboard System</h1>
            <p class="subtitle">
                A complete solution for managing users, analyzing data, and controlling your application with powerful administrative tools.
            </p>
        </div>
        
        <div class="cards-container">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3>Secure Authentication</h3>
                <p>Protected login system with password encryption and session management for maximum security.</p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3>User Management</h3>
                <p>Easily manage users, assign roles, and control permissions with an intuitive interface.</p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Advanced Analytics</h3>
                <p>Get insights with real-time charts, graphs, and reports for data-driven decisions.</p>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login to Dashboard
            </a>
            <a href="reset.php" class="btn btn-tertiary">
                <i class="fas fa-redo"></i> Reset Session
            </a>
        </div>
        
        <div class="session-info">
            <?php
            if (isset($_SESSION['user_id'])) {
                echo "<p><i class='fas fa-info-circle'></i> You are currently logged in as: <strong>" . 
                     htmlspecialchars($_SESSION['username']) . "</strong> | ";
                echo "<a href='reset.php'>Click here to logout and start fresh</a></p>";
            } else {
                echo "<p><i class='fas fa-info-circle'></i> You are not logged in. Please register or login.</p>";
            }
            ?>
        </div>
    </div>
    
    <script>
        // Prevent back button issues
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
        
        // Add animation to buttons on load
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach((button, index) => {
                button.style.animationDelay = `${index * 0.1}s`;
                button.classList.add('animate__animated', 'animate__fadeInUp');
            });
            
            // Show welcome message
            if(!sessionStorage.getItem('welcomeShown')) {
                console.log('Welcome to Admin Dashboard System!');
                sessionStorage.setItem('welcomeShown', 'true');
            }
        });
    </script>
</body>
</html>