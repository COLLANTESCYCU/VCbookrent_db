<?php
require_once __DIR__ . '/../Database.php';

class ReportController
{
    private $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    public function mostRentedBooks($limit = 10)
    {
        $stmt = $this->pdo->prepare('SELECT id, title, author, times_rented FROM books ORDER BY times_rented DESC LIMIT :l');
        $stmt->bindValue(':l', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function mostActiveUsers($limit = 10)
    {
        $stmt = $this->pdo->prepare('SELECT id, name, total_rentals FROM users ORDER BY total_rentals DESC LIMIT :l');
        $stmt->bindValue(':l', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function rentalTrends($period = 'daily')
    {
        // very simple aggregation
        if ($period === 'daily') {
            $sql = "SELECT DATE(rent_date) as d, COUNT(*) as cnt FROM rentals GROUP BY DATE(rent_date) ORDER BY d DESC LIMIT 30";
        } else {
            $sql = "SELECT DATE_FORMAT(rent_date, '%Y-%m') as d, COUNT(*) as cnt FROM rentals GROUP BY DATE_FORMAT(rent_date, '%Y-%m') ORDER BY d DESC LIMIT 12";
        }
        return $this->pdo->query($sql)->fetchAll();
    }

    public function counts()
    {
        $res = [];
        $res['books'] = (int)$this->pdo->query('SELECT COUNT(*) FROM books WHERE archived = 0')->fetchColumn();
        $res['users'] = (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $res['active_rentals'] = (int)$this->pdo->query('SELECT COUNT(*) FROM rentals WHERE status = "active"')->fetchColumn();
        $res['overdue'] = (int)$this->pdo->query('SELECT COUNT(*) FROM rentals WHERE status = "overdue"')->fetchColumn();
        return $res;
    }

    public function recentRentals($limit = 10)
    {
        $stmt = $this->pdo->prepare('SELECT r.*, b.title, u.name as user_name FROM rentals r JOIN books b ON b.id = r.book_id JOIN users u ON u.id = r.user_id ORDER BY r.rent_date DESC LIMIT :l');
        $stmt->bindValue(':l', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
