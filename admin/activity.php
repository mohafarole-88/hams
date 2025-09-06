<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();

// Get activity records with search and filters
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(ar.description LIKE ? OR ar.table_affected LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_filter) {
    $where_conditions[] = "ar.action_type = ?";
    $params[] = $action_filter;
}

if ($user_filter) {
    $where_conditions[] = "ar.user_id = ?";
    $params[] = $user_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(ar.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(ar.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get activity records
$stmt = $pdo->prepare("
    SELECT ar.*, u.full_name as user_name 
    FROM activity_records ar 
    LEFT JOIN users u ON ar.user_id = u.id 
    $where_clause 
    ORDER BY ar.created_at DESC 
    LIMIT 500
");
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get users for filter
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
$users = $stmt->fetchAll();

// Get activity statistics
$stmt = $pdo->query("
    SELECT 
        action_type,
        COUNT(*) as count,
        DATE(created_at) as activity_date
    FROM activity_records 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY action_type, DATE(created_at)
    ORDER BY activity_date DESC, count DESC
");
$activity_stats = $stmt->fetchAll();

// Group stats by action type
$stats_by_action = [];
foreach ($activity_stats as $stat) {
    if (!isset($stats_by_action[$stat['action_type']])) {
        $stats_by_action[$stat['action_type']] = 0;
    }
    $stats_by_action[$stat['action_type']] += $stat['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Records - <?php echo APP_NAME; ?></title>
    <style>
        body{visibility:hidden;}
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
                <h1>Activity Records</h1>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
                    (<?php echo ucfirst($_SESSION['role']); ?>)
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <!-- Activity Statistics -->
            <div class="stats-grid">
                <?php foreach ($stats_by_action as $action => $count): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo formatNumber($count); ?></div>
                        <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $action)); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Search and Filter -->
            <div class="card">
                <div class="card-header">
                    <h3>System Activity Log</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="search-form">
                        <div class="search-group">
                            <input type="text" name="search" placeholder="Description or table..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <select name="action">
                                <option value="">All Actions</option>
                                <option value="login" <?php echo $action_filter === 'login' ? 'selected' : ''; ?>>Login</option>
                                <option value="logout" <?php echo $action_filter === 'logout' ? 'selected' : ''; ?>>Logout</option>
                                <option value="create" <?php echo $action_filter === 'create' ? 'selected' : ''; ?>>Create</option>
                                <option value="update" <?php echo $action_filter === 'update' ? 'selected' : ''; ?>>Update</option>
                                <option value="delete" <?php echo $action_filter === 'delete' ? 'selected' : ''; ?>>Delete</option>
                                <option value="delivery" <?php echo $action_filter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                                <option value="report" <?php echo $action_filter === 'report' ? 'selected' : ''; ?>>Report</option>
                                <option value="registration" <?php echo $action_filter === 'registration' ? 'selected' : ''; ?>>Registration</option>
                                <option value="stock_adjustment" <?php echo $action_filter === 'stock_adjustment' ? 'selected' : ''; ?>>Stock Adjustment</option>
                            </select>
                            <select name="user">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            <button type="submit" class="btn btn-secondary">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Records Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                
                        <?php if (empty($activities)): ?>
                            <p style="text-align: center; color: #666; padding: 40px;">
                                No activity records found for the selected criteria.
                            </p>
                        <?php else: ?>
                            <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Table</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <?php echo formatDate($activity['created_at'], 'M j, Y'); ?>
                                            <br><small style="color: #666;"><?php echo formatDate($activity['created_at'], 'g:i A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['user_name'] ?: 'Unknown'); ?></td>
                                        <td>
                                            <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; 
                                                  background-color: <?php 
                                                      echo $activity['action_type'] === 'login' ? '#d4edda' : 
                                                           ($activity['action_type'] === 'logout' ? '#f8d7da' : 
                                                           ($activity['action_type'] === 'delivery' ? '#e3f2fd' : 
                                                           ($activity['action_type'] === 'create' ? '#d1ecf1' : 
                                                           ($activity['action_type'] === 'update' ? '#fff3cd' : 
                                                           ($activity['action_type'] === 'delete' ? '#f5c6cb' : '#f8f9fa'))))); 
                                                  ?>;
                                                  color: <?php 
                                                      echo $activity['action_type'] === 'login' ? '#155724' : 
                                                           ($activity['action_type'] === 'logout' ? '#721c24' : 
                                                           ($activity['action_type'] === 'delivery' ? '#0c5460' : 
                                                           ($activity['action_type'] === 'create' ? '#0c5460' : 
                                                           ($activity['action_type'] === 'update' ? '#856404' : 
                                                           ($activity['action_type'] === 'delete' ? '#721c24' : '#495057'))))); 
                                                  ?>;">
                                                <?php 
                                                $icon = '';
                                                switch($activity['action_type']) {
                                                    case 'login': $icon = '🔐 '; break;
                                                    case 'logout': $icon = '🚪 '; break;
                                                    case 'create': $icon = '➕ '; break;
                                                    case 'update': $icon = '✏️ '; break;
                                                    case 'delete': $icon = '🗑️ '; break;
                                                    case 'delivery': $icon = '📦 '; break;
                                                    default: $icon = '📝 '; break;
                                                }
                                                echo $icon . ucfirst(str_replace('_', ' ', $activity['action_type']));
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($activity['description']): ?>
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            <?php else: ?>
                                                <span style="color: #666;">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($activity['table_affected']): ?>
                                                <code><?php echo htmlspecialchars($activity['table_affected']); ?></code>
                                                <?php if ($activity['record_id']): ?>
                                                    <br><small style="color: #666;">ID: <?php echo $activity['record_id']; ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #666;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small style="color: #666;"><?php echo htmlspecialchars($activity['ip_address'] ?: 'Unknown'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (count($activities) >= 500): ?>
                            <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 4px; text-align: center;">
                                <strong>Note:</strong> Showing the most recent 500 activities. Use filters to narrow down results.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/performance.js" async></script>
    <script src="../assets/js/script.js" defer></script>
    <script>document.body.style.visibility='visible';</script>
</body>
</html>
