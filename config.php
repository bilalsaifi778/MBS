<?php
// Include compatibility functions
require_once 'includes/compatibility.php';

// Database configuration
// Check if running locally vs production
if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    // Local development database
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'mbsbilalsaifi');
} else {
    // Production database (InfinityFree)
    define('DB_HOST', 'sql100.infinityfree.com'); // InfinityFree MySQL host
    define('DB_USER', 'if0_39811100'); // InfinityFree MySQL username
    define('DB_PASS', 'salma987183'); // InfinityFree MySQL password
    define('DB_NAME', 'if0_39811100_mbsbilalsaifi'); // InfinityFree MySQL database name
}

// Site configuration
// Dynamically determine SITE_URL so links work locally and in production
$__protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
$__host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $__protocol . '://' . $__host);
// BASE_URL accounts for subdirectory installs (e.g., domain.com/blog)
$__script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE_URL', rtrim(SITE_URL . ($__script_dir === '' || $__script_dir === '/' ? '' : $__script_dir), '/'));
define('SITE_NAME', 'MBS Bilal Saifi');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Security
define('SALT', 'mbs_bilal_saifi_2024_secure_salt_' . md5('infinityfree_hosting_secure'));

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Log error instead of displaying it
    error_log("Database connection failed: " . $conn->connect_error);
    
    // If running locally and database doesn't exist, show helpful message
    if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
        die("<div style='padding:20px;background:#f8d7da;color:#721c24;border-radius:5px;margin:20px;'>
            <h3>Development Database Not Found</h3>
            <p>Please ensure you have:</p>
            <ol>
                <li>MySQL/MariaDB running locally</li>
                <li>Created a database named 'mbsbilalsaifi'</li>
                <li>Imported the database.sql file</li>
            </ol>
            <p>Error: " . $conn->connect_error . "</p>
        </div>");
    } else {
        // Production - show generic error
        include 'includes/db-error.php';
        exit;
    }
}

// Set charset
$conn->set_charset("utf8mb4");

// Ensure posts table has views column
$check_views = $conn->query("SHOW COLUMNS FROM posts LIKE 'views'");
if (!$check_views || $check_views->num_rows == 0) {
    $conn->query("ALTER TABLE posts ADD COLUMN views INT DEFAULT 0");
}

// Session start
if (!session_id()) {
    session_start();
}

// Helper functions
function clean($string) {
    global $conn;
    $string = trim($string);
    $string = stripslashes($string);
    $string = htmlspecialchars($string);
    $string = $conn->real_escape_string($string);
    return $string;
}

function redirect($location) {
    header("Location: $location");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function checkLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

// CSRF Protection is handled in functions.php
?>