<?php
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/Book.php';

class Inventory
{
    private $pdo;
    private $bookModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
        $this->bookModel = new Book();
    }

    /**
     * Get all books with inventory status
     */
    public function getAllWithStatus()
    {
        try {
            $stmt = $this->pdo->query('
                SELECT b.*, g.name as genre,
                       CASE 
                           WHEN b.stock_count <= 0 THEN "out_of_stock"
                           WHEN b.stock_count <= b.restock_min_level THEN "low_stock"
                           ELSE "ok_stock"
                       END as stock_status
                FROM books b
                LEFT JOIN genres g ON b.genre_id = g.id
                WHERE b.archived = 0
                ORDER BY b.stock_status DESC, b.title
            ');
        } catch (Exception $e) {
            // Fallback if stock_count columns don't exist yet
            $stmt = $this->pdo->query('
                SELECT b.*, g.name as genre, "ok_stock" as stock_status
                FROM books b
                LEFT JOIN genres g ON b.genre_id = g.id
                WHERE b.archived = 0
                ORDER BY b.title
            ');
        }
        
        $books = $stmt->fetchAll();
        
        // Add authors to each book
        foreach ($books as &$book) {
            $book['authors'] = $this->bookModel->getAuthors($book['id']);
        }
        
        return $books;
    }

    /**
     * Get books with low stock
     */
    public function getLowStockBooks()
    {
        try {
            $stmt = $this->pdo->query('
                SELECT b.*, g.name as genre FROM books b
                LEFT JOIN genres g ON b.genre_id = g.id
                WHERE b.archived = 0 AND b.stock_count <= b.restock_min_level
                ORDER BY b.stock_count ASC
            ');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Columns don't exist yet - return empty array
            return [];
        }
    }

    /**
     * Get out of stock books
     */
    public function getOutOfStockBooks()
    {
        try {
            $stmt = $this->pdo->query('
                SELECT b.*, g.name as genre FROM books b
                LEFT JOIN genres g ON b.genre_id = g.id
                WHERE b.archived = 0 AND b.stock_count = 0
                ORDER BY b.title
            ');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Columns don't exist yet - return empty array
            return [];
        }
    }

    /**
     * Restock a book
     */
    public function restockBook($bookId, $quantity, $reason = null, $userId = null)
    {
        try {
            return $this->bookModel->restock($bookId, $quantity, $reason, $userId);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get inventory transaction logs
     */
    public function getTransactionLogs($bookId = null, $limit = 50)
    {
        try {
            $sql = 'SELECT il.*, b.title, b.isbn, u.name as restocked_by 
                    FROM inventory_logs il
                    JOIN books b ON il.book_id = b.id
                    LEFT JOIN users u ON il.created_by = u.id';
            $params = [];
            
            if ($bookId) {
                $sql .= ' WHERE il.book_id = :bid';
                $params['bid'] = $bookId;
            }
            
            $sql .= ' ORDER BY il.created_at DESC LIMIT ' . (int)$limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Table doesn't exist yet - return empty array
            return [];
        }
    }

    /**
     * Get inventory stats
     */
    public function getStats()
    {
        try {
            // Total value of inventory
            $stmt = $this->pdo->query('SELECT SUM(stock_count * price) as total_value FROM books WHERE archived = 0');
            $totalValue = (float)($stmt->fetch()['total_value'] ?? 0);
            
            // Count of different stock statuses
            $stmt = $this->pdo->query('SELECT COUNT(*) as out_of_stock FROM books WHERE archived = 0 AND stock_count = 0');
            $outOfStock = (int)$stmt->fetch()['out_of_stock'];
            
            $stmt = $this->pdo->query('SELECT COUNT(*) as low_stock FROM books WHERE archived = 0 AND stock_count > 0 AND stock_count <= restock_min_level');
            $lowStock = (int)$stmt->fetch()['low_stock'];
            
            $stmt = $this->pdo->query('SELECT COUNT(*) as ok_stock FROM books WHERE archived = 0 AND stock_count > restock_min_level');
            $okStock = (int)$stmt->fetch()['ok_stock'];
            
            $stmt = $this->pdo->query('SELECT COUNT(*) as total_books FROM books WHERE archived = 0');
            $totalBooks = (int)$stmt->fetch()['total_books'];
        } catch (Exception $e) {
            // Columns don't exist yet - return defaults with all books as "ok"
            $stmt = $this->pdo->query('SELECT COUNT(*) as total_books FROM books WHERE archived = 0');
            $totalBooks = (int)$stmt->fetch()['total_books'];
            $outOfStock = 0;
            $lowStock = 0;
            $okStock = $totalBooks;
            $totalValue = 0;
        }
        
        return [
            'total_value' => $totalValue,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'ok_stock' => $okStock,
            'total_books' => $totalBooks
        ];
    }
}