<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$recipient_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $data = [
        'recipient_id' => sanitizeInput($_POST['recipient_id']),
        'full_name' => sanitizeInput($_POST['full_name']),
        'phone' => sanitizeInput($_POST['phone']),
        'location' => sanitizeInput($_POST['location']),
        'district' => sanitizeInput($_POST['district']),
        'household_size' => (int)($_POST['household_size'] ?? 1),
        'displacement_status' => $_POST['displacement_status'],
        'vulnerability_level' => $_POST['vulnerability_level'],
        'registration_date' => $_POST['registration_date'],
        'notes' => sanitizeInput($_POST['notes'])
    ];
    
    try {
        if ($action === 'add') {
            // Check if recipient ID already exists
            $stmt = $pdo->prepare("SELECT id FROM aid_recipients WHERE recipient_id = ?");
            $stmt->execute([$data['recipient_id']]);
            if ($stmt->fetch()) {
                $error = 'Recipient ID already exists. Please use a different ID.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO aid_recipients 
                    (recipient_id, full_name, phone, location, district, household_size, 
                     displacement_status, vulnerability_level, registration_date, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['recipient_id'], $data['full_name'], $data['phone'], 
                    $data['location'], $data['district'], $data['household_size'],
                    $data['displacement_status'], $data['vulnerability_level'], 
                    $data['registration_date'], $data['notes'], $_SESSION['user_id']
                ]);
                
                logActivity($_SESSION['user_id'], 'create', 'aid_recipients', $pdo->lastInsertId(), 
                           'Added new aid recipient: ' . $data['full_name']);
                
                $message = 'Aid recipient registered successfully.';
                $action = 'list';
            }
        } elseif ($action === 'edit' && $recipient_id) {
            $stmt = $pdo->prepare("
                UPDATE aid_recipients SET 
                recipient_id = ?, full_name = ?, phone = ?, location = ?, district = ?, 
                household_size = ?, displacement_status = ?, vulnerability_level = ?, 
                registration_date = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['recipient_id'], $data['full_name'], $data['phone'], 
                $data['location'], $data['district'], $data['household_size'],
                $data['displacement_status'], $data['vulnerability_level'], 
                $data['registration_date'], $data['notes'], $recipient_id
            ]);
            
            logActivity($_SESSION['user_id'], 'update', 'aid_recipients', $recipient_id, 
                       'Updated aid recipient: ' . $data['full_name']);
            
            $message = 'Aid recipient updated successfully.';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error saving recipient: ' . $e->getMessage();
    }
}

