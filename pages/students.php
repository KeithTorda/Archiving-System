<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = new Database();
$user = $auth->getCurrentUser();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && $auth->hasPermission('upload')) {
        $studentId = sanitizeInput($_POST['student_id'] ?? '');
        $schoolYear = sanitizeInput($_POST['school_year'] ?? '');
        $gradeLevel = sanitizeInput($_POST['grade_level'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $recordType = sanitizeInput($_POST['record_type'] ?? '');
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!validateCSRFToken($csrf_token)) {
            setAlert('danger', 'Invalid request. Please try again.');
        } elseif (empty($studentId) || empty($schoolYear) || empty($gradeLevel) || empty($recordType)) {
            setAlert('danger', 'Please fill in all required fields.');
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            setAlert('danger', 'Please select a valid file to upload.');
        } else {
            // Create upload directory
            $uploadDir = UPLOAD_PATH . 'students/' . $studentId . '/' . $schoolYear . '/';
            
            $uploadResult = uploadFile($_FILES['file'], $uploadDir);
            
            if ($uploadResult['success']) {
                $sql = "INSERT INTO student_records (student_id, school_year, grade_level, section, record_type, file_name, original_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $recordId = $db->insert($sql, [
                    $studentId, $schoolYear, $gradeLevel, $section, $recordType,
                    $uploadResult['filename'], $uploadResult['original_name'],
                    $uploadResult['path'], $uploadResult['size'], $user['id']
                ]);
                
                if ($recordId) {
                    logActivity($db, $user['id'], 'upload', "Uploaded {$recordType} for student ID: {$studentId}", $recordId);
                    setAlert('success', 'Record uploaded successfully.');
                } else {
                    setAlert('danger', 'Failed to save record to database.');
                }
            } else {
                setAlert('danger', $uploadResult['message']);
            }
        }
        
        header('Location: students.php');
        exit();
    }
}

// Handle file download
if (isset($_GET['download']) && $auth->hasPermission('view')) {
    $recordId = (int)($_GET['download'] ?? 0);
    
    $sql = "SELECT sr.*, s.first_name, s.last_name FROM student_records sr 
            JOIN students s ON sr.student_id = s.id WHERE sr.id = ?";
    $record = $db->fetchOne($sql, [$recordId]);
    
    if ($record && file_exists($record['file_path'])) {
        // Log download
        logActivity($db, $user['id'], 'download', "Downloaded {$record['record_type']} for {$record['first_name']} {$record['last_name']}", $recordId);
        
        // Log download activity
        $sql = "INSERT INTO download_logs (user_id, record_type, record_id, ip_address) VALUES (?, 'student', ?, ?)";
        $db->insert($sql, [$user['id'], $recordId, $_SERVER['REMOTE_ADDR'] ?? '']);
        
        downloadFile($record['file_path'], $record['original_name']);
        exit();
    }
}

