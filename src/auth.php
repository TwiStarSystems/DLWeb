<?php
/**
 * Authentication & Authorization System
 * Role-Based Access Control (RBAC) for Containerize-Webserver
 */

require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication Class
 * Handles user login, logout, registration, and session management
 */
class Auth {
    private $db;
    private $user = null;
    private $permissions = [];
    
    public function __construct() {
        $this->db = getDB();
        $this->loadUserFromSession();
    }
    
    /**
     * Load user from session if logged in
     */
    private function loadUserFromSession() {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name, r.display_name as role_display_name, r.level as role_level
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_active = TRUE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $this->user = $stmt->fetch();
            
            if ($this->user) {
                $this->loadPermissions();
            } else {
                // User not found or inactive, clear session
                $this->logout();
            }
        } elseif (isset($_COOKIE['remember_token'])) {
            // Try to authenticate via remember token
            $this->authenticateByToken($_COOKIE['remember_token']);
        }
    }
    
    /**
     * Load user permissions from database
     */
    private function loadPermissions() {
        if (!$this->user) return;
        
        $stmt = $this->db->prepare("
            SELECT p.name
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$this->user['role_id']]);
        $this->permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Authenticate user by username/email and password
     */
    public function login($usernameOrEmail, $password, $remember = false) {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name, r.level as role_level
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE (u.username = ? OR u.email = ?) AND u.is_active = TRUE
        ");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->user = $user;
            $_SESSION['user_id'] = $user['id'];
            
            // Update last login
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Handle remember me
            if ($remember) {
                $this->createRememberToken($user['id']);
            }
            
            $this->loadPermissions();
            return ['success' => true, 'user' => $this->getSafeUserData()];
        }
        
        return ['success' => false, 'message' => 'Invalid username/email or password'];
    }
    
    /**
     * Create a remember me token
     */
    private function createRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Delete old tokens for this user
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Insert new token
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $hashedToken,
            $expires,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Set cookie
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
    }
    
    /**
     * Authenticate by remember token
     */
    private function authenticateByToken($token) {
        $hashedToken = hash('sha256', $token);
        
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name, r.level as role_level
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN user_sessions s ON u.id = s.user_id
            WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute([$hashedToken]);
        $user = $stmt->fetch();
        
        if ($user) {
            $this->user = $user;
            $_SESSION['user_id'] = $user['id'];
            $this->loadPermissions();
            return true;
        }
        
        // Invalid token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        return false;
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password, $displayName = null) {
        // Validate input
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Username must be between 3 and 50 characters'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        // Check if username or email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        // Get default role (subscriber)
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'subscriber'");
        $stmt->execute();
        $role = $stmt->fetch();
        $roleId = $role ? $role['id'] : 2; // Default to ID 2 (subscriber)
        
        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $displayName = $displayName ?: $username;
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, display_name, role_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $passwordHash, $displayName, $roleId]);
            
            return ['success' => true, 'message' => 'Registration successful! You can now log in.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Logout the current user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Delete session tokens
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $this->user = null;
        $this->permissions = [];
        
        // Clear session
        $_SESSION = [];
        session_destroy();
        
        // Clear remember cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->user !== null;
    }
    
    /**
     * Check if user is a guest (not logged in)
     */
    public function isGuest() {
        return $this->user === null;
    }
    
    /**
     * Get current user data (safe version without password)
     */
    public function getUser() {
        return $this->getSafeUserData();
    }
    
    /**
     * Get safe user data (without sensitive fields)
     */
    private function getSafeUserData() {
        if (!$this->user) return null;
        
        return [
            'id' => $this->user['id'],
            'username' => $this->user['username'],
            'email' => $this->user['email'],
            'display_name' => $this->user['display_name'],
            'role_id' => $this->user['role_id'],
            'role_name' => $this->user['role_name'],
            'role_display_name' => $this->user['role_display_name'],
            'role_level' => $this->user['role_level'],
            'created_at' => $this->user['created_at'],
            'last_login' => $this->user['last_login']
        ];
    }
    
    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permission) {
        return in_array($permission, $this->permissions);
    }
    
    /**
     * Check if user has any of the specified permissions
     */
    public function hasAnyPermission(array $permissions) {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all of the specified permissions
     */
    public function hasAllPermissions(array $permissions) {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if user has a specific role
     */
    public function hasRole($roleName) {
        return $this->user && $this->user['role_name'] === $roleName;
    }
    
    /**
     * Check if user has a minimum role level
     */
    public function hasMinimumRole($minLevel) {
        return $this->user && $this->user['role_level'] >= $minLevel;
    }
    
    /**
     * Get all user permissions
     */
    public function getPermissions() {
        return $this->permissions;
    }
    
    /**
     * Check if user can perform action on a page
     */
    public function canEditPage($pageAuthorId) {
        if (!$this->isLoggedIn()) return false;
        
        // Can edit all pages
        if ($this->hasPermission('edit_all_pages')) return true;
        
        // Can edit own pages
        if ($this->hasPermission('edit_own_pages') && $pageAuthorId == $this->user['id']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can delete a page
     */
    public function canDeletePage($pageAuthorId) {
        if (!$this->isLoggedIn()) return false;
        
        // Can delete all pages
        if ($this->hasPermission('delete_all_pages')) return true;
        
        // Can delete own pages
        if ($this->hasPermission('delete_own_pages') && $pageAuthorId == $this->user['id']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Require user to be logged in
     */
    public function requireLogin($redirectTo = '/login.php') {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Require user to have a specific permission
     */
    public function requirePermission($permission, $redirectTo = '/403.php') {
        $this->requireLogin();
        
        if (!$this->hasPermission($permission)) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Require user to have minimum role level
     */
    public function requireMinimumRole($minLevel, $redirectTo = '/403.php') {
        $this->requireLogin();
        
        if (!$this->hasMinimumRole($minLevel)) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.email, u.display_name, u.role_id, 
                   u.is_active, u.created_at, u.last_login,
                   r.name as role_name, r.display_name as role_display_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Get all roles
     */
    public function getRoles() {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY level ASC");
        return $stmt->fetchAll();
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAllUsers() {
        if (!$this->hasPermission('manage_users')) {
            return [];
        }
        
        $stmt = $this->db->query("
            SELECT u.id, u.username, u.email, u.display_name, u.role_id,
                   u.is_active, u.created_at, u.last_login,
                   r.name as role_name, r.display_name as role_display_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Update user role (admin only)
     */
    public function updateUserRole($userId, $roleId) {
        if (!$this->hasPermission('manage_users')) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        // Prevent changing own role
        if ($userId == $this->user['id']) {
            return ['success' => false, 'message' => 'You cannot change your own role'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->execute([$roleId, $userId]);
            return ['success' => true, 'message' => 'User role updated successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to update role: ' . $e->getMessage()];
        }
    }
    
    /**
     * Toggle user active status (admin only)
     */
    public function toggleUserStatus($userId) {
        if (!$this->hasPermission('manage_users')) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        // Prevent deactivating self
        if ($userId == $this->user['id']) {
            return ['success' => false, 'message' => 'You cannot deactivate your own account'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$userId]);
            return ['success' => true, 'message' => 'User status updated successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Global auth instance
$auth = new Auth();

/**
 * Helper functions for templates
 */
function auth() {
    global $auth;
    return $auth;
}

function isLoggedIn() {
    return auth()->isLoggedIn();
}

function currentUser() {
    return auth()->getUser();
}

function hasPermission($permission) {
    return auth()->hasPermission($permission);
}

function hasRole($role) {
    return auth()->hasRole($role);
}

function csrfToken() {
    return auth()->generateCSRFToken();
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}
?>
