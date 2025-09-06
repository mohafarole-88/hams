<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$project_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $data = [
        'project_name' => sanitizeInput($_POST['project_name']),
        'project_code' => sanitizeInput($_POST['project_code']),
        'donor_name' => sanitizeInput($_POST['donor_name']),
        'target_location' => sanitizeInput($_POST['target_location']),
        'target_beneficiaries' => (int)($_POST['target_beneficiaries'] ?? 0),
        'start_date' => $_POST['start_date'] ?: null,
        'end_date' => $_POST['end_date'] ?: null,
        'budget' => (float)($_POST['budget'] ?? 0),
        'status' => $_POST['status'],
        'description' => sanitizeInput($_POST['description'])
    ];
    
    try {
        if ($action === 'add') {
            // Check if project code already exists
            if ($data['project_code']) {
                $stmt = $pdo->prepare("SELECT id FROM projects WHERE project_code = ?");
                $stmt->execute([$data['project_code']]);
                if ($stmt->fetch()) {
                    $error = 'Project code already exists. Please use a different code.';
                }
            }
            
            if (!$error) {
                $stmt = $pdo->prepare("
                    INSERT INTO projects 
                    (project_name, project_code, donor_name, target_location, target_beneficiaries, 
                     start_date, end_date, budget, status, description, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['project_name'], $data['project_code'], $data['donor_name'], 
                    $data['target_location'], $data['target_beneficiaries'], $data['start_date'],
                    $data['end_date'], $data['budget'], $data['status'], $data['description'], 
                    $_SESSION['user_id']
                ]);
                
                logActivity($_SESSION['user_id'], 'create', 'projects', $pdo->lastInsertId(), 
                           'Created new project: ' . $data['project_name']);
                
                $message = 'Project created successfully.';
                $action = 'list';
            }
        } elseif ($action === 'edit' && $project_id) {
            $stmt = $pdo->prepare("
                UPDATE projects SET 
                project_name = ?, project_code = ?, donor_name = ?, target_location = ?, 
                target_beneficiaries = ?, start_date = ?, end_date = ?, budget = ?, 
                status = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['project_name'], $data['project_code'], $data['donor_name'], 
                $data['target_location'], $data['target_beneficiaries'], $data['start_date'],
                $data['end_date'], $data['budget'], $data['status'], $data['description'], 
                $project_id
            ]);
            
            logActivity($_SESSION['user_id'], 'update', 'projects', $project_id, 
                       'Updated project: ' . $data['project_name']);
            
            $message = 'Project updated successfully.';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error saving project: ' . $e->getMessage();
    }
}

