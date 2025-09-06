<?php
require_once 'config/config.php';
requireLogin();

$pdo = getDBConnection();

// Get dashboard statistics
try {
    // Total aid recipients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM aid_recipients");
    $total_recipients = $stmt->fetch()['total'];
    
    // Total supplies
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM supplies");
    $total_supplies = $stmt->fetch()['total'];
    
    // Active projects
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE status = 'active'");
    $active_projects = $stmt->fetch()['total'];
    
    // Recent deliveries (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM aid_deliveries WHERE delivery_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $recent_deliveries = $stmt->fetch()['total'];
    
    // Low stock items
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM supplies WHERE current_stock <= minimum_stock");
    $low_stock_items = $stmt->fetch()['total'];
    
    // Recent activities
    $stmt = $pdo->prepare("
        SELECT ar.*, u.full_name 
        FROM activity_records ar 
        JOIN users u ON ar.user_id = u.id 
        ORDER BY ar.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total_recipients = $total_supplies = $active_projects = $recent_deliveries = $low_stock_items = 0;
    $recent_activities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <style>
        body{visibility:hidden;font-family:'Segoe UI',sans-serif;margin:0;padding:0;background:#fff}
        .sidebar{width:250px!important;background-color:#07bbc1!important;color:#fff!important;position:fixed!important;height:100vh!important;top:0!important;left:0!important;z-index:1000!important;transform:translate3d(0,0,0)!important;opacity:1!important;visibility:visible!important}
        .main-content{margin-left:250px!important;background-color:#fff!important;min-height:100vh!important}
        .container{display:flex!important}
        @media(max-width:768px){.sidebar{transform:translateX(-100%)!important}.sidebar.active{transform:translateX(0)!important}.main-content{margin-left:0!important}}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
    <link rel="stylesheet" href="assets/css/style.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="assets/css/style.css"></noscript>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <button class="mobile-menu-btn">☰ Menu</button>
            
            <div class="header clearfix">
                <h1>Dashboard</h1>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
                    (<?php echo ucfirst($_SESSION['role']); ?>)
                    <a href="logout.php">Logout</a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <?php if (canAccess('recipients')): ?>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatNumber($total_recipients); ?></span>
                    <div class="stat-label">Aid Recipients</div>
                </div>
                <?php endif; ?>
                
                <?php if (canAccess('deliveries')): ?>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatNumber($recent_deliveries); ?></span>
                    <div class="stat-label">Deliveries (30 days)</div>
                </div>
                <?php endif; ?>
                
                <?php if (canAccess('supplies')): ?>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatNumber($total_supplies); ?></span>
                    <div class="stat-label">Supply Items</div>
                </div>
                <?php endif; ?>
                
                <?php if (canAccess('projects')): ?>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatNumber($active_projects); ?></span>
                    <div class="stat-label">Active Projects</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($low_stock_items > 0 && canAccess('supplies')): ?>
            <div class="alert alert-warning">
                <strong>Stock Alert:</strong> <?php echo $low_stock_items; ?> item(s) are running low. 
                <a href="program/supplies.php" style="color: #856404; text-decoration: underline;">Check supplies</a>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div style="display: grid; gap: 10px;">
                        <?php if (canAccess('recipients')): ?>
                        <a href="program/aid_recipients.php?action=add" class="btn btn-primary">Register New Recipient</a>
                        <?php endif; ?>
                        
                        <?php if (canAccess('deliveries')): ?>
                        <a href="program/aid_delivery.php?action=add" class="btn btn-secondary">Record Aid Delivery</a>
                        <?php endif; ?>
                        
                        <?php if (canAccess('supplies')): ?>
                        <a href="program/supplies.php?action=add" class="btn btn-secondary">Add Supply Item</a>
                        <?php endif; ?>
                        
                        <?php if (canAccess('reports')): ?>
                        <a href="program/reports.php" class="btn btn-secondary">Generate Report</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <?php if (canAccess('dashboard_activity')): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activities)): ?>
                            <p class="text-muted">No recent activity to display.</p>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <?php
                                            switch ($activity['action_type']) {
                                                case 'create': echo '➕'; break;
                                                case 'update': echo '✏️'; break;
                                                case 'delete': echo '🗑️'; break;
                                                case 'login': echo '🔐'; break;
                                                default: echo '📝'; break;
                                            }
                                            ?>
                                        </div>
                                        <div class="activity-content">
                                            <p><strong><?php echo htmlspecialchars($activity['full_name']); ?></strong> 
                                               <?php echo htmlspecialchars($activity['description']); ?></p>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="admin/activity.php" class="btn btn-link">View More</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>System Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="system-info">
                            <div>
                                <strong>Version:</strong><br>
                                <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
                            </div>
                            <div>
                                <strong>Server Time:</strong><br>
                                <?php echo date('Y-m-d H:i:s T'); ?>
                            </div>
                            <div>
                                <strong>Your Role:</strong><br>
                                <?php echo ucfirst($_SESSION['role']); ?>
                            </div>
                            <div>
                                <strong>Last Login:</strong><br>
                                <?php 
                                // Get actual last login from database
                                $stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $user_data = $stmt->fetch();
                                
                                if ($user_data && $user_data['last_login'] && $user_data['last_login'] != '0000-00-00 00:00:00') {
                                    echo date('M j, Y g:i A', strtotime($user_data['last_login']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="js/performance.js" async></script>
    <script src="script.js" defer></script>
    <script>
        // Immediate page optimization
        document.body.style.visibility = 'visible';
        
        // Navigation loading feedback
        document.addEventListener('click', function(e) {
            const link = e.target.closest('.sidebar a[href$=".php"]');
            if (link) {
                link.style.opacity = '0.7';
                link.innerHTML = link.innerHTML.replace(/^/, '⏳ ');
            }
        });
    </script>
</body>
</html>
