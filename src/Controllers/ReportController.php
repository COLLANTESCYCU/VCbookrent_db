<?php
require_once __DIR__ . '/../Models/Report.php';

class ReportController
{
    private $report;

    public function __construct()
    {
        $this->report = new Report();
    }

    public function getDashboard()
    {
        return $this->report->getDashboardSummary();
    }

    public function getRentalTrends($days = 30)
    {
        return $this->report->getRentalTrends($days);
    }

    public function getPopularBooks($limit = 10)
    {
        return $this->report->getPopularBooks($limit);
    }

    public function getOverdueRentals($limit = 50)
    {
        return $this->report->getOverdueRentalsDetails($limit);
    }

    public function getPenaltyStats()
    {
        return $this->report->getPenaltyStats();
    }

    public function getGenreStats()
    {
        return $this->report->getGenreStats();
    }

    public function getUserMetrics()
    {
        return $this->report->getUserMetrics();
    }

    public function getActiveRentalsCount()
    {
        return $this->report->getActiveRentalsCount();
    }

    public function getOverdueRentalsCount()
    {
        return $this->report->getOverdueRentalsCount();
    }

    // Backward compatibility
    public function mostRentedBooks($limit = 10)
    {
        return $this->report->getPopularBooks($limit);
    }

    public function mostActiveUsers($limit = 10)
    {
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare('SELECT id, fullname, total_rentals FROM users ORDER BY total_rentals DESC LIMIT :l');
        $stmt->bindValue(':l', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function rentalTrends($period = 'daily')
    {
        if ($period === 'daily') {
            $trends = $this->report->getRentalTrends(30);
            // Transform to expected format with 'd' and 'cnt' keys
            return array_map(function($t) {
                return ['d' => $t['rental_date'], 'cnt' => $t['rental_count']];
            }, $trends);
        } else {
            $pdo = Database::getInstance()->pdo();
            return $pdo->query("SELECT DATE_FORMAT(rent_date, '%Y-%m') as d, COUNT(*) as cnt FROM rentals GROUP BY DATE_FORMAT(rent_date, '%Y-%m') ORDER BY d DESC LIMIT 12")->fetchAll();
        }
    }

    public function counts()
    {
        $res = [];
        $pdo = Database::getInstance()->pdo();
        $res['books'] = (int)$pdo->query('SELECT COUNT(*) FROM books WHERE archived = 0')->fetchColumn();
        $res['users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $res['active_rentals'] = $this->report->getActiveRentalsCount();
        $res['overdue'] = $this->report->getOverdueRentalsCount();
        return $res;
    }

    public function recentRentals($limit = 10)
    {
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare('SELECT r.*, b.title, u.name as user_name FROM rentals r JOIN books b ON b.id = r.book_id JOIN users u ON u.id = r.user_id ORDER BY r.rent_date DESC LIMIT :l');
        $stmt = $pdo->prepare('SELECT r.*, b.title, u.fullname as user_name FROM rentals r JOIN books b ON b.id = r.book_id JOIN users u ON u.id = r.user_id ORDER BY r.rent_date DESC LIMIT :l');
        $stmt->bindValue(':l', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}