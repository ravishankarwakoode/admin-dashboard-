<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Default XAMPP username
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_NAME', 'ravi1');

// Create connection with error reporting
function getDBConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if (!$conn) {
            throw new Exception("Connection failed: " . mysqli_connect_error());
        }
        
        // Set charset to UTF-8
        mysqli_set_charset($conn, "utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        // More descriptive error message
        die("Database Connection Error: " . $e->getMessage() . 
            "<br>Please check: 
            <br>1. Is MySQL running in XAMPP?
            <br>2. Database name: " . DB_NAME . "
            <br>3. Host: " . DB_HOST . "
            <br>4. Username: " . DB_USER);
    }
}

// Test connection function (optional)
function testDBConnection() {
    try {
        $conn = getDBConnection();
        echo "Database connection successful!";
        mysqli_close($conn);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>