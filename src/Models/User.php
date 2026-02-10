<?php
require_once __DIR__ . '/../Database.php';

class User
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    // Register user with basic validations
    public function register(array $data)
    {
        // Required fields
        if (empty($data['name']) || empty($data['email']) || empty($data['username']) || empty($data['password'])) {
            throw new Exception('Missing required fields');
        }

        // Email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email');
        }

        // Unique email and username
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email OR username = :username');
        $stmt->execute(['email' => $data['email'], 'username' => $data['username']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email or username already exists');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (name, username, email, password_hash, contact, role) VALUES (:name, :username, :email, :password, :contact, :role)');
        $stmt->execute([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $passwordHash,
            'contact' => $data['contact'] ?? null,
            'role' => $data['role'] ?? 'user'
        ]);

        return $this->pdo->lastInsertId();
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch();
    }

    public function update($id, array $data)
    {
        // Allow update name, contact, email (with validation)
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email');
        }

        // Check uniqueness if email/username changed
        if (isset($data['email']) || isset($data['username'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE (email = :email OR username = :username) AND id != :id');
            $stmt->execute(['email' => $data['email'] ?? '', 'username' => $data['username'] ?? '', 'id'=>$id]);
            if ($stmt->fetch()) {
                throw new Exception('Email or username taken');
            }
        }

        $fields = [];
        $params = ['id'=>$id];
        foreach (['name','email','username','contact','role'] as $f) {
            if (isset($data[$f])) {
                $fields[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }
        if (!$fields) return false;

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function setStatus($id, $status)
    {
        if (!in_array($status, ['active','inactive'])) throw new Exception('Invalid status');
        $stmt = $this->pdo->prepare('UPDATE users SET status = :s, last_status_change_at = NOW() WHERE id = :id');
        return $stmt->execute(['s'=>$status, 'id'=>$id]);
    }

    public function canRent($id)
    {
        $config = require __DIR__ . '/../config.php';
        $max = $config['settings']['max_active_rentals_per_user'];

        $user = $this->findById($id);
        if (!$user || $user['status'] !== 'active') return false;

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rentals WHERE user_id = :id AND status = "active"');
        $stmt->execute(['id'=>$id]);
        $active = (int)$stmt->fetchColumn();

        // Check unpaid penalties
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM penalties WHERE user_id = :id AND paid = 0');
        $stmt->execute(['id'=>$id]);
        $unpaid = (int)$stmt->fetchColumn();

        return ($active < $max) && ($unpaid === 0);
    }

    public function incrementStatsAfterReturn($id, $late)
    {
        $sql = 'UPDATE users SET total_rentals = total_rentals + 1, total_late_returns = total_late_returns + :late WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['late'=>$late ? 1 : 0, 'id'=>$id]);
    }

    // Return list of users, optionally only active ones
    public function all($onlyActive = false)
    {
        $sql = 'SELECT id, name, username, email, status FROM users';
        if ($onlyActive) $sql .= ' WHERE status = "active"';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function getStats($id)
    {
        // user status
        $stmt = $this->pdo->prepare('SELECT status, created_at, total_rentals, total_late_returns FROM users WHERE id = :id');
        $stmt->execute(['id'=>$id]);
        $user = $stmt->fetch();
        if (!$user) return null;

        $stmt = $this->pdo->prepare('SELECT COUNT(*) as active FROM rentals WHERE user_id = :id AND status = "active"');
        $stmt->execute(['id'=>$id]);
        $active = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM penalties WHERE user_id = :id AND paid = 0');
        $stmt->execute(['id'=>$id]);
        $unpaid = (int)$stmt->fetchColumn();

        return [
            'status' => $user['status'],
            'active_rentals' => $active,
            'unpaid_penalties' => $unpaid,
            'total_rentals' => (int)$user['total_rentals'],
            'total_late_returns' => (int)$user['total_late_returns']
        ];
    }
}
