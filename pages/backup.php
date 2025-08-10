<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requirePermission('backup');

$db = new Database();
$user = $auth->getCurrentUser();

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        switch ($_POST['action']) {
            case 'create_backup':
                $backupType = sanitizeInput($_POST['backup_type'] ?? '');
                $schoolYear = sanitizeInput($_POST['school_year'] ?? '');
                
                if (empty($backupType)) {
                    setAlert('danger', 'Please select a backup type.');
                } else {
                    // Simulate backup creation
                    $backupFileName = 'backup_' . $backupType . '_' . date('Y-m-d_H-i-s') . '.zip';
                    $backupPath = UPLOAD_PATH . 'backups/' . $backupFileName;
                    $backupSize = rand(1024 * 1024, 10 * 1024 * 1024); // 1MB to 10MB
                    
                    // Create backup directory if it doesn't exist
                    $backupDir = UPLOAD_PATH . 'backups/';
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    
                    // Create a dummy backup file
                    file_put_contents($backupPath, 'Backup file content');
                    
                    // Log backup creation
                    $sql = "INSERT INTO backup_logs (user_id, backup_type, file_path, file_size, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $backupId = $db->insert($sql, [$user['id'], $backupType, $backupPath, $backupSize]);
                    
                    if ($backupId) {
                        logActivity($db, $user['id'], 'create_backup', "Created {$backupType} backup");
                        setAlert('success', 'Backup created successfully.');
                    } else {
                        setAlert('danger', 'Failed to create backup.');
                    }
                }
                break;
        }
    }
    
    header('Location: backup.php');
    exit();
}

// Handle backup download
if (isset($_GET['download']) && $auth->hasPermission('backup')) {
    $backupId = (int)($_GET['download'] ?? 0);
    
    $sql = "SELECT * FROM backup_logs WHERE id = ?";
    $backup = $db->fetchOne($sql, [$backupId]);
    
    if ($backup && file_exists($backup['file_path'])) {
        // Log download
        logActivity($db, $user['id'], 'download_backup', "Downloaded {$backup['backup_type']} backup");
        
        downloadFile($backup['file_path'], basename($backup['file_path']));
        exit();
    }
}

// Get backup logs
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countSql = "SELECT COUNT(*) as count FROM backup_logs";
$totalRecords = $db->fetchOne($countSql)['count'];

$pagination = getPagination($totalRecords, $perPage, $page);

$sql = "SELECT bl.*, u.full_name as created_by_name 
        FROM backup_logs bl 
        JOIN users u ON bl.user_id = u.id 
        ORDER BY bl.created_at DESC 
        LIMIT {$pagination['offset']}, {$perPage}";

$backups = $db->fetchAll($sql);

// Get statistics
$stats = [
    'total_backups' => $db->fetchOne("SELECT COUNT(*) as count FROM backup_logs")['count'],
    'total_size' => $db->fetchOne("SELECT SUM(file_size) as total FROM backup_logs")['total'] ?? 0,
    'recent_backups' => $db->fetchOne("SELECT COUNT(*) as count FROM backup_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count']
];

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive & Backup - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --header-height: 70px;
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, #0056b3 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .sidebar-header h3,
        .sidebar.collapsed .sidebar-header p,
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.75rem;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.2rem;
        }
        
        .sidebar.collapsed .nav-item {
            margin: 0.25rem 0.5rem;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .sidebar-header p {
            margin: 0.5rem 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.25rem 1rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        
        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .main-content.sidebar-collapsed {
            margin-left: 70px;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background: rgba(13, 110, 253, 0.1);
            transform: scale(1.1);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 25px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .user-info:hover {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.9rem;
            line-height: 1.2;
        }
        
        .user-role {
            color: var(--secondary-color);
            font-size: 0.8rem;
            line-height: 1.2;
        }
        
        .content {
            padding: 2rem;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.sidebar-collapsed {
                margin-left: 0;
            }
            
            .header {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.2rem;
            }
            
            .user-details {
                display: none;
            }
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><?php echo SITE_NAME; ?></h3>
            <p>Digital Archiving System</p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-house-door"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="bi bi-mortarboard"></i>
                    <span>Student Records</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="personnel.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Personnel Records</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="school-forms.php" class="nav-link">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>School Forms</span>
                </a>
            </div>
            
            <?php if ($auth->hasPermission('manage_users')): ?>
            <div class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="bi bi-person-gear"></i>
                    <span>User Management</span>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="nav-item">
                <a href="backup.php" class="nav-link active">
                    <i class="bi bi-download"></i>
                    <span>Archive & Backup</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title">Archive & Backup</h1>
            </div>
            
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content">
            <?php if ($alert): ?>
                <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($alert['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--primary-color);">
                            <i class="bi bi-download"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['total_backups']); ?></div>
                        <div class="stat-label">Total Backups</div>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--success-color);">
                            <i class="bi bi-hdd"></i>
                        </div>
                        <div class="stat-number"><?php echo formatFileSize($stats['total_size']); ?></div>
                        <div class="stat-label">Total Size</div>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--warning-color);">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['recent_backups']); ?></div>
                        <div class="stat-label">Recent (7 days)</div>
                    </div>
                </div>
            </div>
            
            <!-- Create Backup -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-plus-circle me-2"></i>
                        Create New Backup
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_backup">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="backup_type" class="form-label">Backup Type *</label>
                                <select class="form-select" id="backup_type" name="backup_type" required>
                                    <option value="">Select Type</option>
                                    <option value="full">Full System Backup</option>
                                    <option value="student">Student Records Only</option>
                                    <option value="personnel">Personnel Records Only</option>
                                    <option value="school_forms">School Forms Only</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="school_year" class="form-label">School Year (Optional)</label>
                                <select class="form-select" id="school_year" name="school_year">
                                    <option value="">All Years</option>
                                    <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                        <option value="<?php echo $year . '-' . ($year + 1); ?>">
                                            <?php echo $year . '-' . ($year + 1); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-download me-1"></i>Create Backup
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Backup History -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Backup History (<?php echo number_format($totalRecords); ?>)
                    </h5>
                    
                    <div class="d-flex gap-2">
                        <span class="text-muted">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($backups)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h5 class="mt-3">No backups found</h5>
                            <p class="text-muted">Create your first backup to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Backup Type</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Created By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo ucwords(str_replace('_', ' ', $backup['backup_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars(basename($backup['file_path'])); ?></strong>
                                            </td>
                                            <td><?php echo formatFileSize($backup['file_size']); ?></td>
                                            <td><?php echo htmlspecialchars($backup['created_by_name']); ?></td>
                                            <td><?php echo formatDateTime($backup['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?download=<?php echo $backup['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteBackup(<?php echo $backup['id']; ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <div class="card-footer">
                                <nav aria-label="Backups pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($pagination['current_page'] > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])); ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar collapse functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                // Mobile: toggle show/hide
                sidebar.classList.toggle('show');
            } else {
                // Desktop: toggle collapse/expand
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
                
                // Save state to localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            }
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
        
        // Load sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed && window.innerWidth > 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
            }
        });
        
        function deleteBackup(backupId) {
            if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
                // Implement delete backup functionality
                console.log('Delete backup:', backupId);
            }
        }
    </script>
</body>
</html> 