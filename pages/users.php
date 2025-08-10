<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requirePermission('manage_users');

$db = new Database();
$user = $auth->getCurrentUser();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitizeInput($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $fullName = sanitizeInput($_POST['full_name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = sanitizeInput($_POST['role'] ?? '');
                
                if (empty($username) || empty($password) || empty($fullName) || empty($email) || empty($role)) {
                    setAlert('danger', 'Please fill in all required fields.');
                } else {
                    try {
                        $userId = $auth->createUser($username, $password, $fullName, $email, $role);
                        if ($userId) {
                            logActivity($db, $user['id'], 'create_user', "Created user: {$username}");
                            setAlert('success', 'User created successfully.');
                        } else {
                            setAlert('danger', 'Failed to create user.');
                        }
                    } catch (Exception $e) {
                        setAlert('danger', 'Username or email already exists.');
                    }
                }
                break;
                
            case 'update_user':
                $userId = (int)($_POST['user_id'] ?? 0);
                $fullName = sanitizeInput($_POST['full_name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = sanitizeInput($_POST['role'] ?? '');
                $status = sanitizeInput($_POST['status'] ?? '');
                
                if ($userId && !empty($fullName) && !empty($email) && !empty($role)) {
                    $updateData = [
                        'full_name' => $fullName,
                        'email' => $email,
                        'role' => $role
                    ];
                    
                    if ($auth->updateUser($userId, $updateData)) {
                        logActivity($db, $user['id'], 'update_user', "Updated user ID: {$userId}");
                        setAlert('success', 'User updated successfully.');
                    } else {
                        setAlert('danger', 'Failed to update user.');
                    }
                } else {
                    setAlert('danger', 'Please fill in all required fields.');
                }
                break;
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get users list
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countSql = "SELECT COUNT(*) as count FROM users";
$totalRecords = $db->fetchOne($countSql)['count'];

$pagination = getPagination($totalRecords, $perPage, $page);

$sql = "SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC LIMIT {$pagination['offset']}, {$perPage}";
$users = $db->fetchAll($sql);

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
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
            
            <div class="nav-item">
                <a href="users.php" class="nav-link active">
                    <i class="bi bi-person-gear"></i>
                    <span>User Management</span>
                </a>
            </div>
            
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
                <h1 class="page-title">User Management</h1>
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
            
            <!-- Add User Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>
                        Add New User
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Administrator</option>
                                    <option value="school_head">School Head</option>
                                    <option value="registrar">Registrar</option>
                                </select>
                            </div>
                            
                            <div class="col-md-9 d-flex align-items-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-person-plus me-1"></i>Add User
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table me-2"></i>
                        Users (<?php echo number_format($totalRecords); ?>)
                    </h5>
                    
                    <div class="d-flex gap-2">
                        <span class="text-muted">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h5 class="mt-3">No users found</h5>
                            <p class="text-muted">Add some users to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userRecord): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($userRecord['username']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($userRecord['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($userRecord['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $userRecord['role'] === 'admin' ? 'danger' : 
                                                        ($userRecord['role'] === 'school_head' ? 'warning' : 'primary'); 
                                                ?>">
                                                    <?php echo ucfirst($userRecord['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $userRecord['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($userRecord['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($userRecord['last_login']): ?>
                                                    <?php echo formatDateTime($userRecord['last_login']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($userRecord['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editUser(<?php echo $userRecord['id']; ?>)" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    
                                                    <?php if ($userRecord['id'] !== $user['id']): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteUser(<?php echo $userRecord['id']; ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
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
                                <nav aria-label="Users pagination">
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
        
        function editUser(userId) {
            // Implement edit user functionality
            console.log('Edit user:', userId);
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                // Implement delete user functionality
                console.log('Delete user:', userId);
            }
        }
    </script>
</body>
</html> 