// Search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$schoolYear = sanitizeInput($_GET['school_year'] ?? '');
$gradeLevel = sanitizeInput($_GET['grade_level'] ?? '');
$recordType = sanitizeInput($_GET['record_type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($schoolYear)) {
    $whereConditions[] = "sr.school_year = ?";
    $params[] = $schoolYear;
}

if (!empty($gradeLevel)) {
    $whereConditions[] = "sr.grade_level = ?";
    $params[] = $gradeLevel;
}

if (!empty($recordType)) {
    $whereConditions[] = "sr.record_type = ?";
    $params[] = $recordType;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) as count FROM student_records sr 
             JOIN students s ON sr.student_id = s.id {$whereClause}";
$totalRecords = $db->fetchOne($countSql, $params)['count'];

$pagination = getPagination($totalRecords, $perPage, $page);

// Get records
$sql = "SELECT sr.*, s.first_name, s.last_name, s.lrn, u.full_name as uploaded_by_name 
        FROM student_records sr 
        JOIN students s ON sr.student_id = s.id 
        JOIN users u ON sr.uploaded_by = u.id 
        {$whereClause} 
        ORDER BY sr.uploaded_at DESC 
        LIMIT {$pagination['offset']}, {$perPage}";

$records = $db->fetchAll($sql, $params);

// Get students for upload form
$students = $db->fetchAll("SELECT id, first_name, last_name, lrn FROM students ORDER BY last_name, first_name");

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Include the same CSS as dashboard.php */
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
                <a href="students.php" class="nav-link active">
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
                <h1 class="page-title">Student Records</h1>
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
            
            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Name or LRN" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="school_year" class="form-label">School Year</label>
                            <select class="form-select" id="school_year" name="school_year">
                                <option value="">All Years</option>
                                <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                    <option value="<?php echo $year . '-' . ($year + 1); ?>" 
                                            <?php echo $schoolYear === $year . '-' . ($year + 1) ? 'selected' : ''; ?>>
                                        <?php echo $year . '-' . ($year + 1); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="grade_level" class="form-label">Grade Level</label>
                            <select class="form-select" id="grade_level" name="grade_level">
                                <option value="">All Grades</option>
                                <option value="Kinder" <?php echo $gradeLevel === 'Kinder' ? 'selected' : ''; ?>>Kinder</option>
                                <option value="Grade 1" <?php echo $gradeLevel === 'Grade 1' ? 'selected' : ''; ?>>Grade 1</option>
                                <option value="Grade 2" <?php echo $gradeLevel === 'Grade 2' ? 'selected' : ''; ?>>Grade 2</option>
                                <option value="Grade 3" <?php echo $gradeLevel === 'Grade 3' ? 'selected' : ''; ?>>Grade 3</option>
                                <option value="Grade 4" <?php echo $gradeLevel === 'Grade 4' ? 'selected' : ''; ?>>Grade 4</option>
                                <option value="Grade 5" <?php echo $gradeLevel === 'Grade 5' ? 'selected' : ''; ?>>Grade 5</option>
                                <option value="Grade 6" <?php echo $gradeLevel === 'Grade 6' ? 'selected' : ''; ?>>Grade 6</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="record_type" class="form-label">Record Type</label>
                            <select class="form-select" id="record_type" name="record_type">
                                <option value="">All Types</option>
                                <option value="report_card" <?php echo $recordType === 'report_card' ? 'selected' : ''; ?>>Report Card</option>
                                <option value="form_137" <?php echo $recordType === 'form_137' ? 'selected' : ''; ?>>Form 137</option>
                                <option value="enrollment_form" <?php echo $recordType === 'enrollment_form' ? 'selected' : ''; ?>>Enrollment Form</option>
                                <option value="other" <?php echo $recordType === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                            <a href="students.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Upload Form -->
            <?php if ($auth->hasPermission('upload')): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-upload me-2"></i>
                        Upload Student Record
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="student_id" class="form-label">Student *</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['lrn'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="upload_school_year" class="form-label">School Year *</label>
                                <select class="form-select" id="upload_school_year" name="school_year" required>
                                    <option value="">Select Year</option>
                                    <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                        <option value="<?php echo $year . '-' . ($year + 1); ?>">
                                            <?php echo $year . '-' . ($year + 1); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="upload_grade_level" class="form-label">Grade Level *</label>
                                <select class="form-select" id="upload_grade_level" name="grade_level" required>
                                    <option value="">Select Grade</option>
                                    <option value="Kinder">Kinder</option>
                                    <option value="Grade 1">Grade 1</option>
                                    <option value="Grade 2">Grade 2</option>
                                    <option value="Grade 3">Grade 3</option>
                                    <option value="Grade 4">Grade 4</option>
                                    <option value="Grade 5">Grade 5</option>
                                    <option value="Grade 6">Grade 6</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section" placeholder="e.g., A, B, C">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="record_type_upload" class="form-label">Record Type *</label>
                                <select class="form-select" id="record_type_upload" name="record_type" required>
                                    <option value="">Select Type</option>
                                    <option value="report_card">Report Card</option>
                                    <option value="form_137">Form 137</option>
                                    <option value="enrollment_form">Enrollment Form</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="file" class="form-label">File *</label>
                                <input type="file" class="form-control" id="file" name="file" required 
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="form-text">Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG (Max 10MB)</div>
                            </div>
                            
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-upload me-1"></i>Upload Record
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Records Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table me-2"></i>
                        Student Records (<?php echo number_format($totalRecords); ?>)
                    </h5>
                    
                    <div class="d-flex gap-2">
                        <span class="text-muted">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h5 class="mt-3">No records found</h5>
                            <p class="text-muted">Try adjusting your search criteria</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>LRN</th>
                                        <th>School Year</th>
                                        <th>Grade</th>
                                        <th>Record Type</th>
                                        <th>File</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['lrn']); ?></td>
                                            <td><?php echo htmlspecialchars($record['school_year']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($record['grade_level']); ?>
                                                <?php if ($record['section']): ?>
                                                    - <?php echo htmlspecialchars($record['section']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo ucwords(str_replace('_', ' ', $record['record_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($record['original_name']); ?>
                                                    <br>
                                                    <span class="text-muted"><?php echo formatFileSize($record['file_size']); ?></span>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['uploaded_by_name']); ?></td>
                                            <td><?php echo formatDateTime($record['uploaded_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?download=<?php echo $record['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    
                                                    <?php if ($auth->hasPermission('delete')): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteRecord(<?php echo $record['id']; ?>)" title="Delete">
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
                                <nav aria-label="Records pagination">
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
        
        function deleteRecord(recordId) {
            if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                // Implement delete functionality
                console.log('Delete record:', recordId);
            }
        }
    </script>
</body>
</html> 