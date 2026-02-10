<?php
require_once __DIR__ . '/../Database.php';

class AuditLog
{
    private $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    public function log($action, $userId = null, $context = null)
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (user_id, action, context, ip) VALUES (:uid, :action, :context, :ip)');
        return $stmt->execute(['uid'=>$userId, 'action'=>$action, 'context'=>$context, 'ip'=>$_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public function recent($limit = 50)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT :l');
        $stmt->bindValue(':l', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
