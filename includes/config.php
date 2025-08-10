<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'atok_archiving_system');

// Application configuration
define('SITE_NAME', 'Atok Elementary School');
define('SITE_URL', 'http://localhost/archivingsystem');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/archivingsystem/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Security configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('BCRYPT_COST', 12);

// Allowed file types
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?> 