// Handle delete action
if ($action === 'delete' && $recipient_id) {
    try {
        // Check if recipient has deliveries
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM aid_deliveries WHERE recipient_id = ?");
        $stmt->execute([$recipient_id]);
        $delivery_count = $stmt->fetch()['count'];
        
        if ($delivery_count > 0) {
            $error = 'Cannot delete recipient with existing aid deliveries. Archive instead.';
        } else {
            $stmt = $pdo->prepare("SELECT full_name FROM aid_recipients WHERE id = ?");
            $stmt->execute([$recipient_id]);
            $recipient_name = $stmt->fetch()['full_name'];
            
            $stmt = $pdo->prepare("DELETE FROM aid_recipients WHERE id = ?");
            $stmt->execute([$recipient_id]);
            
            logActivity($_SESSION['user_id'], 'delete', 'aid_recipients', $recipient_id, 
                       'Deleted aid recipient: ' . $recipient_name);
            
            $message = 'Aid recipient deleted successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting recipient: ' . $e->getMessage();
    }
    $action = 'list';
}

// Get recipient data for edit
$recipient_data = null;
if ($action === 'edit' && $recipient_id) {
    $stmt = $pdo->prepare("SELECT * FROM aid_recipients WHERE id = ?");
    $stmt->execute([$recipient_id]);
    $recipient_data = $stmt->fetch();
    if (!$recipient_data) {
        $error = 'Recipient not found.';
        $action = 'list';
    }
}

// Get recipients list with search
$search = $_GET['search'] ?? '';
$displacement_filter = $_GET['displacement'] ?? '';
$vulnerability_filter = $_GET['vulnerability'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(full_name LIKE ? OR recipient_id LIKE ? OR location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($displacement_filter) {
    $where_conditions[] = "displacement_status = ?";
    $params[] = $displacement_filter;
}

if ($vulnerability_filter) {
    $where_conditions[] = "vulnerability_level = ?";
    $params[] = $vulnerability_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT ar.*, u.full_name as created_by_name 
    FROM aid_recipients ar 
    LEFT JOIN users u ON ar.created_by = u.id 
    $where_clause 
    ORDER BY ar.created_at DESC
");
$stmt->execute($params);
$recipients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aid Recipients - <?php echo APP_NAME; ?></title>
    <style>
        body{visibility:hidden;font-family:'Segoe UI',sans-serif;margin:0;padding:0;background:#fff;overflow-x:hidden}
        .sidebar{width:250px!important;background-color:#07bbc1!important;color:#fff!important;position:fixed!important;height:100vh!important;top:0!important;left:0!important;z-index:1000!important;transform:translate3d(0,0,0)!important;opacity:1!important;visibility:visible!important}
        .main-content{margin-left:250px!important;background-color:#fff!important;min-height:100vh!important}
        .container{display:flex!important}
        @media(max-width:768px){.sidebar{transform:translateX(-100%)!important}.sidebar.active{transform:translateX(0)!important}.main-content{margin-left:0!important}}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
    <link rel="stylesheet" href="../assets/css/style.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="style.css"></noscript>
</head>
<body>
    <div class="container">
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <button class="mobile-menu-btn">☰ Menu</button>
            
            <div class="header clearfix">
                <h1>Aid Recipients</h1>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
                    (<?php echo ucfirst($_SESSION['role']); ?>)
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Recipients List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">People Supported (<?php echo count($recipients); ?>)</h3>
                        <a href="?action=add" class="btn btn-primary" style="float: right;">Register New Recipient</a>
                    </div>
                    
                    <!-- Search and Filters -->
                    <form method="GET" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 10px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Name, ID, or location..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Displacement</label>
                                <select name="displacement" class="form-control">
                                    <option value="">All</option>
                                    <option value="resident" <?php echo $displacement_filter === 'resident' ? 'selected' : ''; ?>>Resident</option>
                                    <option value="idp" <?php echo $displacement_filter === 'idp' ? 'selected' : ''; ?>>IDP</option>
                                    <option value="refugee" <?php echo $displacement_filter === 'refugee' ? 'selected' : ''; ?>>Refugee</option>
                                    <option value="returnee" <?php echo $displacement_filter === 'returnee' ? 'selected' : ''; ?>>Returnee</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Vulnerability</label>
                                <select name="vulnerability" class="form-control">
                                    <option value="">All</option>
                                    <option value="low" <?php echo $vulnerability_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $vulnerability_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $vulnerability_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo $vulnerability_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary">Filter</button>
                        </div>
                    </form>
                    
                    <?php if (empty($recipients)): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">
                            No aid recipients found. <a href="?action=add">Register the first recipient</a>
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Location</th>
                                        <th>Household</th>
                                        <th>Status</th>
                                        <th>Vulnerability</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($recipient['recipient_id']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($recipient['full_name']); ?>
                                                <?php if ($recipient['phone']): ?>
                                                    <br><small style="color: #666;"><?php echo htmlspecialchars($recipient['phone']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($recipient['location']); ?>
                                                <?php if ($recipient['district']): ?>
                                                    <br><small style="color: #666;"><?php echo htmlspecialchars($recipient['district']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $recipient['household_size']; ?> people</td>
                                            <td>
                                                <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; 
                                                      background-color: <?php 
                                                          echo $recipient['displacement_status'] === 'idp' ? '#fff3cd' : 
                                                               ($recipient['displacement_status'] === 'refugee' ? '#f8d7da' : '#d4edda'); 
                                                      ?>;">
                                                    <?php echo ucfirst($recipient['displacement_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; 
                                                      background-color: <?php 
                                                          echo $recipient['vulnerability_level'] === 'critical' ? '#f8d7da' : 
                                                               ($recipient['vulnerability_level'] === 'high' ? '#fff3cd' : '#d4edda'); 
                                                      ?>;">
                                                    <?php echo ucfirst($recipient['vulnerability_level']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($recipient['registration_date']); ?></td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $recipient['id']; ?>" 
                                                   class="btn btn-small btn-secondary">Edit</a>
                                                <a href="?action=delete&id=<?php echo $recipient['id']; ?>" 
                                                   class="btn btn-small btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this recipient?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $action === 'add' ? 'Register New Aid Recipient' : 'Edit Aid Recipient'; ?>
                        </h3>
                    </div>
                    
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Recipient ID *</label>
                                    <input type="text" name="recipient_id" class="form-control" required
                                           value="<?php echo htmlspecialchars($recipient_data['recipient_id'] ?? ''); ?>"
                                           placeholder="e.g., SOM2024001">
                                    <small style="color: #666;">Unique identifier for this person</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($recipient_data['full_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control"
                                           value="<?php echo htmlspecialchars($recipient_data['phone'] ?? ''); ?>"
                                           placeholder="+252...">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Location *</label>
                                    <input type="text" name="location" class="form-control" required
                                           value="<?php echo htmlspecialchars($recipient_data['location'] ?? ''); ?>"
                                           placeholder="Village/Camp/Neighborhood">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">District</label>
                                    <input type="text" name="district" class="form-control"
                                           value="<?php echo htmlspecialchars($recipient_data['district'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Household Size *</label>
                                    <input type="number" name="household_size" class="form-control" required min="1"
                                           value="<?php echo $recipient_data['household_size'] ?? 1; ?>">
                                    <small style="color: #666;">Number of people in household</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Displacement Status *</label>
                                    <select name="displacement_status" class="form-control" required>
                                        <option value="resident" <?php echo ($recipient_data['displacement_status'] ?? '') === 'resident' ? 'selected' : ''; ?>>Resident</option>
                                        <option value="idp" <?php echo ($recipient_data['displacement_status'] ?? '') === 'idp' ? 'selected' : ''; ?>>IDP (Internally Displaced)</option>
                                        <option value="refugee" <?php echo ($recipient_data['displacement_status'] ?? '') === 'refugee' ? 'selected' : ''; ?>>Refugee</option>
                                        <option value="returnee" <?php echo ($recipient_data['displacement_status'] ?? '') === 'returnee' ? 'selected' : ''; ?>>Returnee</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Vulnerability Level *</label>
                                    <select name="vulnerability_level" class="form-control" required>
                                        <option value="low" <?php echo ($recipient_data['vulnerability_level'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($recipient_data['vulnerability_level'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($recipient_data['vulnerability_level'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="critical" <?php echo ($recipient_data['vulnerability_level'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Registration Date *</label>
                                    <input type="date" name="registration_date" class="form-control" required
                                           value="<?php echo $recipient_data['registration_date'] ?? date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"
                                              placeholder="Additional information..."><?php echo htmlspecialchars($recipient_data['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Register Recipient' : 'Update Recipient'; ?>
                            </button>
                            <a href="aid_recipients.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/performance.js" async></script>
    <script src="../assets/js/script.js" defer></script>
    <script>document.body.style.visibility='visible';</script>
</body>
</html>
