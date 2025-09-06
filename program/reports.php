<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();
$report_type = $_GET['type'] ?? 'summary';
$export = $_GET['export'] ?? '';

// Date range filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$project_filter = $_GET['project'] ?? '';

// Get projects for filter
$stmt = $pdo->query("SELECT id, project_name FROM projects ORDER BY project_name");
$projects = $stmt->fetchAll();

// Generate reports based on type
$report_data = [];
$report_title = '';

try {
    if ($report_type === 'summary') {
        $report_title = 'Summary Report';
        
        // Overall statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM aid_recipients");
        $total_recipients = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM supplies");
        $total_supplies = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE status = 'active'");
        $active_projects = $stmt->fetch()['total'];
        
        $where_clause = "WHERE ad.delivery_date BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        
        if ($project_filter) {
            $where_clause .= " AND ad.project_id = ?";
            $params[] = $project_filter;
        }
        
        // Deliveries in date range
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aid_deliveries ad $where_clause");
        $stmt->execute($params);
        $period_deliveries = $stmt->fetch()['total'];
        
        // Unique beneficiaries reached
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ad.recipient_id) as total FROM aid_deliveries ad $where_clause");
        $stmt->execute($params);
        $period_beneficiaries = $stmt->fetch()['total'];
        
        // Deliveries by category
        $stmt = $pdo->prepare("
            SELECT s.category, COUNT(ad.id) as delivery_count, SUM(ad.quantity_delivered) as total_quantity
            FROM aid_deliveries ad 
            JOIN supplies s ON ad.supply_id = s.id 
            $where_clause 
            GROUP BY s.category 
            ORDER BY delivery_count DESC
        ");
        $stmt->execute($params);
        $deliveries_by_category = $stmt->fetchAll();
        
        // Top recipients
        $stmt = $pdo->prepare("
            SELECT ar.full_name, ar.recipient_id, ar.location, COUNT(ad.id) as delivery_count
            FROM aid_deliveries ad 
            JOIN aid_recipients ar ON ad.recipient_id = ar.id 
            $where_clause 
            GROUP BY ad.recipient_id 
            ORDER BY delivery_count DESC 
            LIMIT 10
        ");
        $stmt->execute($params);
        $top_recipients = $stmt->fetchAll();
        
        $report_data = [
            'stats' => [
                'total_recipients' => $total_recipients,
                'total_supplies' => $total_supplies,
                'active_projects' => $active_projects,
                'period_deliveries' => $period_deliveries,
                'period_beneficiaries' => $period_beneficiaries
            ],
            'deliveries_by_category' => $deliveries_by_category,
            'top_recipients' => $top_recipients
        ];
        
    } elseif ($report_type === 'deliveries') {
        $report_title = 'Deliveries Report';
        
        $where_clause = "WHERE ad.delivery_date BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        
        if ($project_filter) {
            $where_clause .= " AND ad.project_id = ?";
            $params[] = $project_filter;
        }
        
        $stmt = $pdo->prepare("
            SELECT ad.*, ar.full_name as recipient_name, ar.recipient_id as recipient_code,
                   s.item_name, s.unit_type, p.project_name, u.full_name as delivered_by_name
            FROM aid_deliveries ad 
            LEFT JOIN aid_recipients ar ON ad.recipient_id = ar.id 
            LEFT JOIN supplies s ON ad.supply_id = s.id 
            LEFT JOIN projects p ON ad.project_id = p.id 
            LEFT JOIN users u ON ad.delivered_by = u.id 
            $where_clause 
            ORDER BY ad.delivery_date DESC
        ");
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        
    } elseif ($report_type === 'recipients') {
        $report_title = 'Recipients Report';
        
        $stmt = $pdo->prepare("
            SELECT ar.*, 
                   COUNT(ad.id) as total_deliveries,
                   MAX(ad.delivery_date) as last_delivery_date
            FROM aid_recipients ar 
            LEFT JOIN aid_deliveries ad ON ar.id = ad.recipient_id 
            GROUP BY ar.id 
            ORDER BY ar.full_name
        ");
        $stmt->execute();
        $report_data = $stmt->fetchAll();
        
    } elseif ($report_type === 'supplies') {
        $report_title = 'Supplies Report';
        
        $stmt = $pdo->prepare("
            SELECT s.*, w.name as warehouse_name,
                   COALESCE(SUM(ad.quantity_delivered), 0) as total_delivered
            FROM supplies s 
            LEFT JOIN warehouses w ON s.warehouse_id = w.id 
            LEFT JOIN aid_deliveries ad ON s.id = ad.supply_id 
            GROUP BY s.id 
            ORDER BY s.item_name
        ");
        $stmt->execute();
        $report_data = $stmt->fetchAll();
        
    } elseif ($report_type === 'projects') {
        $report_title = 'Projects Report';
        
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as created_by_name,
                   COUNT(DISTINCT ad.recipient_id) as beneficiaries_reached,
                   COUNT(ad.id) as total_deliveries,
                   SUM(ad.quantity_delivered * s.cost_per_unit) as total_value
            FROM projects p 
            LEFT JOIN users u ON p.created_by = u.id 
            LEFT JOIN aid_deliveries ad ON p.id = ad.project_id 
            LEFT JOIN supplies s ON ad.supply_id = s.id 
            GROUP BY p.id 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
        $report_data = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = 'Error generating report: ' . $e->getMessage();
    $report_data = [];
}

// Handle CSV export
if ($export === 'csv' && !empty($report_data)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($report_type === 'summary') {
        // Export summary statistics
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Recipients', $report_data['stats']['total_recipients']]);
        fputcsv($output, ['Period Deliveries', $report_data['stats']['period_deliveries']]);
        fputcsv($output, ['People Reached (Period)', $report_data['stats']['period_beneficiaries']]);
        fputcsv($output, ['Active Projects', $report_data['stats']['active_projects']]);
        
        // Add empty row
        fputcsv($output, []);
        
        // Export deliveries by category
        fputcsv($output, ['Deliveries by Category']);
        fputcsv($output, ['Category', 'Deliveries', 'Total Quantity']);
        foreach ($report_data['deliveries_by_category'] as $category) {
            fputcsv($output, [
                ucfirst($category['category']),
                $category['delivery_count'],
                $category['total_quantity']
            ]);
        }
        
        // Add empty row
        fputcsv($output, []);
        
        // Export top recipients
        fputcsv($output, ['Top Recipients']);
        fputcsv($output, ['Name', 'Recipient ID', 'Location', 'Deliveries']);
        foreach ($report_data['top_recipients'] as $recipient) {
            fputcsv($output, [
                $recipient['full_name'],
                $recipient['recipient_id'],
                $recipient['location'],
                $recipient['delivery_count']
            ]);
        }
    } elseif ($report_type === 'deliveries') {
        fputcsv($output, ['Date', 'Recipient Name', 'Recipient ID', 'Item', 'Quantity', 'Unit', 'Location', 'Project', 'Delivered By', 'Receipt Signed', 'Notes']);
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['delivery_date'],
                $row['recipient_name'],
                $row['recipient_code'],
                $row['item_name'],
                $row['quantity_delivered'],
                $row['unit_type'],
                $row['delivery_location'],
                $row['project_name'] ?: 'No project',
                $row['delivered_by_name'],
                $row['receipt_signature'] ? 'Yes' : 'No',
                $row['notes']
            ]);
        }
    } elseif ($report_type === 'recipients') {
        fputcsv($output, ['Recipient ID', 'Full Name', 'Phone', 'Location', 'District', 'Household Size', 'Displacement Status', 'Vulnerability Level', 'Registration Date', 'Total Deliveries', 'Last Delivery']);
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['recipient_id'],
                $row['full_name'],
                $row['phone'],
                $row['location'],
                $row['district'],
                $row['household_size'],
                $row['displacement_status'],
                $row['vulnerability_level'],
                $row['registration_date'],
                $row['total_deliveries'],
                $row['last_delivery_date']
            ]);
        }
    } elseif ($report_type === 'supplies') {
        fputcsv($output, ['Item Name', 'Category', 'Current Stock', 'Minimum Stock', 'Unit Type', 'Warehouse', 'Expiry Date', 'Cost per Unit', 'Supplier', 'Total Delivered']);
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['item_name'],
                $row['category'],
                $row['current_stock'],
                $row['minimum_stock'],
                $row['unit_type'],
                $row['warehouse_name'],
                $row['expiry_date'],
                $row['cost_per_unit'],
                $row['supplier'],
                $row['total_delivered']
            ]);
        }
    } elseif ($report_type === 'projects') {
        fputcsv($output, ['Project Name', 'Code', 'Donor', 'Target Location', 'Target Beneficiaries', 'Beneficiaries Reached', 'Start Date', 'End Date', 'Budget', 'Status', 'Total Deliveries', 'Total Value', 'Created By']);
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['project_name'],
                $row['project_code'],
                $row['donor_name'],
                $row['target_location'],
                $row['target_beneficiaries'],
                $row['beneficiaries_reached'],
                $row['start_date'],
                $row['end_date'],
                $row['budget'],
                $row['status'],
                $row['total_deliveries'],
                $row['total_value'],
                $row['created_by_name']
            ]);
        }
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <style>
        body{visibility:hidden;font-family:'Segoe UI',sans-serif;margin:0;padding:0;background:#fff;overflow-x:hidden}
        .sidebar{width:250px!important;background-color:#07bbc1!important;color:#fff!important;position:fixed!important;height:100vh!important;top:0!important;left:0!important;z-index:1000!important;transform:translate3d(0,0,0)!important;opacity:1!important;visibility:visible!important}
        .main-content{margin-left:250px!important;background-color:#fff!important;min-height:100vh!important;padding:20px!important}
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
                <h1>Reports</h1>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
                    (<?php echo ucfirst($_SESSION['role']); ?>)
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <!-- Report Controls -->
            <div class="card no-print">
                <div class="card-header">
                    <h3 class="card-title">Generate Report</h3>
                </div>
                
                <form method="GET">
                    <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 10px; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-control">
                                <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                <option value="deliveries" <?php echo $report_type === 'deliveries' ? 'selected' : ''; ?>>Deliveries Report</option>
                                <option value="recipients" <?php echo $report_type === 'recipients' ? 'selected' : ''; ?>>Recipients Report</option>
                                <option value="supplies" <?php echo $report_type === 'supplies' ? 'selected' : ''; ?>>Supplies Report</option>
                                <option value="projects" <?php echo $report_type === 'projects' ? 'selected' : ''; ?>>Projects Report</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Project</label>
                            <select name="project" class="form-control">
                                <option value="">All Projects</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo $project_filter == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Generate</button>
                        
                        <?php if (!empty($report_data)): ?>
                            <div>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                                   class="btn btn-secondary">Export CSV</a>
                                <button onclick="window.print()" class="btn btn-secondary">Print</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Report Content -->
            <?php if (!empty($report_data)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $report_title; ?></h3>
                    </div>
                    
                    <?php if ($report_type === 'summary'): ?>
                        <!-- Summary Report -->
                        <div class="stats-grid" style="margin-bottom: 30px;">
                            <div class="stat-card">
                                <span class="stat-number"><?php echo formatNumber($report_data['stats']['total_recipients']); ?></span>
                                <div class="stat-label">Total Recipients</div>
                            </div>
                            <div class="stat-card">
                                <span class="stat-number"><?php echo formatNumber($report_data['stats']['period_deliveries']); ?></span>
                                <div class="stat-label">Deliveries (Period)</div>
                            </div>
                            <div class="stat-card">
                                <span class="stat-number"><?php echo formatNumber($report_data['stats']['period_beneficiaries']); ?></span>
                                <div class="stat-label">People Reached (Period)</div>
                            </div>
                            <div class="stat-card">
                                <span class="stat-number"><?php echo formatNumber($report_data['stats']['active_projects']); ?></span>
                                <div class="stat-label">Active Projects</div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h4>Deliveries by Category</h4>
                                <?php if (empty($report_data['deliveries_by_category'])): ?>
                                    <p style="color: #666;">No deliveries in selected period</p>
                                <?php else: ?>
                                    <table class="table">
                                        <thead>
                                            <tr><th>Category</th><th>Deliveries</th><th>Total Quantity</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['deliveries_by_category'] as $category): ?>
                                                <tr>
                                                    <td><?php echo ucfirst($category['category']); ?></td>
                                                    <td><?php echo formatNumber($category['delivery_count']); ?></td>
                                                    <td><?php echo formatNumber($category['total_quantity'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <h4>Top Recipients</h4>
                                <?php if (empty($report_data['top_recipients'])): ?>
                                    <p style="color: #666;">No recipients in selected period</p>
                                <?php else: ?>
                                    <table class="table">
                                        <thead>
                                            <tr><th>Name</th><th>Location</th><th>Deliveries</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['top_recipients'] as $recipient): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($recipient['full_name']); ?>
                                                        <br><small><?php echo htmlspecialchars($recipient['recipient_id']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($recipient['location']); ?></td>
                                                    <td><?php echo formatNumber($recipient['delivery_count']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    <?php elseif ($report_type === 'deliveries'): ?>
                        <!-- Deliveries Report -->
                        <?php if (empty($report_data)): ?>
                            <p style="text-align: center; color: #666; padding: 40px;">No deliveries found for the selected criteria.</p>
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
                                            <th>Project</th>
                                            <th>Delivered By</th>
                                            <th>Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $delivery): ?>
                                            <tr>
                                                <td><?php echo formatDate($delivery['delivery_date']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($delivery['recipient_name']); ?>
                                                    <br><small><?php echo htmlspecialchars($delivery['recipient_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($delivery['item_name']); ?></td>
                                                <td><?php echo formatNumber($delivery['quantity_delivered'], 2); ?> <?php echo htmlspecialchars($delivery['unit_type']); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['delivery_location']); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['project_name'] ?: 'No project'); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['delivered_by_name']); ?></td>
                                                <td><?php echo $delivery['receipt_signature'] ? 'Yes' : 'No'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($report_type === 'recipients'): ?>
                        <!-- Recipients Report -->
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
                                        <th>Deliveries</th>
                                        <th>Last Delivery</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $recipient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($recipient['recipient_id']); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['location']); ?></td>
                                            <td><?php echo $recipient['household_size']; ?></td>
                                            <td><?php echo ucfirst($recipient['displacement_status']); ?></td>
                                            <td><?php echo ucfirst($recipient['vulnerability_level']); ?></td>
                                            <td><?php echo formatDate($recipient['registration_date']); ?></td>
                                            <td><?php echo formatNumber($recipient['total_deliveries']); ?></td>
                                            <td><?php echo $recipient['last_delivery_date'] ? formatDate($recipient['last_delivery_date']) : 'Never'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php elseif ($report_type === 'supplies'): ?>
                        <!-- Supplies Report -->
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Min. Stock</th>
                                        <th>Unit</th>
                                        <th>Warehouse</th>
                                        <th>Expiry</th>
                                        <th>Total Delivered</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $supply): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($supply['item_name']); ?></td>
                                            <td><?php echo ucfirst($supply['category']); ?></td>
                                            <td><?php echo formatNumber($supply['current_stock'], 2); ?></td>
                                            <td><?php echo formatNumber($supply['minimum_stock'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($supply['unit_type']); ?></td>
                                            <td><?php echo htmlspecialchars($supply['warehouse_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo $supply['expiry_date'] ? formatDate($supply['expiry_date']) : 'No expiry'; ?></td>
                                            <td><?php echo formatNumber($supply['total_delivered'], 2); ?></td>
                                            <td>
                                                <?php if ($supply['current_stock'] == 0): ?>
                                                    <span style="color: #dc3545;">Out of Stock</span>
                                                <?php elseif ($supply['current_stock'] <= $supply['minimum_stock']): ?>
                                                    <span style="color: #856404;">Low Stock</span>
                                                <?php else: ?>
                                                    <span style="color: #28a745;">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php elseif ($report_type === 'projects'): ?>
                        <!-- Projects Report -->
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Code</th>
                                        <th>Donor</th>
                                        <th>Target</th>
                                        <th>Reached</th>
                                        <th>Progress</th>
                                        <th>Budget</th>
                                        <th>Status</th>
                                        <th>Deliveries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                            <td><?php echo htmlspecialchars($project['project_code'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($project['donor_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo formatNumber($project['target_beneficiaries'] ?: 0); ?></td>
                                            <td><?php echo formatNumber($project['beneficiaries_reached']); ?></td>
                                            <td>
                                                <?php if ($project['target_beneficiaries']): ?>
                                                    <?php echo round(($project['beneficiaries_reached'] / $project['target_beneficiaries']) * 100); ?>%
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $project['budget'] ? '$' . formatNumber($project['budget'], 2) : 'N/A'; ?></td>
                                            <td><?php echo ucfirst($project['status']); ?></td>
                                            <td><?php echo formatNumber($project['total_deliveries']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
