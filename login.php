<?php
require_once 'config/config.php';

// Start session
startSession();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle logout message
if (isset($_GET['logout'])) {
    $success_message = 'You have been logged out successfully.';
}

// Handle session timeout
if (isset($_GET['timeout'])) {
    $error_message = 'Your session has expired. Please log in again.';
}

// Handle login form submission
if ($_POST) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username/email and password.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Log login activity
                logActivity('login', 'users', $user['id'], 'User logged in');
                
                header('Location: index.php');
                exit();
            } else {
                $error_message = 'Invalid username or password.';
                // Log failed login attempt
                if ($user) {
                    logActivity('failed_login', 'users', $user['id'], 'Failed login attempt');
                }
            }
        } catch (Exception $e) {
            $error_message = 'Login system temporarily unavailable. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <style>
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f5f5f5;margin:0;padding:0}
        .login-container{display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .login-card{background:#fff;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);padding:40px;width:100%;max-width:400px}
        .login-header{text-align:center;margin-bottom:30px}
        .login-header h1{color:#07bbc1;font-size:28px;margin-bottom:8px}
        .login-header p{color:#666;margin:4px 0}
        .form-group{margin-bottom:20px}
        .form-label{display:block;margin-bottom:8px;font-weight:500;color:#333}
        .form-control{width:100%;padding:12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box}
        .form-control:focus{outline:none;border-color:#07bbc1;box-shadow:0 0 0 2px rgba(7,187,193,0.2)}
        .btn{padding:12px 24px;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:500;text-decoration:none;display:inline-block;text-align:center;transition:background-color 0.2s}
        .btn-primary{background-color:#f68e1f;color:#fff}
        .btn-primary:hover{background-color:#e67e0f}
        .btn-primary:disabled{background-color:#ccc;cursor:not-allowed}
        .alert{padding:12px;border-radius:4px;margin-bottom:20px}
        .alert-error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .alert-success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}
    </style>
    <link rel="stylesheet" href="style.css" media="print" onload="this.media='all'">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>HAMS</h1>
                <p>Humanitarian Aid Management System</p>
                <p><small>Somalia Relief Operations</small></p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter username or email"
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" required style="padding-right: 45px;">
                        <button type="button" id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; color: #666;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </div>
            </form>
            
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
    <script>
        // Show loading indicator on form submit
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite; margin-right: 8px;"></span>Logging in...';
            btn.disabled = true;
        });
        
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
            
            if (type === 'password') {
                this.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            } else {
                this.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            }
        });
    </script>
</body>
</html>
