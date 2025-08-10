<?php
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($username, $password) {
        // Sanitize input
        $username = $this->sanitizeInput($username);
        
        // Get user from database
        $sql = "SELECT id, username, password, role, full_name, status FROM users WHERE username = ? AND status = 'active'";
        $user = $this->db->fetchOne($sql, [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Create session
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['login_time'] = time();
            
            // Log login
            $this->logActivity($user['id'], 'login', 'User logged in successfully');
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $sql = "SELECT id, username, role, full_name, email FROM users WHERE id = ?";
        return $this->db->fetchOne($sql, [$_SESSION['user_id']]);
    }
    
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    public function hasPermission($permission) {
        $role = $_SESSION['role'] ?? '';
        
        $permissions = [
            'admin' => ['view', 'upload', 'edit', 'delete', 'manage_users', 'backup'],
            'school_head' => ['view'],
            'registrar' => ['view', 'upload']
        ];
        
        return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ../pages/login.php');
            exit();
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        
        if (!$this->hasRole($role)) {
            header('Location: ../pages/unauthorized.php');
            exit();
        }
    }
    
    public function requirePermission($permission) {
        $this->requireLogin();
        
        if (!$this->hasPermission($permission)) {
            header('Location: ../pages/unauthorized.php');
            exit();
        }
    }
    
    public function logActivity($userId, $action, $description) {
        $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())";
        $this->db->insert($sql, [$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public function createUser($username, $password, $fullName, $email, $role) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        $sql = "INSERT INTO users (username, password, full_name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";
        return $this->db->insert($sql, [$username, $hashedPassword, $fullName, $email, $role]);
    }
    
    public function updateUser($userId, $data) {
        $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$data['full_name'], $data['email'], $data['role'], $userId]);
    }
    
    public function changePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$hashedPassword, $userId]);
    }
}
?> 