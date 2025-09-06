<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$delivery_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $data = [
        'delivery_date' => $_POST['delivery_date'],
        'recipient_id' => (int)$_POST['recipient_id'],
        'supply_id' => (int)$_POST['supply_id'],
        'quantity_delivered' => (float)$_POST['quantity_delivered'],
        'project_id' => (int)($_POST['project_id'] ?? 0) ?: null,
        'delivery_location' => sanitizeInput($_POST['delivery_location']),
        'receipt_signature' => isset($_POST['receipt_signature']) ? 1 : 0,
        'notes' => sanitizeInput($_POST['notes'])
    ];
    
    try {
        if ($action === 'add') {
            // Check if enough stock is available
            $stmt = $pdo->prepare("SELECT current_stock, item_name FROM supplies WHERE id = ?");
            $stmt->execute([$data['supply_id']]);
            $supply = $stmt->fetch();
            
            if (!$supply) {
                $error = 'Selected supply item not found.';
            } elseif ($supply['current_stock'] < $data['quantity_delivered']) {
                $error = 'Insufficient stock. Available: ' . $supply['current_stock'] . ', Requested: ' . $data['quantity_delivered'];
            } else {
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Insert delivery record
                    $stmt = $pdo->prepare("
                        INSERT INTO aid_deliveries 
                        (delivery_date, recipient_id, supply_id, quantity_delivered, 
                         project_id, delivery_location, delivered_by, receipt_signature, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['delivery_date'], $data['recipient_id'], $data['supply_id'], 
                        $data['quantity_delivered'], $data['project_id'], $data['delivery_location'],
                        $_SESSION['user_id'], $data['receipt_signature'], $data['notes']
                    ]);
                    
                    $delivery_id = $pdo->lastInsertId();
                    
                    // Update stock
                    $stmt = $pdo->prepare("UPDATE supplies SET current_stock = current_stock - ? WHERE id = ?");
                    $stmt->execute([$data['quantity_delivered'], $data['supply_id']]);
                    
                    // Get recipient and supply names for logging
                    $stmt = $pdo->prepare("
                        SELECT ar.full_name as recipient_name, s.item_name 
                        FROM aid_recipients ar, supplies s 
                        WHERE ar.id = ? AND s.id = ?
                    ");
                    $stmt->execute([$data['recipient_id'], $data['supply_id']]);
                    $names = $stmt->fetch();
                    
                    logActivity($_SESSION['user_id'], 'delivery', 'aid_deliveries', $delivery_id, 
                               'Delivered ' . $data['quantity_delivered'] . ' ' . $supply['item_name'] . 
                               ' to ' . $names['recipient_name']);
                    
                    $pdo->commit();
                    $message = 'Aid delivery recorded successfully. Stock updated automatically.';
                    $action = 'list';
                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
            }
        } elseif ($action === 'edit' && $delivery_id) {
            // Get original delivery data
            $stmt = $pdo->prepare("SELECT * FROM aid_deliveries WHERE id = ?");
            $stmt->execute([$delivery_id]);
            $original = $stmt->fetch();
            
            if (!$original) {
                $error = 'Delivery record not found.';
            } else {
                // Calculate stock adjustment needed
                $stock_adjustment = $original['quantity_delivered'] - $data['quantity_delivered'];
                
                // Check if enough stock for the adjustment
                $stmt = $pdo->prepare("SELECT current_stock, item_name FROM supplies WHERE id = ?");
                $stmt->execute([$data['supply_id']]);
                $supply = $stmt->fetch();
                
                if ($stock_adjustment < 0 && $supply['current_stock'] < abs($stock_adjustment)) {
                    $error = 'Insufficient stock for this adjustment.';
                } else {
                    $pdo->beginTransaction();
                    
                    try {
                        // Update delivery record
                        $stmt = $pdo->prepare("
                            UPDATE aid_deliveries SET 
                            delivery_date = ?, recipient_id = ?, supply_id = ?, quantity_delivered = ?, 
                            project_id = ?, delivery_location = ?, receipt_signature = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $data['delivery_date'], $data['recipient_id'], $data['supply_id'], 
                            $data['quantity_delivered'], $data['project_id'], $data['delivery_location'],
                            $data['receipt_signature'], $data['notes'], $delivery_id
                        ]);
                        
                        // Adjust stock if supply changed or quantity changed
                        if ($original['supply_id'] != $data['supply_id']) {
                            // Return stock to original supply
                            $stmt = $pdo->prepare("UPDATE supplies SET current_stock = current_stock + ? WHERE id = ?");
                            $stmt->execute([$original['quantity_delivered'], $original['supply_id']]);
                            
                            // Deduct from new supply
                            $stmt = $pdo->prepare("UPDATE supplies SET current_stock = current_stock - ? WHERE id = ?");
                            $stmt->execute([$data['quantity_delivered'], $data['supply_id']]);
                        } else {
                            // Same supply, just adjust the difference
                            $stmt = $pdo->prepare("UPDATE supplies SET current_stock = current_stock + ? WHERE id = ?");
                            $stmt->execute([$stock_adjustment, $data['supply_id']]);
                        }
                        
                        logActivity($_SESSION['user_id'], 'update', 'aid_deliveries', $delivery_id, 
                                   'Updated delivery record');
                        
                        $pdo->commit();
                        $message = 'Delivery record updated successfully. Stock adjusted automatically.';
                        $action = 'list';
                    } catch (Exception $e) {
                        $pdo->rollback();
                        throw $e;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error saving delivery: ' . $e->getMessage();
    }
}

// Handle delete action
if ($action === 'delete' && $delivery_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM aid_deliveries WHERE id = ?");
        $stmt->execute([$delivery_id]);
        $delivery = $stmt->fetch();
        
        if ($delivery) {
            $pdo->beginTransaction();
            
            try {
                // Return stock
                $stmt = $pdo->prepare("UPDATE supplies SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$delivery['quantity_delivered'], $delivery['supply_id']]);
                
                // Delete delivery
                $stmt = $pdo->prepare("DELETE FROM aid_deliveries WHERE id = ?");
                $stmt->execute([$delivery_id]);
                
                logActivity($_SESSION['user_id'], 'delete', 'aid_deliveries', $delivery_id, 
                           'Deleted delivery record and returned stock');
                
                $pdo->commit();
                $message = 'Delivery record deleted and stock returned.';
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $error = 'Error deleting delivery: ' . $e->getMessage();
    }
    $action = 'list';
}

// Get delivery data for edit
$delivery_data = null;
if ($action === 'edit' && $delivery_id) {
    $stmt = $pdo->prepare("
        SELECT ad.*, ar.full_name as recipient_name, s.item_name, p.project_name 
        FROM aid_deliveries ad 
        LEFT JOIN aid_recipients ar ON ad.recipient_id = ar.id 
        LEFT JOIN supplies s ON ad.supply_id = s.id 
        LEFT JOIN projects p ON ad.project_id = p.id 
        WHERE ad.id = ?
    ");
    $stmt->execute([$delivery_id]);
    $delivery_data = $stmt->fetch();
    if (!$delivery_data) {
        $error = 'Delivery not found.';
        $action = 'list';
    }
}

// Get recipients for dropdown
$stmt = $pdo->query("SELECT id, recipient_id, full_name, location FROM aid_recipients ORDER BY full_name");
$recipients = $stmt->fetchAll();

// Get supplies for dropdown
$stmt = $pdo->query("SELECT id, item_name, current_stock, unit_type FROM supplies WHERE current_stock > 0 ORDER BY item_name");
$supplies = $stmt->fetchAll();

// Get projects for dropdown
$stmt = $pdo->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name");
$projects = $stmt->fetchAll();

// Get deliveries list with search and filters
$search = $_GET['search'] ?? '';
$recipient_filter = $_GET['recipient'] ?? '';
$supply_filter = $_GET['supply'] ?? '';
$project_filter = $_GET['project'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(ar.full_name LIKE ? OR s.item_name LIKE ? OR ad.delivery_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($recipient_filter) {
    $where_conditions[] = "ad.recipient_id = ?";
    $params[] = $recipient_filter;
}

if ($supply_filter) {
    $where_conditions[] = "ad.supply_id = ?";
    $params[] = $supply_filter;
}

if ($project_filter) {
    $where_conditions[] = "ad.project_id = ?";
    $params[] = $project_filter;
}

if ($date_from) {
    $where_conditions[] = "ad.delivery_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "ad.delivery_date <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT ad.*, ar.full_name as recipient_name, ar.recipient_id as recipient_code,
           s.item_name, s.unit_type, p.project_name, u.full_name as delivered_by_name
    FROM aid_deliveries ad 
    LEFT JOIN aid_recipients ar ON ad.recipient_id = ar.id 
    LEFT JOIN supplies s ON ad.supply_id = s.id 
    LEFT JOIN projects p ON ad.project_id = p.id 
    LEFT JOIN users u ON ad.delivered_by = u.id 
    $where_clause 
    ORDER BY ad.delivery_date DESC, ad.created_at DESC
");
$stmt->execute($params);
$deliveries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aid Delivery - <?php echo APP_NAME; ?></title>
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
                <h1>Aid Delivery</h1>
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
                <!-- Deliveries List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Distribution Records (<?php echo count($deliveries); ?>)</h3>
                        <a href="?action=add" class="btn btn-primary" style="float: right;">Record New Delivery</a>
                    </div>
                    
                    <!-- Search and Filters -->
                    <form method="GET" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 10px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Recipient, item, or location..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Supply</label>
                                <select name="supply" class="form-control">
                                    <option value="">All Items</option>
                                    <?php 
                                    $stmt = $pdo->query("SELECT DISTINCT s.id, s.item_name FROM supplies s JOIN aid_deliveries ad ON s.id = ad.supply_id ORDER BY s.item_name");
                                    $delivery_supplies = $stmt->fetchAll();
                                    foreach ($delivery_supplies as $supply): ?>
                                        <option value="<?php echo $supply['id']; ?>" 
                                                <?php echo $supply_filter == $supply['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supply['item_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary">Filter</button>
                        </div>
                    </form>
                    
                    <?php if (empty($deliveries)): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">
                            No deliveries recorded yet. <a href="?action=add">Record the first delivery</a>
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Recipient</th>
                                        <th>Item Delivered</th>
                                        <th>Quantity</th>
                                        <th>Location</th>
                                        <th>Project</th>
                                        <th>Receipt</th>
                                        <th>Delivered By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deliveries as $delivery): ?>
                                        <tr>
                                            <td><?php echo formatDate($delivery['delivery_date']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($delivery['recipient_name']); ?></strong>
                                                <br><small style="color: #666;">ID: <?php echo htmlspecialchars($delivery['recipient_code']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($delivery['item_name']); ?></td>
                                            <td>
                                                <strong><?php echo formatNumber($delivery['quantity_delivered'], 2); ?></strong>
                                                <?php echo htmlspecialchars($delivery['unit_type']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($delivery['delivery_location']); ?></td>
                                            <td>
                                                <?php if ($delivery['project_name']): ?>
                                                    <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; background-color: #e3f2fd;">
                                                        <?php echo htmlspecialchars($delivery['project_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #666;">No project</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($delivery['receipt_signature']): ?>
                                                    <span style="color: #28a745;">✓ Signed</span>
                                                <?php else: ?>
                                                    <span style="color: #dc3545;">✗ No signature</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($delivery['delivered_by_name']); ?></td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $delivery['id']; ?>" 
                                                   class="btn btn-small btn-secondary">Edit</a>
                                                <a href="?action=delete&id=<?php echo $delivery['id']; ?>" 
                                                   class="btn btn-small btn-danger" 
                                                   onclick="return confirm('Are you sure? This will return the items to stock.')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary -->
                        <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 4px;">
                            <strong>Summary:</strong> 
                            <?php 
                            $total_deliveries = count($deliveries);
                            $signed_receipts = count(array_filter($deliveries, function($d) { return $d['receipt_signature']; }));
                            ?>
                            <?php echo $total_deliveries; ?> deliveries, 
                            <?php echo $signed_receipts; ?> with signed receipts 
                            (<?php echo $total_deliveries > 0 ? round(($signed_receipts / $total_deliveries) * 100) : 0; ?>%)
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $action === 'add' ? 'Record New Aid Delivery' : 'Edit Aid Delivery'; ?>
                        </h3>
                    </div>
                    
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Delivery Date *</label>
                                    <input type="date" name="delivery_date" class="form-control" required
                                           value="<?php echo $delivery_data['delivery_date'] ?? date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Recipient *</label>
                                    <select name="recipient_id" class="form-control" required>
                                        <option value="">Select recipient...</option>
                                        <?php foreach ($recipients as $recipient): ?>
                                            <option value="<?php echo $recipient['id']; ?>" 
                                                    <?php echo ($delivery_data['recipient_id'] ?? '') == $recipient['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($recipient['full_name']); ?> 
                                                (ID: <?php echo htmlspecialchars($recipient['recipient_id']); ?>) - 
                                                <?php echo htmlspecialchars($recipient['location']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Supply Item *</label>
                                    <select name="supply_id" class="form-control" required id="supply_select">
                                        <option value="">Select item...</option>
                                        <?php foreach ($supplies as $supply): ?>
                                            <option value="<?php echo $supply['id']; ?>" 
                                                    data-stock="<?php echo $supply['current_stock']; ?>"
                                                    data-unit="<?php echo htmlspecialchars($supply['unit_type']); ?>"
                                                    <?php echo ($delivery_data['supply_id'] ?? '') == $supply['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supply['item_name']); ?> 
                                                (Available: <?php echo formatNumber($supply['current_stock'], 2); ?> <?php echo htmlspecialchars($supply['unit_type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="stock_info" style="color: #666;"></small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Quantity Delivered *</label>
                                    <input type="number" name="quantity_delivered" class="form-control" required 
                                           step="0.01" min="0.01" id="quantity_input"
                                           value="<?php echo $delivery_data['quantity_delivered'] ?? ''; ?>">
                                    <small id="quantity_warning" style="color: #dc3545;"></small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Delivery Location *</label>
                                    <input type="text" name="delivery_location" class="form-control" required
                                           value="<?php echo htmlspecialchars($delivery_data['delivery_location'] ?? ''); ?>"
                                           placeholder="Where was this delivered?">
                                </div>
                            </div>
                            
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Project (Optional)</label>
                                    <select name="project_id" class="form-control">
                                        <option value="">No specific project</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>" 
                                                    <?php echo ($delivery_data['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($project['project_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="receipt_signature" value="1" 
                                               <?php echo ($delivery_data['receipt_signature'] ?? 0) ? 'checked' : ''; ?>>
                                        Receipt Signed by Recipient
                                    </label>
                                    <small style="color: #666; display: block; margin-top: 5px;">
                                        Check this if the recipient signed or provided acknowledgment
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="4"
                                              placeholder="Additional information about this delivery..."><?php echo htmlspecialchars($delivery_data['notes'] ?? ''); ?></textarea>
                                </div>
                                
                                <?php if ($action === 'edit'): ?>
                                    <div style="padding: 15px; background-color: #fff3cd; border-radius: 4px; margin-top: 10px;">
                                        <strong>Note:</strong> Editing this delivery will automatically adjust stock levels.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Record Delivery' : 'Update Delivery'; ?>
                            </button>
                            <a href="aid_delivery.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/performance.js" async></script>
    <script src="../assets/js/script.js" defer></script>
    <script>document.body.style.visibility='visible';</script>
    <script>
        // Stock validation for delivery form
        document.addEventListener('DOMContentLoaded', function() {
            const supplySelect = document.getElementById('supply_select');
            const quantityInput = document.getElementById('quantity_input');
            const stockInfo = document.getElementById('stock_info');
            const quantityWarning = document.getElementById('quantity_warning');
            
            function updateStockInfo() {
                const selectedOption = supplySelect.options[supplySelect.selectedIndex];
                if (selectedOption.value) {
                    const stock = parseFloat(selectedOption.dataset.stock);
                    const unit = selectedOption.dataset.unit;
                    stockInfo.textContent = `Available stock: ${stock} ${unit}`;
                    quantityInput.max = stock;
                    validateQuantity();
                } else {
                    stockInfo.textContent = '';
                    quantityInput.max = '';
                    quantityWarning.textContent = '';
                }
            }
            
            function validateQuantity() {
                const selectedOption = supplySelect.options[supplySelect.selectedIndex];
                if (selectedOption.value && quantityInput.value) {
                    const stock = parseFloat(selectedOption.dataset.stock);
                    const requested = parseFloat(quantityInput.value);
                    
                    if (requested > stock) {
                        quantityWarning.textContent = `Cannot deliver more than available stock (${stock})`;
                        quantityInput.style.borderColor = '#dc3545';
                    } else {
                        quantityWarning.textContent = '';
                        quantityInput.style.borderColor = '#ddd';
                    }
                }
            }
            
            if (supplySelect) {
                supplySelect.addEventListener('change', updateStockInfo);
                updateStockInfo(); // Initialize
            }
            
            if (quantityInput) {
                quantityInput.addEventListener('input', validateQuantity);
            }
        });
    </script>
</body>
</html>
