<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = new Database();

// Get dashboard statistics
$stats = [
    'students' => $db->fetchOne("SELECT COUNT(*) as count FROM students")['count'],
    'personnel' => $db->fetchOne("SELECT COUNT(*) as count FROM personnel")['count'],
    'student_records' => $db->fetchOne("SELECT COUNT(*) as count FROM student_records")['count'],
    'school_forms' => $db->fetchOne("SELECT COUNT(*) as count FROM school_forms")['count']
];

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT al.*, u.full_name FROM activity_logs al 
     JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT 10"
);

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
        
        /* Sidebar Styles */
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
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .main-content.sidebar-collapsed {
            margin-left: 70px;
        }
        
        /* Header */
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
        
        /* Content Area */
        .content {
            padding: 2rem;
        }
        
        /* Cards */
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
        
        /* Activity Feed */
        .activity-feed {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 0.9rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            margin: 0;
            font-weight: 500;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        /* Responsive */
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
        
        /* Alert Styles */
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
                <a href="dashboard.php" class="nav-link active">
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
            
            <?php if ($auth->hasPermission('backup')): ?>
            <div class="nav-item">
                <a href="backup.php" class="nav-link">
                    <i class="bi bi-download"></i>
                    <span>Archive & Backup</span>
                </a>
            </div>
            <?php endif; ?>
            
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
                <h1 class="page-title">Dashboard</h1>
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
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--primary-color);">
                            <i class="bi bi-mortarboard"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['students']); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--success-color);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['personnel']); ?></div>
                        <div class="stat-label">Total Personnel</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--warning-color);">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['student_records']); ?></div>
                        <div class="stat-label">Student Records</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--info-color);">
                            <i class="bi bi-file-earmark"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['school_forms']); ?></div>
                        <div class="stat-label">School Forms</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="activity-feed">
                        <h5 class="mb-3">
                            <i class="bi bi-clock-history me-2"></i>
                            Recent Activities
                        </h5>
                        
                        <?php if (empty($recentActivities)): ?>
                            <p class="text-muted">No recent activities</p>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon" style="background: var(--primary-color);">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p class="activity-text">
                                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </p>
                                        <p class="activity-time">
                                            <?php echo formatDateTime($activity['created_at']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="activity-feed">
                        <h5 class="mb-3">
                            <i class="bi bi-lightning me-2"></i>
                            Quick Actions
                        </h5>
                        
                        <div class="d-grid gap-2">
                            <?php if ($auth->hasPermission('upload')): ?>
                                <a href="students.php?action=add" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>
                                    Add Student Record
                                </a>
                                
                                <a href="personnel.php?action=add" class="btn btn-success">
                                    <i class="bi bi-person-plus me-2"></i>
                                    Add Personnel Record
                                </a>
                                
                                <a href="school-forms.php?action=add" class="btn btn-info">
                                    <i class="bi bi-file-earmark-plus me-2"></i>
                                    Upload School Form
                                </a>
                            <?php endif; ?>
                            
                            <a href="students.php" class="btn btn-outline-primary">
                                <i class="bi bi-search me-2"></i>
                                Search Records
                            </a>
                        </div>
                    </div>
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
    </script>
</body>
</html> 