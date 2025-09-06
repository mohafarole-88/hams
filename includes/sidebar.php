<?php
// Sidebar navigation with role-based access control
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <h2>HAMS</h2>
        <p style="font-size: 12px; opacity: 0.8;">Somalia Relief</p>
    </div>
    <ul class="sidebar-menu">
        <?php 
        // Determine base path based on current directory
        $base_path = '';
        $current_dir = dirname($_SERVER['PHP_SELF']);
        if (strpos($current_dir, '/program') !== false || strpos($current_dir, '/admin') !== false) {
            $base_path = '../';
        }
        ?>
        
        <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="icon">📊</i> Dashboard
        </a></li>
        
        <?php if (canAccess('recipients')): ?>
        <li><a href="<?php echo $base_path; ?>program/aid_recipients.php" class="<?php echo $current_page === 'aid_recipients.php' ? 'active' : ''; ?>">
            <i class="icon">👥</i> Aid Recipients
        </a></li>
        <?php endif; ?>
        
        <?php if (canAccess('deliveries')): ?>
        <li><a href="<?php echo $base_path; ?>program/aid_delivery.php" class="<?php echo $current_page === 'aid_delivery.php' ? 'active' : ''; ?>">
            <i class="icon">🚚</i> Aid Delivery
        </a></li>
        <?php endif; ?>
        
        <?php if (canAccess('supplies')): ?>
        <li><a href="<?php echo $base_path; ?>program/supplies.php" class="<?php echo $current_page === 'supplies.php' ? 'active' : ''; ?>">
            <i class="icon">📦</i> Supplies
        </a></li>
        <?php endif; ?>
        
        <?php if (canAccess('projects')): ?>
        <li><a href="<?php echo $base_path; ?>program/projects.php" class="<?php echo $current_page === 'projects.php' ? 'active' : ''; ?>">
            <i class="icon">📋</i> Projects
        </a></li>
        <?php endif; ?>
        
        <?php if (canAccess('reports')): ?>
        <li><a href="<?php echo $base_path; ?>program/reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
            <i class="icon">📈</i> Reports
        </a></li>
        <?php endif; ?>
        
        <?php if (canAccess('activity_records')): ?>
        <li><a href="<?php echo $base_path; ?>admin/activity.php" class="<?php echo $current_page === 'activity.php' ? 'active' : ''; ?>">
            <i class="icon">📝</i> Activity Records
        </a></li>
        <?php endif; ?>
        
        <?php if (canAccess('users')): ?>
        <li><a href="<?php echo $base_path; ?>admin/users.php" class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
            <i class="icon">👤</i> User Management
        </a></li>
        <?php endif; ?>
    </ul>
    
</nav>
