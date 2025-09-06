<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$supply_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $data = [
        'item_name' => sanitizeInput($_POST['item_name']),
        'category' => $_POST['category'],
        'unit_type' => sanitizeInput($_POST['unit_type']),
        'current_stock' => (float)($_POST['current_stock'] ?? 0),
        'minimum_stock' => (float)($_POST['minimum_stock'] ?? 0),
        'warehouse_id' => (int)($_POST['warehouse_id'] ?? 1),
        'expiry_date' => $_POST['expiry_date'] ?: null,
        'cost_per_unit' => (float)($_POST['cost_per_unit'] ?? 0),
        'supplier' => sanitizeInput($_POST['supplier']),
        'notes' => sanitizeInput($_POST['notes'])
    ];
    
    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO supplies 
                (item_name, category, unit_type, current_stock, minimum_stock, 
                 warehouse_id, expiry_date, cost_per_unit, supplier, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['item_name'], $data['category'], $data['unit_type'], 
                $data['current_stock'], $data['minimum_stock'], $data['warehouse_id'],
                $data['expiry_date'], $data['cost_per_unit'], $data['supplier'], $data['notes']
            ]);
            
            logActivity($_SESSION['user_id'], 'create', 'supplies', $pdo->lastInsertId(), 
                       'Added new supply item: ' . $data['item_name']);
            
            $message = 'Supply item added successfully.';
            $action = 'list';
        } elseif ($action === 'edit' && $supply_id) {
            $stmt = $pdo->prepare("
                UPDATE supplies SET 
                item_name = ?, category = ?, unit_type = ?, current_stock = ?, 
                minimum_stock = ?, warehouse_id = ?, expiry_date = ?, cost_per_unit = ?, 
                supplier = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['item_name'], $data['category'], $data['unit_type'], 
                $data['current_stock'], $data['minimum_stock'], $data['warehouse_id'],
                $data['expiry_date'], $data['cost_per_unit'], $data['supplier'], 
                $data['notes'], $supply_id
            ]);
            
            logActivity($_SESSION['user_id'], 'update', 'supplies', $supply_id, 
                       'Updated supply item: ' . $data['item_name']);
            
            $message = 'Supply item updated successfully.';
            $action = 'list';
        } elseif ($action === 'adjust_stock' && $supply_id) {
            $adjustment = (float)($_POST['adjustment'] ?? 0);
            $adjustment_type = $_POST['adjustment_type'];
            $reason = sanitizeInput($_POST['reason']);
            
            if ($adjustment_type === 'add') {
                $stmt = $pdo->prepare("UPDATE supplies SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$adjustment, $supply_id]);
                $description = "Stock increased by $adjustment. Reason: $reason";
            } else {
                $stmt = $pdo->prepare("UPDATE supplies SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
                $stmt->execute([$adjustment, $supply_id]);
                $description = "Stock decreased by $adjustment. Reason: $reason";
            }
            
            logActivity($_SESSION['user_id'], 'update', 'supplies', $supply_id, $description);
            
            $message = 'Stock level adjusted successfully.';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error saving supply: ' . $e->getMessage();
    }
}

// Handle delete action
if ($action === 'delete' && $supply_id) {
    try {
        // Check if supply has deliveries
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM aid_deliveries WHERE supply_id = ?");
        $stmt->execute([$supply_id]);
        $delivery_count = $stmt->fetch()['count'];
        
        if ($delivery_count > 0) {
            $error = 'Cannot delete supply with existing deliveries.';
        } else {
            $stmt = $pdo->prepare("SELECT item_name FROM supplies WHERE id = ?");
            $stmt->execute([$supply_id]);
            $item_name = $stmt->fetch()['item_name'];
            
            $stmt = $pdo->prepare("DELETE FROM supplies WHERE id = ?");
            $stmt->execute([$supply_id]);
            
            logActivity($_SESSION['user_id'], 'delete', 'supplies', $supply_id, 
                       'Deleted supply item: ' . $item_name);
            
            $message = 'Supply item deleted successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting supply: ' . $e->getMessage();
    }
    $action = 'list';
}

// Get supply data for edit
$supply_data = null;
if (($action === 'edit' || $action === 'adjust_stock') && $supply_id) {
    $stmt = $pdo->prepare("SELECT s.*, w.name as warehouse_name FROM supplies s LEFT JOIN warehouses w ON s.warehouse_id = w.id WHERE s.id = ?");
    $stmt->execute([$supply_id]);
    $supply_data = $stmt->fetch();
    if (!$supply_data) {
        $error = 'Supply not found.';
        $action = 'list';
    }
}

// Get warehouses for dropdown
$stmt = $pdo->query("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");
$warehouses = $stmt->fetchAll();

// Get supplies list with search and filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$warehouse_filter = $_GET['warehouse'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(s.item_name LIKE ? OR s.supplier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "s.category = ?";
    $params[] = $category_filter;
}

if ($warehouse_filter) {
    $where_conditions[] = "s.warehouse_id = ?";
    $params[] = $warehouse_filter;
}

if ($stock_filter === 'low') {
    $where_conditions[] = "s.current_stock <= s.minimum_stock";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "s.current_stock = 0";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT s.*, w.name as warehouse_name 
    FROM supplies s 
    LEFT JOIN warehouses w ON s.warehouse_id = w.id 
    $where_clause 
    ORDER BY s.item_name
");
$stmt->execute($params);
$supplies = $stmt->fetchAll();

// Get stock alerts
$stmt = $pdo->query("
    SELECT COUNT(*) as low_stock_count 
    FROM supplies 
    WHERE current_stock <= minimum_stock AND current_stock > 0
");
$low_stock_count = $stmt->fetch()['low_stock_count'];

$stmt = $pdo->query("
    SELECT COUNT(*) as out_of_stock_count 
    FROM supplies 
    WHERE current_stock = 0
");
$out_of_stock_count = $stmt->fetch()['out_of_stock_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplies - <?php echo APP_NAME; ?></title>
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
                <h1>Supplies</h1>
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

            <!-- Stock Alerts -->
            <?php if ($low_stock_count > 0 || $out_of_stock_count > 0): ?>
                <div class="alert alert-warning">
                    <strong>Stock Alerts:</strong>
                    <?php if ($out_of_stock_count > 0): ?>
                        <?php echo $out_of_stock_count; ?> item(s) out of stock.
                    <?php endif; ?>
                    <?php if ($low_stock_count > 0): ?>
                        <?php echo $low_stock_count; ?> item(s) running low.
                    <?php endif; ?>
                    <a href="?stock=low" style="color: #856404; text-decoration: underline;">View alerts</a>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Supplies List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Inventory Items (<?php echo count($supplies); ?>)</h3>
                        <a href="?action=add" class="btn btn-primary" style="float: right;">Add New Item</a>
                    </div>
                    
                    <!-- Search and Filters -->
                    <form method="GET" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr auto auto auto auto; gap: 10px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Item name or supplier..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-control">
                                    <option value="">All</option>
                                    <option value="food" <?php echo $category_filter === 'food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="water" <?php echo $category_filter === 'water' ? 'selected' : ''; ?>>Water</option>
                                    <option value="shelter" <?php echo $category_filter === 'shelter' ? 'selected' : ''; ?>>Shelter</option>
                                    <option value="hygiene" <?php echo $category_filter === 'hygiene' ? 'selected' : ''; ?>>Hygiene</option>
                                    <option value="medical" <?php echo $category_filter === 'medical' ? 'selected' : ''; ?>>Medical</option>
                                    <option value="clothing" <?php echo $category_filter === 'clothing' ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse" class="form-control">
                                    <option value="">All</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?php echo $warehouse['id']; ?>" 
                                                <?php echo $warehouse_filter == $warehouse['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($warehouse['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Stock Level</label>
                                <select name="stock" class="form-control">
                                    <option value="">All</option>
                                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary">Filter</button>
                        </div>
                    </form>
                    
                    <?php if (empty($supplies)): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">
                            No supplies found. <a href="?action=add">Add the first item</a>
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Min. Stock</th>
                                        <th>Unit</th>
                                        <th>Warehouse</th>
                                        <th>Expiry</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supplies as $supply): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($supply['item_name']); ?></strong>
                                                <?php if ($supply['supplier']): ?>
                                                    <br><small style="color: #666;">by <?php echo htmlspecialchars($supply['supplier']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; background-color: #f8f9fa;">
                                                    <?php echo ucfirst($supply['category']); ?>
                                                </span>
                                            </td>
                                            <td class="stock-level" 
                                                data-current="<?php echo $supply['current_stock']; ?>" 
                                                data-minimum="<?php echo $supply['minimum_stock']; ?>">
                                                <strong style="<?php 
                                                    if ($supply['current_stock'] == 0) echo 'color: #dc3545;';
                                                    elseif ($supply['current_stock'] <= $supply['minimum_stock']) echo 'color: #856404;';
                                                ?>">
                                                    <?php echo formatNumber($supply['current_stock'], 2); ?>
                                                </strong>
                                                <?php if ($supply['current_stock'] == 0): ?>
                                                    <br><small style="color: #dc3545;">OUT OF STOCK</small>
                                                <?php elseif ($supply['current_stock'] <= $supply['minimum_stock']): ?>
                                                    <br><small style="color: #856404;">LOW STOCK</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatNumber($supply['minimum_stock'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($supply['unit_type']); ?></td>
                                            <td><?php echo htmlspecialchars($supply['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($supply['expiry_date']): ?>
                                                    <?php 
                                                    $expiry = new DateTime($supply['expiry_date']);
                                                    $now = new DateTime();
                                                    $days_to_expiry = $now->diff($expiry)->days;
                                                    $is_expired = $expiry < $now;
                                                    ?>
                                                    <span style="<?php 
                                                        if ($is_expired) echo 'color: #dc3545;';
                                                        elseif ($days_to_expiry <= 30) echo 'color: #856404;';
                                                    ?>">
                                                        <?php echo formatDate($supply['expiry_date']); ?>
                                                        <?php if ($is_expired): ?>
                                                            <br><small>EXPIRED</small>
                                                        <?php elseif ($days_to_expiry <= 30): ?>
                                                            <br><small>EXPIRES SOON</small>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #666;">No expiry</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=adjust_stock&id=<?php echo $supply['id']; ?>" 
                                                   class="btn btn-small btn-primary">Adjust</a>
                                                <a href="?action=edit&id=<?php echo $supply['id']; ?>" 
                                                   class="btn btn-small btn-secondary">Edit</a>
                                                <a href="?action=delete&id=<?php echo $supply['id']; ?>" 
                                                   class="btn btn-small btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
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
                            <?php echo $action === 'add' ? 'Add New Supply Item' : 'Edit Supply Item'; ?>
                        </h3>
                    </div>
                    
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Item Name *</label>
                                    <input type="text" name="item_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($supply_data['item_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Category *</label>
                                    <select name="category" class="form-control" required>
                                        <option value="food" <?php echo ($supply_data['category'] ?? '') === 'food' ? 'selected' : ''; ?>>Food</option>
                                        <option value="water" <?php echo ($supply_data['category'] ?? '') === 'water' ? 'selected' : ''; ?>>Water</option>
                                        <option value="shelter" <?php echo ($supply_data['category'] ?? '') === 'shelter' ? 'selected' : ''; ?>>Shelter</option>
                                        <option value="hygiene" <?php echo ($supply_data['category'] ?? '') === 'hygiene' ? 'selected' : ''; ?>>Hygiene</option>
                                        <option value="medical" <?php echo ($supply_data['category'] ?? '') === 'medical' ? 'selected' : ''; ?>>Medical</option>
                                        <option value="clothing" <?php echo ($supply_data['category'] ?? '') === 'clothing' ? 'selected' : ''; ?>>Clothing</option>
                                        <option value="other" <?php echo ($supply_data['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Unit Type *</label>
                                    <input type="text" name="unit_type" class="form-control" required
                                           value="<?php echo htmlspecialchars($supply_data['unit_type'] ?? ''); ?>"
                                           placeholder="kg, liters, pieces, boxes">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Current Stock *</label>
                                    <input type="number" name="current_stock" class="form-control" required 
                                           step="0.01" min="0"
                                           value="<?php echo $supply_data['current_stock'] ?? 0; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Minimum Stock Level *</label>
                                    <input type="number" name="minimum_stock" class="form-control" required 
                                           step="0.01" min="0"
                                           value="<?php echo $supply_data['minimum_stock'] ?? 0; ?>">
                                    <small style="color: #666;">Alert when stock falls below this level</small>
                                </div>
                            </div>
                            
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Warehouse *</label>
                                    <select name="warehouse_id" class="form-control" required>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <option value="<?php echo $warehouse['id']; ?>" 
                                                    <?php echo ($supply_data['warehouse_id'] ?? 1) == $warehouse['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($warehouse['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" name="expiry_date" class="form-control"
                                           value="<?php echo $supply_data['expiry_date'] ?? ''; ?>">
                                    <small style="color: #666;">Leave blank if no expiry</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Cost per Unit</label>
                                    <input type="number" name="cost_per_unit" class="form-control" 
                                           step="0.01" min="0"
                                           value="<?php echo $supply_data['cost_per_unit'] ?? 0; ?>">
                                    <small style="color: #666;">In USD</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Supplier</label>
                                    <input type="text" name="supplier" class="form-control"
                                           value="<?php echo htmlspecialchars($supply_data['supplier'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($supply_data['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Add Item' : 'Update Item'; ?>
                            </button>
                            <a href="supplies.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'adjust_stock'): ?>
                <!-- Stock Adjustment Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Adjust Stock - <?php echo htmlspecialchars($supply_data['item_name']); ?></h3>
                    </div>
                    
                    <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 4px;">
                        <strong>Current Stock:</strong> <?php echo formatNumber($supply_data['current_stock'], 2); ?> <?php echo htmlspecialchars($supply_data['unit_type']); ?><br>
                        <strong>Minimum Level:</strong> <?php echo formatNumber($supply_data['minimum_stock'], 2); ?> <?php echo htmlspecialchars($supply_data['unit_type']); ?><br>
                        <strong>Warehouse:</strong> <?php echo htmlspecialchars($supply_data['warehouse_name']); ?>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Adjustment Type *</label>
                            <select name="adjustment_type" class="form-control" required>
                                <option value="add">Add Stock (Received/Purchased)</option>
                                <option value="remove">Remove Stock (Damaged/Lost/Used)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="adjustment" class="form-control" required 
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Reason *</label>
                            <input type="text" name="reason" class="form-control" required
                                   placeholder="e.g., New shipment received, Damaged goods removed">
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="submit" class="btn btn-primary">Adjust Stock</button>
                            <a href="supplies.php" class="btn btn-secondary">Cancel</a>
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
