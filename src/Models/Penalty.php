<?php
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/AuditLog.php';

class Penalty
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    public function createForRental($rentalId, $userId, $daysOverdue)
    {
        if ($daysOverdue <= 0) return null;

        // Calculate penalty: ₱10 per day
        $amount = 10.00 * $daysOverdue;

        $stmt = $this->pdo->prepare('INSERT INTO penalties (rental_id, user_id, amount, days_overdue) VALUES (:rid, :uid, :amt, :d)');
        $stmt->execute(['rid'=>$rentalId, 'uid'=>$userId, 'amt'=>$amount, 'd'=>$daysOverdue]);

        $id = $this->pdo->lastInsertId();
        // audit
        $al = new AuditLog();
        $al->log('Penalty created: id=' . $id, $userId, json_encode(['rental'=>$rentalId, 'days'=>$daysOverdue, 'amount'=>$amount]));
        return $id;
    }

    public function markPaid($penaltyId)
    {
        $stmt = $this->pdo->prepare('UPDATE penalties SET paid = 1 WHERE id = :id');
        $ok = $stmt->execute(['id'=>$penaltyId]);
        if ($ok) {
            // audit
            $stmt = $this->pdo->prepare('SELECT user_id, rental_id, amount FROM penalties WHERE id = :id');
            $stmt->execute(['id'=>$penaltyId]);
            $p = $stmt->fetch();
            $al = new AuditLog();
            $al->log('Penalty paid: id=' . $penaltyId, $p['user_id'] ?? null, json_encode($p));
        }
        return $ok;
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare('SELECT p.*, u.name as user_name, b.title FROM penalties p JOIN users u ON u.id = p.user_id JOIN books b ON b.id = p.rental_id OR b.id = (SELECT book_id FROM rentals WHERE id = p.rental_id LIMIT 1) WHERE p.id = :id');
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch();
    }

    public function listForUser($userId)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM penalties WHERE user_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id'=>$userId]);
        return $stmt->fetchAll();
    }

    public function listUnpaid()
    {
        $stmt = $this->pdo->query('SELECT p.*, u.name as user_name FROM penalties p JOIN users u ON u.id = p.user_id WHERE p.paid = 0 ORDER BY p.created_at DESC');
        return $stmt->fetchAll();
    }
}