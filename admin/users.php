<?php
require_once '../config/config.php';
requireLogin();

// Only admin can access user management
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitizeInput($_POST['username']);
                $full_name = sanitizeInput($_POST['full_name']);
                $email = sanitizeInput($_POST['email']);
                $phone = sanitizeInput($_POST['phone']);
                $role = sanitizeInput($_POST['role']);
                $password = $_POST['password'];
                
                // Validate required fields
                if (empty($username) || empty($full_name) || empty($password) || empty($role)) {
                    $error = "Username, full name, password, and role are required.";
                } else {
                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->fetch()) {
                        $error = "Username already exists. Please choose a different username.";
                    } else {
                        // Hash password and insert user
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, password_hash, full_name, email, phone, role) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        if ($stmt->execute([$username, $password_hash, $full_name, $email, $phone, $role])) {
                            $new_user_id = $pdo->lastInsertId();
                            logActivity("create", "users", $new_user_id, "Created new user: $username ($role)");
                            $success = "User created successfully!";
                        } else {
                            $error = "Failed to create user. Please try again.";
                        }
                    }
                }
                break;
                
            case 'edit_user':
                $user_id = (int)$_POST['user_id'];
                $full_name = sanitizeInput($_POST['full_name']);
                $email = sanitizeInput($_POST['email']);
                $phone = sanitizeInput($_POST['phone']);
                $role = sanitizeInput($_POST['role']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, role = ?, is_active = ? 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$full_name, $email, $phone, $role, $is_active, $user_id])) {
                    logActivity("update", "users", $user_id, "Updated user ID: $user_id");
                    $success = "User updated successfully!";
                } else {
                    $error = "Failed to update user.";
                }
                break;
                
            case 'reset_password':
                $user_id = (int)$_POST['user_id'];
                $new_password = $_POST['new_password'];
                
                if (empty($new_password)) {
                    $error = "New password is required.";
                } else {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    
                    if ($stmt->execute([$password_hash, $user_id])) {
                        logActivity("password_reset", "users", $user_id, "Reset password for user ID: $user_id");
                        $success = "Password reset successfully!";
                    } else {
                        $error = "Failed to reset password.";
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                
                // Prevent deleting own account
                if ($user_id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    
                    if ($stmt->execute([$user_id])) {
                        logActivity("delete", "users", $user_id, "Deleted user ID: $user_id");
                        $success = "User deleted successfully!";
                    } else {
                        $error = "Failed to delete user.";
                    }
                }
                break;
        }
    }
}

// Get all users
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitizeInput($_GET['role']) : '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
$role_stats = [];
while ($row = $stmt->fetch()) {
    $role_stats[$row['role']] = $row['count'];
}

