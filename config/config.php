<?php
// HAMS Configuration File
// Database settings for humanitarian aid management

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hams_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'HAMS - Humanitarian Aid Management System');
define('APP_VERSION', '1.0');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Security settings
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_NAME', 'hams_session');

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('UPLOAD_PATH', 'uploads/');

// Timezone
date_default_timezone_set('Africa/Mogadishu');

// Database connection function with connection pooling
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Session management
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

// Check if user is logged in
function requireLogin() {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Handle missing role in existing sessions
    if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
        // Get role from database
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $_SESSION['role'] = $user['role'];
            } else {
                // User not found, force logout
                session_destroy();
                header('Location: login.php');
                exit();
            }
        } catch (Exception $e) {
            // Database error, set default role
            $_SESSION['role'] = 'assistant';
        }
    }
}

// Check user role with new hierarchy
function requireRole($required_role) {
    requireLogin();
    $roles = ['assistant' => 1, 'officer' => 2, 'coordinator' => 3, 'admin' => 4];
    $user_level = $roles[$_SESSION['role']] ?? 0;
    $required_level = $roles[$required_role] ?? 4;
    
    if ($user_level < $required_level) {
        die("Access denied. Insufficient permissions.");
    }
}

// Check if user can access specific features
function canAccess($feature) {
    requireLogin();
    $role = $_SESSION['role'];
    
    $permissions = [
        'admin' => ['users', 'activity_records', 'dashboard_activity', 'reports', 'projects', 'supplies', 'deliveries', 'recipients'],
        'coordinator' => ['reports', 'projects', 'supplies', 'deliveries', 'recipients'],
        'officer' => ['supplies', 'deliveries', 'recipients'],
        'assistant' => ['recipients']
    ];
    
    return in_array($feature, $permissions[$role] ?? []);
}

// Log activity
function logActivity($action_type, $table_affected = null, $record_id = null, $description = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_records (user_id, action_type, table_affected, record_id, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action_type,
            $table_affected,
            $record_id,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format date for display
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

// Format number with commas
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals);
}
?>
