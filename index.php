<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: pages/login.php');
    exit();
}

// Redirect to dashboard
header('Location: pages/dashboard.php');
exit();
?> 