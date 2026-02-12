<?php
require_once __DIR__ . '/../Auth.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Database.php';

class AuthController
{
    private $auth;
    private $user;

    public function __construct()
    {
        $this->auth = Auth::getInstance();
        $this->user = new User();
    }

    /**
     * Attempt login
     */
    public function login($email, $password)
    {
        if (empty($email) || empty($password)) {
            throw new Exception('Email and password are required');
        }

        $user = $this->auth->login($email, $password);
        if (!$user) {
            throw new Exception('Invalid email or password');
        }

        return $user;
    }

    /**
     * Logout current user
     */
    public function logout()
    {
        $this->auth->logout();
        return true;
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        return $this->auth->currentUser();
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception('Name, email, and password are required');
        }

        $userId = $this->user->register([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null,
            'role' => 'user' // New users are always 'user' role
        ]);

        return $userId;
    }

    /**
     * Register user (for admin/staff registration)
     */
    public function registerUser($data)
    {
        if (empty($data['name']) || empty($data['email'])) {
            throw new Exception('Name and email are required');
        }

        // Generate default password if not provided
        $password = $data['password'] ?? uniqid('pass_', true);

        $userId = $this->user->register([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $password,
            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null,
            'role' => $data['role'] ?? 'user'
        ]);

        return ['userId' => $userId, 'tempPassword' => $password];
    }

    /**
     * Check if user is authenticated
     */
    public function isLoggedIn()
    {
        return $this->auth->isLoggedIn();
    }

    /**
     * Check if current user has role
     */
    public function hasRole($role)
    {
        return $this->auth->hasRole($role);
    }

    /**
     * Change password for user
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        if (empty($newPassword)) {
            throw new Exception('New password cannot be empty');
        }

        $userRecord = $this->user->findById($userId);
        if (!$userRecord) {
            throw new Exception('User not found');
        }

        if (!password_verify($currentPassword, $userRecord['password_hash'])) {
            throw new Exception('Current password is incorrect');
        }

        // Update password in database
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        return $stmt->execute(['hash' => $hash, 'id' => $userId]);
    }
}