// Initialize variables to prevent undefined variable errors
$error = isset($error) ? $error : '';
$success = isset($success) ? $success : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <style>
        body{visibility:hidden}
        .sidebar{width:250px!important;background-color:#07bbc1!important;color:#FFFFFF!important;position:fixed!important;height:100vh!important;top:0!important;left:0!important;z-index:1000!important;transform:translate3d(0,0,0)!important;opacity:1!important;visibility:visible!important}
        .main-content{margin-left:250px!important;background-color:#FFFFFF!important;min-height:100vh!important}
        .container{display:flex!important}
        @media(max-width:768px){.sidebar{transform:translateX(-100%)!important}.sidebar.active{transform:translateX(0)!important}.main-content{margin-left:0!important}}
    </style>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>HAMS</h2>
                <p style="font-size: 12px; opacity: 0.8;">Somalia Relief</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../index.php"><span class="icon">🏠</span> Dashboard</a></li>
                <li><a href="../program/aid_recipients.php"><span class="icon">👥</span> Aid Recipients</a></li>
                <li><a href="../program/aid_delivery.php"><span class="icon">🚚</span> Aid Delivery</a></li>
                <li><a href="../program/supplies.php"><span class="icon">📦</span> Supplies</a></li>
                <li><a href="../program/projects.php"><span class="icon">📋</span> Projects</a></li>
                <li><a href="../program/reports.php"><span class="icon">📊</span> Reports</a></li>
                <li><a href="activity.php"><span class="icon">📝</span> Activity Records</a></li>
                <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <li><a href="users.php" class="active"><span class="icon">👤</span> User Management</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <button class="mobile-menu-btn">☰ Menu</button>
            
            <div class="header clearfix">
                <h1>User Management</h1>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
                    (<?php echo ucfirst($_SESSION['role']); ?>)
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum($role_stats); ?></div>
                    <div class="stat-label">Total Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_stats['admin'] ?? 0; ?></div>
                    <div class="stat-label">Administrators</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_stats['manager'] ?? 0; ?></div>
                    <div class="stat-label">Managers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo ($role_stats['coordinator'] ?? 0) + ($role_stats['field_worker'] ?? 0); ?></div>
                    <div class="stat-label">Staff Members</div>
                </div>
            </div>


            <!-- Search and Filter -->
            <div class="card">
                <div class="card-header">
                    <h3>User List</h3>
                    <a href="#" class="btn btn-primary" style="float: right;" onclick="openAddUserModal()">Add New User</a>
                </div>
                <div class="card-body">
                    <form method="GET" class="search-form">
                        <div class="search-group">
                            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                            <select name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="coordinator" <?php echo $role_filter === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                                <option value="officer" <?php echo $role_filter === 'officer' ? 'selected' : ''; ?>>Officer</option>
                                <option value="assistant" <?php echo $role_filter === 'assistant' ? 'selected' : ''; ?>>Assistant</option>
                            </select>
                            <button type="submit" class="btn btn-secondary">Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        if ($user['last_login'] && $user['last_login'] != '0000-00-00 00:00:00') {
                                            echo date('M j, Y g:i A', strtotime($user['last_login']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-secondary" onclick="editUser(<?php echo $user['id']; ?>)">Edit</button>
                                            <button class="btn btn-sm btn-warning" onclick="resetPassword(<?php echo $user['id']; ?>)">Reset Password</button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content modal-scrollable">
            <div class="modal-header">
                <h3>Edit User</h3>
                <span class="close" onclick="closeModal('editUserModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_username">Username *</label>
                            <input type="text" id="edit_username" name="username" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="edit_full_name">Full Name *</label>
                            <input type="text" id="edit_full_name" name="full_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Phone</label>
                            <input type="tel" id="edit_phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_role">Role *</label>
                            <select id="edit_role" name="role" required>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <option value="admin">Administrator</option>
                                <?php endif; ?>
                                <option value="coordinator">Coordinator</option>
                                <option value="officer">Officer</option>
                                <option value="assistant">Assistant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status *</label>
                            <select id="edit_status" name="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <span class="close" onclick="closeModal('resetPasswordModal')">&times;</span>
            </div>
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // User management functions
        function editUser(userId) {
            // Find user data from the table
            const row = event.target.closest('tr');
            const cells = row.cells;
            
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = cells[0].textContent;
            document.getElementById('edit_full_name').value = cells[1].textContent;
            document.getElementById('edit_email').value = cells[3].textContent === '-' ? '' : cells[3].textContent;
            document.getElementById('edit_phone').value = cells[4].textContent === '-' ? '' : cells[4].textContent;
            
            // Set role - handle new role names
            const roleText = cells[2].querySelector('.badge').textContent.toLowerCase();
            let roleValue = roleText;
            if (roleText === 'field worker') roleValue = 'assistant';
            if (roleText === 'manager') roleValue = 'coordinator';
            document.getElementById('edit_role').value = roleValue;
            
            // Set active status
            const isActive = cells[5].textContent === 'Active';
            document.getElementById('edit_status').value = isActive ? '1' : '0';
            
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        function resetPassword(userId) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <span class="close" onclick="closeAddUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="addUserForm">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="form-group">
                        <label for="modal_username">Username *</label>
                        <input type="text" id="modal_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_full_name">Full Name *</label>
                        <input type="text" id="modal_full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_email">Email</label>
                        <input type="email" id="modal_email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_phone">Phone</label>
                        <input type="tel" id="modal_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_role">Role *</label>
                        <select id="modal_role" name="role" required>
                            <option value="">Select Role</option>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <option value="admin">Administrator</option>
                            <?php endif; ?>
                            <option value="coordinator">Coordinator</option>
                            <option value="officer">Officer</option>
                            <option value="assistant">Assistant</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_password">Password *</label>
                        <input type="password" id="modal_password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group button-row">
                        <button type="submit" class="btn btn-primary">Create User</button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-body .form-group {
            margin-bottom: 20px;
        }
        
        .modal-body .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .modal-body .form-group input,
        .modal-body .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .modal-body .form-group input:focus,
        .modal-body .form-group select:focus {
            outline: none;
            border-color: #07bbc1;
            box-shadow: 0 0 0 2px rgba(7, 187, 193, 0.1);
        }
        
        .modal-body .button-row {
            margin-top: 30px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .modal-body .button-row .btn {
            flex: 1;
            padding: 10px 12px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 42px;
        }
        
        .modal-body .button-row .btn-primary {
            background-color: #f68e1f;
            color: white;
        }
        
        .modal-body .button-row .btn-primary:hover {
            background-color: #e67e0f;
            transform: translateY(-1px);
        }
        
        .modal-body .button-row .btn-secondary {
            background-color: #07bbc1;
            color: white;
        }
        
        .modal-body .button-row .btn-secondary:hover {
            background-color: #069aa0;
            transform: translateY(-1px);
        }
    </style>

    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target == modal) {
                closeAddUserModal();
            }
        }
    </script>
</body>
</html>