// Handle delete action
if ($action === 'delete' && $project_id) {
    try {
        // Check if project has deliveries
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM aid_deliveries WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $delivery_count = $stmt->fetch()['count'];
        
        if ($delivery_count > 0) {
            $error = 'Cannot delete project with existing deliveries. Change status to completed instead.';
        } else {
            $stmt = $pdo->prepare("SELECT project_name FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $project_name = $stmt->fetch()['project_name'];
            
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            
            logActivity($_SESSION['user_id'], 'delete', 'projects', $project_id, 
                       'Deleted project: ' . $project_name);
            
            $message = 'Project deleted successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting project: ' . $e->getMessage();
    }
    $action = 'list';
}

// Get project data for edit
$project_data = null;
if ($action === 'edit' && $project_id) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project_data = $stmt->fetch();
    if (!$project_data) {
        $error = 'Project not found.';
        $action = 'list';
    }
}

// Get project details for view
if ($action === 'view' && $project_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as created_by_name 
        FROM projects p 
        LEFT JOIN users u ON p.created_by = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $project_details = $stmt->fetch();
    
    if ($project_details) {
        // Get project deliveries
        $stmt = $pdo->prepare("
            SELECT ad.*, ar.full_name as recipient_name, s.item_name, s.unit_type 
            FROM aid_deliveries ad 
            LEFT JOIN aid_recipients ar ON ad.recipient_id = ar.id 
            LEFT JOIN supplies s ON ad.supply_id = s.id 
            WHERE ad.project_id = ? 
            ORDER BY ad.delivery_date DESC
        ");
        $stmt->execute([$project_id]);
        $project_deliveries = $stmt->fetchAll();
        
        // Get project statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT ad.recipient_id) as unique_beneficiaries,
                COUNT(ad.id) as total_deliveries,
                SUM(ad.quantity_delivered * s.cost_per_unit) as total_value
            FROM aid_deliveries ad 
            LEFT JOIN supplies s ON ad.supply_id = s.id 
            WHERE ad.project_id = ?
        ");
        $stmt->execute([$project_id]);
        $project_stats = $stmt->fetch();
    } else {
        $error = 'Project not found.';
        $action = 'list';
    }
}

// Get projects list with search and filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$donor_filter = $_GET['donor'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(project_name LIKE ? OR project_code LIKE ? OR target_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($donor_filter) {
    $where_conditions[] = "donor_name LIKE ?";
    $params[] = "%$donor_filter%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as created_by_name,
           (SELECT COUNT(DISTINCT ad.recipient_id) FROM aid_deliveries ad WHERE ad.project_id = p.id) as beneficiaries_reached
    FROM projects p 
    LEFT JOIN users u ON p.created_by = u.id 
    $where_clause 
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - <?php echo APP_NAME; ?></title>
    <style>
        body{visibility:hidden;font-family:'Segoe UI',sans-serif;margin:0;padding:0;background:#fff;overflow-x:hidden}
        .sidebar{width:250px!important;background-color:#07bbc1!important;color:#fff!important;position:fixed!important;height:100vh!important;top:0!important;left:0!important;z-index:1000!important;transform:translate3d(0,0,0)!important;opacity:1!important;visibility:visible!important}
        .main-content{margin-left:250px!important;background-color:#fff!important;min-height:100vh!important}
        .container{display:flex!important}
        @media(max-width:768px){.sidebar{transform:translateX(-100%)!important}.sidebar.active{transform:translateX(0)!important}.main-content{margin-left:0!important}}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
    <link rel="stylesheet" href="../assets/css/style.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="../assets/css/style.css"></noscript>
</head>
<body>
    <div class="container">
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <button class="mobile-menu-btn">☰ Menu</button>
            
            <div class="header clearfix">
                <h1>Projects</h1>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
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
                <!-- Projects List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Relief Programs (<?php echo count($projects); ?>)</h3>
                        <a href="?action=add" class="btn btn-primary" style="float: right;">Create New Project</a>
                    </div>
                    
                    <!-- Search and Filters -->
                    <form method="GET" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 10px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Project name, code, or location..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="planning" <?php echo $status_filter === 'planning' ? 'selected' : ''; ?>>Planning</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Donor</label>
                                <input type="text" name="donor" class="form-control" 
                                       placeholder="Donor name..." 
                                       value="<?php echo htmlspecialchars($donor_filter); ?>">
                            </div>
                            <button type="submit" class="btn btn-secondary">Filter</button>
                        </div>
                    </form>
                    
                    <?php if (empty($projects)): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">
                            No projects found. <a href="?action=add">Create the first project</a>
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Code</th>
                                        <th>Donor</th>
                                        <th>Target Location</th>
                                        <th>Beneficiaries</th>
                                        <th>Budget</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($project['project_name']); ?></strong>
                                                <?php if ($project['description']): ?>
                                                    <br><small style="color: #666;"><?php echo htmlspecialchars(substr($project['description'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($project['project_code']): ?>
                                                    <code><?php echo htmlspecialchars($project['project_code']); ?></code>
                                                <?php else: ?>
                                                    <span style="color: #666;">No code</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($project['donor_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($project['target_location'] ?: 'N/A'); ?></td>
                                            <td>
                                                <strong><?php echo formatNumber($project['beneficiaries_reached']); ?></strong>
                                                <?php if ($project['target_beneficiaries']): ?>
                                                    / <?php echo formatNumber($project['target_beneficiaries']); ?>
                                                    <?php 
                                                    $percentage = $project['target_beneficiaries'] > 0 ? 
                                                        round(($project['beneficiaries_reached'] / $project['target_beneficiaries']) * 100) : 0;
                                                    ?>
                                                    <br><small style="color: #666;">(<?php echo $percentage; ?>%)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($project['budget']): ?>
                                                    $<?php echo formatNumber($project['budget'], 2); ?>
                                                <?php else: ?>
                                                    <span style="color: #666;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; 
                                                      background-color: <?php 
                                                          echo $project['status'] === 'active' ? '#d4edda' : 
                                                               ($project['status'] === 'completed' ? '#e3f2fd' : 
                                                               ($project['status'] === 'suspended' ? '#f8d7da' : '#fff3cd')); 
                                                      ?>;">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($project['start_date'] && $project['end_date']): ?>
                                                    <?php echo formatDate($project['start_date'], 'M j'); ?> - 
                                                    <?php echo formatDate($project['end_date'], 'M j, Y'); ?>
                                                <?php elseif ($project['start_date']): ?>
                                                    From <?php echo formatDate($project['start_date']); ?>
                                                <?php else: ?>
                                                    <span style="color: #666;">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=view&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-small btn-primary">View</a>
                                                <a href="?action=edit&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-small btn-secondary">Edit</a>
                                                <a href="?action=delete&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-small btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this project?')">Delete</a>
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
                            <?php echo $action === 'add' ? 'Create New Project' : 'Edit Project'; ?>
                        </h3>
                    </div>
                    
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Project Name *</label>
                                    <input type="text" name="project_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($project_data['project_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Project Code</label>
                                    <input type="text" name="project_code" class="form-control"
                                           value="<?php echo htmlspecialchars($project_data['project_code'] ?? ''); ?>"
                                           placeholder="e.g., SOM-2024-001">
                                    <small style="color: #666;">Unique identifier for this project</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Donor/Funding Organization</label>
                                    <input type="text" name="donor_name" class="form-control"
                                           value="<?php echo htmlspecialchars($project_data['donor_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Target Location</label>
                                    <input type="text" name="target_location" class="form-control"
                                           value="<?php echo htmlspecialchars($project_data['target_location'] ?? ''); ?>"
                                           placeholder="Districts, camps, or regions">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Target Beneficiaries</label>
                                    <input type="number" name="target_beneficiaries" class="form-control" min="0"
                                           value="<?php echo $project_data['target_beneficiaries'] ?? ''; ?>"
                                           placeholder="Number of people to reach">
                                </div>
                            </div>
                            
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control"
                                           value="<?php echo $project_data['start_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control"
                                           value="<?php echo $project_data['end_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Budget (USD)</label>
                                    <input type="number" name="budget" class="form-control" step="0.01" min="0"
                                           value="<?php echo $project_data['budget'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-control" required>
                                        <option value="planning" <?php echo ($project_data['status'] ?? 'planning') === 'planning' ? 'selected' : ''; ?>>Planning</option>
                                        <option value="active" <?php echo ($project_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo ($project_data['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="suspended" <?php echo ($project_data['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4"
                                              placeholder="Project objectives and activities..."><?php echo htmlspecialchars($project_data['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Create Project' : 'Update Project'; ?>
                            </button>
                            <a href="projects.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'view'): ?>
                <!-- Project Details View -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($project_details['project_name']); ?></h3>
                        <div style="float: right;">
                            <a href="?action=edit&id=<?php echo $project_id; ?>" class="btn btn-secondary">Edit Project</a>
                            <a href="projects.php" class="btn btn-secondary">Back to List</a>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <h4>Project Information</h4>
                            <table style="width: 100%; font-size: 14px;">
                                <tr><td><strong>Code:</strong></td><td><?php echo htmlspecialchars($project_details['project_code'] ?: 'N/A'); ?></td></tr>
                                <tr><td><strong>Donor:</strong></td><td><?php echo htmlspecialchars($project_details['donor_name'] ?: 'N/A'); ?></td></tr>
                                <tr><td><strong>Location:</strong></td><td><?php echo htmlspecialchars($project_details['target_location'] ?: 'N/A'); ?></td></tr>
                                <tr><td><strong>Status:</strong></td><td><span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; background-color: #d4edda;"><?php echo ucfirst($project_details['status']); ?></span></td></tr>
                                <tr><td><strong>Duration:</strong></td><td>
                                    <?php if ($project_details['start_date'] && $project_details['end_date']): ?>
                                        <?php echo formatDate($project_details['start_date']); ?> to <?php echo formatDate($project_details['end_date']); ?>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </td></tr>
                                <tr><td><strong>Budget:</strong></td><td>
                                    <?php echo $project_details['budget'] ? '$' . formatNumber($project_details['budget'], 2) : 'N/A'; ?>
                                </td></tr>
                                <tr><td><strong>Created by:</strong></td><td><?php echo htmlspecialchars($project_details['created_by_name'] ?: 'Unknown'); ?></td></tr>
                            </table>
                        </div>
                        
                        <div>
                            <h4>Project Statistics</h4>
                            <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo formatNumber($project_stats['unique_beneficiaries']); ?></span>
                                    <div class="stat-label">People Reached</div>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo formatNumber($project_stats['total_deliveries']); ?></span>
                                    <div class="stat-label">Total Deliveries</div>
                                </div>
                            </div>
                            
                            <?php if ($project_details['target_beneficiaries']): ?>
                                <div style="margin-top: 15px;">
                                    <strong>Progress:</strong> 
                                    <?php 
                                    $progress = round(($project_stats['unique_beneficiaries'] / $project_details['target_beneficiaries']) * 100);
                                    ?>
                                    <?php echo $progress; ?>% of target (<?php echo formatNumber($project_details['target_beneficiaries']); ?> people)
                                    <div style="background-color: #e0e0e0; height: 10px; border-radius: 5px; margin-top: 5px;">
                                        <div style="background-color: #f68e1f; height: 100%; width: <?php echo min($progress, 100); ?>%; border-radius: 5px;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($project_details['description']): ?>
                        <div style="margin-bottom: 20px;">
                            <h4>Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($project_details['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <h4>Recent Deliveries</h4>
                    <?php if (empty($project_deliveries)): ?>
                        <p style="color: #666; font-style: italic;">No deliveries recorded for this project yet.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Recipient</th>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($project_deliveries, 0, 10) as $delivery): ?>
                                        <tr>
                                            <td><?php echo formatDate($delivery['delivery_date']); ?></td>
                                            <td><?php echo htmlspecialchars($delivery['recipient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($delivery['item_name']); ?></td>
                                            <td><?php echo formatNumber($delivery['quantity_delivered'], 2); ?> <?php echo htmlspecialchars($delivery['unit_type']); ?></td>
                                            <td><?php echo htmlspecialchars($delivery['delivery_location']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($project_deliveries) > 10): ?>
                            <p style="text-align: center; margin-top: 10px;">
                                <a href="aid_delivery.php?project=<?php echo $project_id; ?>">View all <?php echo count($project_deliveries); ?> deliveries</a>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/performance.js" async></script>
    <script src="../assets/js/script.js" defer></script>
    <script>document.body.style.visibility='visible';</script>
</body>
</html>
