<?php
require_once __DIR__ . '/../Database.php';

class Report
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    /**
     * Get rental trends over a period (days)
     */
    public function getRentalTrends($days = 30)
    {
        $stmt = $this->pdo->prepare('
            SELECT DATE(rent_date) as rental_date, COUNT(*) as rental_count
            FROM rentals
            WHERE rent_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(rent_date)
            ORDER BY rental_date DESC
        ');
        $stmt->execute(['days' => (int)$days]);
        return $stmt->fetchAll();
    }

    /**
     * Get most popular books (most rented)
     */
    public function getPopularBooks($limit = 10)
    {
        $stmt = $this->pdo->prepare('
            SELECT b.*, g.name as genre, COUNT(r.id) as rental_count
            FROM books b
            LEFT JOIN genres g ON b.genre_id = g.id
            LEFT JOIN rentals r ON b.id = r.book_id
            WHERE b.archived = 0
            GROUP BY b.id
            ORDER BY rental_count DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get active rentals count
     */
    public function getActiveRentalsCount()
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM rentals WHERE status = "active"');
        return (int)$stmt->fetch()['count'];
    }

    /**
     * Get overdue rentals count
     */
    public function getOverdueRentalsCount()
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM rentals WHERE due_date < NOW() AND status = "active"');
        return (int)$stmt->fetch()['count'];
    }

    /**
     * Get total revenue from penalties
     */
    public function getTotalPenaltyRevenue($days = 30)
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT SUM(amount) as total FROM penalties
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ');
            $stmt->execute(['days' => (int)$days]);
            $result = $stmt->fetch();
            return (float)($result['total'] ?? 0);
        } catch (Exception $e) {
            // Table doesn't exist yet - return 0
            return 0;
        }
    }

    /**
     * Get rental revenue (if tracking book prices)
     */
    public function getRentalRevenue($days = 30)
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT SUM(b.price) as total FROM rentals r
                JOIN books b ON r.book_id = b.id
                WHERE r.rent_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ');
            $stmt->execute(['days' => (int)$days]);
            $result = $stmt->fetch();
            return (float)($result['total'] ?? 0);
        } catch (Exception $e) {
            // Table doesn't exist yet or price column missing - return 0
            return 0;
        }
    }

    /**
     * Get user activity metrics
     */
    public function getUserMetrics()
    {
        try {
            $stmt = $this->pdo->query('SELECT COUNT(*) as total_users FROM users WHERE status = "active"');
            $totalUsers = (int)$stmt->fetch()['total_users'];
            
            $stmt = $this->pdo->query('SELECT COUNT(*) as active_users FROM users WHERE status = "active" AND id IN (SELECT DISTINCT user_id FROM rentals WHERE status = "active")');
            $activeUsers = (int)$stmt->fetch()['active_users'];
            
            $stmt = $this->pdo->query('SELECT AVG(rental_count) as avg_rentals FROM (SELECT COUNT(*) as rental_count FROM rentals GROUP BY user_id) as user_rentals');
            $avgRentals = (float)($stmt->fetch()['avg_rentals'] ?? 0);
            
            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'avg_rentals_per_user' => round($avgRentals, 2)
            ];
        } catch (Exception $e) {
            // return default metrics if query fails
            return [
                'total_users' => 0,
                'active_users' => 0,
                'avg_rentals_per_user' => 0
            ];
        }
    }

    /**
     * Get overdue rental details
     */
    public function getOverdueRentalsDetails($limit = 50)
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT r.*, b.title, b.isbn, u.name as user_name, u.email,
                       DATEDIFF(NOW(), r.due_date) as days_overdue,
                       p.amount as penalty_amount
                FROM rentals r
                JOIN books b ON r.book_id = b.id
                JOIN users u ON r.user_id = u.id
                LEFT JOIN penalties p ON r.penalty_id = p.id
                WHERE r.status IN ("active", "overdue") AND r.due_date < NOW()
                ORDER BY r.due_date ASC
                LIMIT :limit
            ');
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Return empty array if query fails
            return [];
        }
    }

    /**
     * Get penalty statistics
     */
    public function getPenaltyStats()
    {
        try {
            $stmt = $this->pdo->query('
                SELECT COUNT(*) as total_penalties, COUNT(CASE WHEN paid = 1 THEN 1 END) as paid_penalties,
                       COUNT(CASE WHEN paid = 0 THEN 1 END) as unpaid_penalties,
                       SUM(CASE WHEN paid = 0 THEN amount ELSE 0 END) as unpaid_total,
                       AVG(amount) as avg_penalty_amount
                FROM penalties
            ');
            $result = $stmt->fetch();
        } catch (Exception $e) {
            // Table doesn't exist yet - return defaults
            $result = [
                'total_penalties' => 0,
                'paid_penalties' => 0,
                'unpaid_penalties' => 0,
                'unpaid_total' => 0,
                'avg_penalty_amount' => 0
            ];
        }
        
        return [
            'total_penalties' => (int)($result['total_penalties'] ?? 0),
            'paid_penalties' => (int)($result['paid_penalties'] ?? 0),
            'unpaid_penalties' => (int)($result['unpaid_penalties'] ?? 0),
            'unpaid_total' => (float)($result['unpaid_total'] ?? 0),
            'avg_amount' => (float)($result['avg_penalty_amount'] ?? 0)
        ];
    }

    /**
     * Get genre statistics
     */
    public function getGenreStats()
    {
        try {
            $stmt = $this->pdo->query('
                SELECT g.id, g.name, COUNT(b.id) as book_count, COUNT(r.id) as rental_count
                FROM genres g
                LEFT JOIN books b ON g.id = b.genre_id
                LEFT JOIN rentals r ON b.id = r.book_id
                GROUP BY g.id, g.name
                ORDER BY rental_count DESC
            ');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Return empty array if query fails
            return [];
        }
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary()
    {
        return [
            'active_rentals' => $this->getActiveRentalsCount(),
            'overdue_rentals' => $this->getOverdueRentalsCount(),
            'user_metrics' => $this->getUserMetrics(),
            'penalty_stats' => $this->getPenaltyStats(),
            'penalty_revenue' => $this->getTotalPenaltyRevenue(30),
            'rental_revenue' => $this->getRentalRevenue(30)
        ];
    }
}