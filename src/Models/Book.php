<?php
require_once __DIR__ . '/../Database.php';

class Book
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    public function add(array $data)
    {
        if (empty($data['isbn']) || empty($data['title']) || empty($data['authors'])) {
            throw new Exception('Missing required book fields');
        }
        if (empty($data['price']) || (float)$data['price'] < 0) throw new Exception('Price must be >= 0');

        // Unique ISBN
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM books WHERE isbn = :isbn');
        $stmt->execute(['isbn'=>$data['isbn']]);
        if ($stmt->fetchColumn() > 0) throw new Exception('ISBN must be unique');

        $price = (float)$data['price'];
        $stock = isset($data['stock_count']) ? (int)$data['stock_count'] : 0;
        $stmt = $this->pdo->prepare('INSERT INTO books (isbn, title, author, genre_id, total_copies, available_copies, stock_count, price, image, restock_min_level) VALUES (:isbn, :title, :author, :genre, :stock, :stock, :stock, :price, :image, :min_level)');
        $stmt->execute([
            'isbn'=>$data['isbn'],
            'title'=>$data['title'],
            'author'=>'', // placeholder, actual authors in book_authors table
            'genre'=>isset($data['genre_id']) && !empty($data['genre_id']) ? (int)$data['genre_id'] : null,
            'stock' => $stock,
            'price' => $price,
            'image' => $data['image'] ?? null,
            'min_level' => (int)($data['restock_min_level'] ?? 3)
        ]);
        
        $bookId = $this->pdo->lastInsertId();
        
        // Add authors to book_authors table
        if (!empty($data['authors'])) {
            $authors = is_array($data['authors']) ? $data['authors'] : [$data['authors']];
            foreach ($authors as $idx => $author) {
                $this->addAuthor($bookId, $author, $idx);
            }
        }
        
        return $bookId;
    }

    public function update($id, array $data)
    {
        $fields = [];
        $params = ['id'=>$id];
        
        // Only allow specified fields to be updated
        $allowedFields = ['title', 'isbn', 'genre_id', 'price', 'image'];
        foreach ($allowedFields as $f) {
            if (isset($data[$f])) {
                // ISBN uniqueness check (excluding current book)
                if ($f === 'isbn') {
                    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM books WHERE isbn = :isbn AND id != :id');
                    $stmt->execute(['isbn'=>$data[$f], 'id'=>$id]);
                    if ($stmt->fetchColumn() > 0) throw new Exception('ISBN must be unique');
                }
                
                $fields[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }
        
        // Validate price
        if (isset($data['price']) && (float)$data['price'] < 0) {
            throw new Exception('Price must be >= 0');
        }
        
        // Update authors if provided
        if (isset($data['authors'])) {
            $this->updateAuthors($id, $data['authors']);
        }

        if (!$fields) return false;
        $sql = 'UPDATE books SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    private function addAuthor($bookId, $authorName, $order = 0)
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO book_authors (book_id, author_name, author_order) VALUES (:bid, :name, :order)');
        return $stmt->execute(['bid'=>$bookId, 'name'=>$authorName, 'order'=>$order]);
    }

    private function updateAuthors($bookId, $authors)
    {
        // Clear existing authors
        $stmt = $this->pdo->prepare('DELETE FROM book_authors WHERE book_id = :bid');
        $stmt->execute(['bid'=>$bookId]);
        
        // Add new authors
        $authorsArray = is_array($authors) ? $authors : [$authors];
        foreach ($authorsArray as $idx => $author) {
            $this->addAuthor($bookId, $author, $idx);
        }
    }

    public function getAuthors($bookId)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT author_name FROM book_authors WHERE book_id = :bid ORDER BY author_order');
            $stmt->execute(['bid'=>$bookId]);
            return array_column($stmt->fetchAll(), 'author_name');
        } catch (Exception $e) {
            // Table doesn't exist yet (migration not run) - return empty array
            return [];
        }
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare('SELECT b.*, g.name as genre FROM books b LEFT JOIN genres g ON b.genre_id = g.id WHERE b.id = :id');
        $stmt->execute(['id'=>$id]);
        $book = $stmt->fetch();
        if ($book) {
            $book['authors'] = $this->getAuthors($id);
        }
        return $book;
    }

    public function archive($id)
    {
        // Cannot archive if active rentals exist
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rentals WHERE book_id = :id AND status = "active"');
        $stmt->execute(['id'=>$id]);
        if ($stmt->fetchColumn() > 0) throw new Exception('Cannot archive book with active rentals');

        $stmt = $this->pdo->prepare('UPDATE books SET archived = 1 WHERE id = :id');
        return $stmt->execute(['id'=>$id]);
    }

    public function available($id)
    {
        $stmt = $this->pdo->prepare('SELECT available_copies FROM books WHERE id = :id AND archived = 0');
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch();
        return $row ? (int)$row['available_copies'] : 0;
    }

    public function markRented($id, $quantity = 1)
    {
        if ($quantity < 1) return false;
        
        $stmt = $this->pdo->prepare('UPDATE books SET available_copies = available_copies - :qty, times_rented = times_rented + :qty, last_rented_at = NOW() WHERE id = :id AND available_copies >= :qty');
        $stmt->execute(['id'=>$id, 'qty'=>$quantity]);
        return $stmt->rowCount() > 0;
    }

    public function markReturned($id, $quantity = 1)
    {
        if ($quantity < 1) return false;
        
        $stmt = $this->pdo->prepare('UPDATE books SET available_copies = available_copies + :qty WHERE id = :id');
        $stmt->execute(['id'=>$id, 'qty'=>$quantity]);
        return $stmt->rowCount() > 0;
    }

    public function search($q = '', $onlyAvailable = false, $genreId = null)
    {
        $sql = 'SELECT b.*, g.name as genre FROM books b LEFT JOIN genres g ON b.genre_id = g.id WHERE b.archived = 0 AND (b.title LIKE :q OR b.isbn LIKE :q)';
        $params = ['q'=>"%$q%"];
        
        if ($onlyAvailable) $sql .= ' AND b.available_copies > 0';
        if ($genreId) {
            $sql .= ' AND b.genre_id = :genre';
            $params['genre'] = $genreId;
        }
        
        $sql .= ' ORDER BY g.name, b.title';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $books = $stmt->fetchAll();
        
        // Add authors to each book
        foreach ($books as &$book) {
            $book['authors'] = $this->getAuthors($book['id']);
        }
        
        return $books;
    }

    public function getByGenre($genreId, $onlyAvailable = false)
    {
        $sql = 'SELECT b.*, g.name as genre FROM books b LEFT JOIN genres g ON b.genre_id = g.id WHERE b.archived = 0 AND b.genre_id = :gid';
        if ($onlyAvailable) $sql .= ' AND b.available_copies > 0';
        $sql .= ' ORDER BY b.title';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['gid'=>$genreId]);
        $books = $stmt->fetchAll();
        
        foreach ($books as &$book) {
            $book['authors'] = $this->getAuthors($book['id']);
        }
        
        return $books;
    }

    public function getStockStatus($bookId)
    {
        $book = $this->find($bookId);
        if (!$book) return null;
        
        $minLevel = (int)$book['restock_min_level'];
        $stock = (int)$book['stock_count'];
        
        if ($stock <= 0) return 'out_of_stock';
        if ($stock <= $minLevel) return 'low_stock';
        return 'ok_stock';
    }

    public function restock($bookId, $quantity, $reason = null, $userId = null)
    {
        if ((int)$quantity <= 0) throw new Exception('Restock quantity must be > 0');
        
        $this->pdo->beginTransaction();
        try {
            // Update stock count
            $stmt = $this->pdo->prepare('UPDATE books SET stock_count = stock_count + :qty, total_copies = total_copies + :qty, available_copies = available_copies + :qty WHERE id = :id');
            $stmt->execute(['qty'=>$quantity, 'id'=>$bookId]);
            
            // Log inventory transaction
            $stmt = $this->pdo->prepare('INSERT INTO inventory_logs (book_id, action, quantity_change, reason, created_by) VALUES (:bid, :action, :qty, :reason, :uid)');
            $stmt->execute(['bid'=>$bookId, 'action'=>'restock', 'qty'=>$quantity, 'reason'=>$reason, 'uid'=>$userId]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getAvailabilityStats()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_books, SUM(available_copies) as available_books FROM books WHERE archived = 0");
        return $stmt->fetch();
    }

    public function getAllGenres()
    {
        $stmt = $this->pdo->query('SELECT id, name FROM genres ORDER BY name');
        return $stmt->fetchAll();
    }

    public function getInventoryStats()
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) as low_stock FROM books WHERE archived = 0 AND stock_count <= restock_min_level');
        $lowStockCount = $stmt->fetch()['low_stock'] ?? 0;
        
        $stmt = $this->pdo->query('SELECT COUNT(*) as out_of_stock FROM books WHERE archived = 0 AND stock_count = 0');
        $outOfStockCount = $stmt->fetch()['out_of_stock'] ?? 0;
        
        return [
            'low_stock' => $lowStockCount,
            'out_of_stock' => $outOfStockCount,
            'total_value' => $this->getTotalInventoryValue()
        ];
    }

    private function getTotalInventoryValue()
    {
        $stmt = $this->pdo->query('SELECT SUM(stock_count * price) as total_value FROM books WHERE archived = 0');
        $result = $stmt->fetch();
        return (float)($result['total_value'] ?? 0);
    }
}