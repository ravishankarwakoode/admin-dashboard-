<?php
// includes/auth-check.php
session_start();

// Check if session is active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Debug: Check what's in session
/*
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
*/

// Only redirect if NOT on login or register pages
$current_page = basename($_SERVER['PHP_SELF']);
$allowed_pages = ['login.php', 'register.php', 'index.php'];

if (!isset($_SESSION['user_id']) && !in_array($current_page, $allowed_pages)) {
    header("Location: login.php");
    exit();
}

// If user is logged in and trying to access login/register, redirect to dashboard
if (isset($_SESSION['user_id']) && in_array($current_page, ['login.php', 'register.php'])) {
    header("Location: dashboard.php");
    exit();
}

// Role checking functions
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}
?>