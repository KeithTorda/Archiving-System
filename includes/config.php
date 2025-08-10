<?php
// Database configuration - support both local and Heroku environments
if (isset($_ENV['DATABASE_URL'])) {
    // Heroku database configuration
    $dbopts = parse_url($_ENV['DATABASE_URL']);
    define('DB_HOST', $dbopts["host"]);
    define('DB_USER', $dbopts["user"]);
    define('DB_PASS', $dbopts["pass"]);
    define('DB_NAME', ltrim($dbopts["path"], '/'));
} else {
    // Local development configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'atok_archiving_system');
}

// Application configuration
define('SITE_NAME', 'Atok Elementary School');
if (isset($_ENV['HEROKU_APP_NAME'])) {
    define('SITE_URL', 'https://' . $_ENV['HEROKU_APP_NAME'] . '.herokuapp.com');
    define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
} else {
    define('SITE_URL', 'http://localhost/archivingsystem');
    define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/archivingsystem/uploads/');
}
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Security configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('BCRYPT_COST', 12);

// Allowed file types
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Error reporting (disable in production)
if (isset($_ENV['HEROKU_APP_NAME'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
?> 