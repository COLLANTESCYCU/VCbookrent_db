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
        // Required fields: name, email, contact_no, address, password
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception('Missing required fields: name, email, password');
        }

        // Email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email');
        }

        // Unique email
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => $data['email']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email already exists');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (fullname, email, password_hash, contact_no, address) VALUES (:name, :email, :password, :contact, :address)');
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $passwordHash,
            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null
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
        // Allow update name, contact, address, email (with validation)
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email');
        }

        // Check uniqueness if email changed
        if (isset($data['email'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
            $stmt->execute(['email' => $data['email'], 'id'=>$id]);
            if ($stmt->fetch()) {
                throw new Exception('Email already in use');
            }
        }

        $fields = [];
        $params = ['id'=>$id];
        foreach (['name','email','contact','address','role'] as $f) {
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

    public function authenticate($email, $password)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email AND status = "active"');
        $stmt->execute(['email'=>$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        
        return $user;
    }

    public function recordTransaction($userId, $transactionType, $description, $amount = 0, $relatedId = null)
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO transaction_history (user_id, transaction_type, description, amount, related_id) VALUES (:uid, :type, :desc, :amt, :rid)');
            return $stmt->execute(['uid'=>$userId, 'type'=>$transactionType, 'desc'=>$description, 'amt'=>$amount, 'rid'=>$relatedId]);
        } catch (Exception $e) {
            // transaction_history table doesn't exist - silently skip
            return false;
        }
    }

    public function getTransactionHistory($userId)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM transaction_history WHERE user_id = :uid ORDER BY created_at DESC');
            $stmt->execute(['uid'=>$userId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Table doesn't exist yet - return empty array
            return [];
        }
    }

    public function setStatus($id, $status)
    {
        if (!in_array($status, ['active','inactive'])) throw new Exception('Invalid status');
        $stmt = $this->pdo->prepare('UPDATE users SET status = :s, last_status_change_at = NOW() WHERE id = :id');
        return $stmt->execute(['s'=>$status, 'id'=>$id]);
    }

    public function canRent($id)
    {
        // Check unpaid penalties - only block rental if user has unpaid penalties
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM penalties WHERE user_id = :id AND paid = 0');
        $stmt->execute(['id'=>$id]);
        $unpaid = (int)$stmt->fetchColumn();

        return ($unpaid === 0);
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
        $sql = 'SELECT id, fullname, contact_no, email, address FROM users';
        // status and username columns removed from schema
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function getStats($id)
    {
        // user stats (status removed)
        $stmt = $this->pdo->prepare('SELECT created_at, total_rentals, total_late_returns FROM users WHERE id = :id');
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
              'active_rentals' => $active,
              'unpaid_penalties' => $unpaid,
              'total_rentals' => (int)$user['total_rentals'],
              'total_late_returns' => (int)$user['total_late_returns']
        ];
    }
}