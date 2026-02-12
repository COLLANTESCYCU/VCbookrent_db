<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Models/User.php';

class Auth
{
    private static $sessionKey = 'bookrent_user';
    private static $instance = null;
    private $userModel;

    private function __construct()
    {
        // Session is already started by bootstrap.php
        $this->userModel = new User();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Authenticate user with email and password
     */
    public function login($email, $password)
    {
        try {
            $user = $this->userModel->authenticate($email, $password);
            if (!$user) {
                return false;
            }
            
            $_SESSION[self::$sessionKey] = $user;
            return $user;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get current authenticated user
     */
    public function currentUser()
    {
        return $_SESSION[self::$sessionKey] ?? null;
    }

    /**
     * Check if user is authenticated
     */
    public function isLoggedIn()
    {
        return isset($_SESSION[self::$sessionKey]);
    }

    /**
     * Logout current user
     */
    public function logout()
    {
        unset($_SESSION[self::$sessionKey]);
        session_destroy();
    }

    /**
     * Check if user has required role(s)
     */
    public function hasRole($roles)
    {
        $user = $this->currentUser();
        if (!$user) return false;
        
        $rolesArray = is_array($roles) ? $roles : [$roles];
        return in_array($user['role'], $rolesArray);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is staff
     */
    public function isStaff()
    {
        return $this->hasRole(['admin', 'staff']);
    }

    /**
     * Check if current user is a regular user (not admin/staff)
     */
    public function isUser()
    {
        return $this->hasRole('user');
    }

    /**
     * Check permission - old method for backward compatibility
     */
    public static function checkRole($needed, $user)
    {
        if (!$user) return false;
        return $user['role'] === $needed || $user['role'] === 'admin';
    }

    /**
     * Require authentication - redirect if not logged in
     */
    public static function requireLogin($redirectTo = 'login.php')
    {
        $auth = self::getInstance();
        if (!$auth->isLoggedIn()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * Require specific role - redirect if insufficient permissions
     */
    public static function requireRole($roles, $redirectTo = 'login.php')
    {
        $auth = self::getInstance();
        if (!$auth->hasRole($roles)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * Require staff access (admin or staff role)
     */
    public static function requireStaff($redirectTo = 'login.php')
    {
        $auth = self::getInstance();
        if (!$auth->isStaff()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * Require admin access
     */
    public static function requireAdmin($redirectTo = 'login.php')
    {
        $auth = self::getInstance();
        if (!$auth->isAdmin()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}