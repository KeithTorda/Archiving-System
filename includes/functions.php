<?php
require_once 'config.php';

// File handling functions
function uploadFile($file, $destination, $allowedExtensions = ALLOWED_EXTENSIONS) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameter'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions)];
    }
    
    // Create directory if it doesn't exist
    $dir = dirname($destination);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create directory'];
        }
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $fileExtension;
    $fullPath = $destination . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
    
    return [
        'success' => true,
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'path' => $fullPath
    ];
}

function deleteFile($filePath) {
    if (file_exists($filePath) && is_file($filePath)) {
        return unlink($filePath);
    }
    return false;
}

function downloadFile($filePath, $originalName) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    readfile($filePath);
    return true;
}

// Input sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateLRN($lrn) {
    return preg_match('/^\d{12}$/', $lrn);
}

// Date and time functions
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    return date($format, strtotime($datetime));
}

function getCurrentSchoolYear() {
    $currentYear = date('Y');
    $currentMonth = date('n');
    
    // School year starts in June
    if ($currentMonth >= 6) {
        return $currentYear . '-' . ($currentYear + 1);
    } else {
        return ($currentYear - 1) . '-' . $currentYear;
    }
}

// File size formatting
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Pagination
function getPagination($totalRecords, $recordsPerPage, $currentPage) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    
    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'records_per_page' => $recordsPerPage,
        'offset' => ($currentPage - 1) * $recordsPerPage
    ];
}

// Search and filter
function buildSearchQuery($searchTerm, $searchFields) {
    if (empty($searchTerm)) {
        return '';
    }
    
    $conditions = [];
    foreach ($searchFields as $field) {
        $conditions[] = "$field LIKE ?";
    }
    
    return '(' . implode(' OR ', $conditions) . ')';
}

// Alert messages
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Role-based access control helpers
function canView($auth) {
    return $auth->hasPermission('view');
}

function canUpload($auth) {
    return $auth->hasPermission('upload');
}

function canEdit($auth) {
    return $auth->hasPermission('edit');
}

function canDelete($auth) {
    return $auth->hasPermission('delete');
}

function canManageUsers($auth) {
    return $auth->hasPermission('manage_users');
}

function canBackup($auth) {
    return $auth->hasPermission('backup');
}

// Logging
function logActivity($db, $userId, $action, $description, $recordId = null) {
    $sql = "INSERT INTO activity_logs (user_id, action, description, record_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $db->insert($sql, [$userId, $action, $description, $recordId, $_SERVER['REMOTE_ADDR'] ?? '']);
}
?> 