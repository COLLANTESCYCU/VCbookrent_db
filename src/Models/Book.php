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
        if (empty($data['isbn']) || empty($data['title']) || empty($data['author']) || empty($data['total_copies'])) {
            throw new Exception('Missing required book fields');
        }

        if ((int)$data['total_copies'] < 1) throw new Exception('Total copies must be >= 1');

        // Unique ISBN
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM books WHERE isbn = :isbn');
        $stmt->execute(['isbn'=>$data['isbn']]);
        if ($stmt->fetchColumn() > 0) throw new Exception('ISBN must be unique');

        $stmt = $this->pdo->prepare('INSERT INTO books (isbn, title, author, genre_id, total_copies, available_copies, image) VALUES (:isbn, :title, :author, :genre, :total, :total, :image)');
        $stmt->execute([
            'isbn'=>$data['isbn'],
            'title'=>$data['title'],
            'author'=>$data['author'],
            'genre'=>$data['genre'] ?? null,
            'total' => (int)$data['total_copies'],
            'image' => $data['image'] ?? null
        ]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, array $data)
    {
        $fields = [];
        $params = ['id'=>$id];
        foreach (['isbn','title','author','genre_id','total_copies','available_copies','archived','image'] as $f) {
            if (isset($data[$f])) {
                $fields[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }
        if (isset($data['total_copies'])) {
            if ((int)$data['total_copies'] < 1) throw new Exception('Total copies must be >= 1');
            // Keep available copies bounded if necessary
            $book = $this->find($id);
            if ($book) {
                $delta = (int)$data['total_copies'] - (int)$book['total_copies'];
                $params['available_copies'] = max(0, $book['available_copies'] + $delta);
                if (!in_array('available_copies = :available_copies', $fields)) $fields[] = 'available_copies = :available_copies';
            }
        }

        if (!$fields) return false;
        $sql = 'UPDATE books SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM books WHERE id = :id');
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch();
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

    public function markRented($id)
    {
        $stmt = $this->pdo->prepare('UPDATE books SET available_copies = available_copies - 1, times_rented = times_rented + 1, last_rented_at = NOW() WHERE id = :id AND available_copies > 0');
        $stmt->execute(['id'=>$id]);
        return $stmt->rowCount() > 0;
    }

    public function markReturned($id)
    {
        $stmt = $this->pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = :id');
        return $stmt->execute(['id'=>$id]);
    }

    public function search($q = '', $onlyAvailable = false)
    {
        $sql = 'SELECT b.*, g.name as genre FROM books b LEFT JOIN genres g ON b.genre_id = g.id WHERE b.archived = 0 AND (b.title LIKE :q OR b.author LIKE :q OR b.isbn LIKE :q)';
        if ($onlyAvailable) $sql .= ' AND b.available_copies > 0';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['q'=>"%$q%"]);
        return $stmt->fetchAll();
    }

    public function getAvailabilityStats()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_books, SUM(available_copies) as available_books FROM books WHERE archived = 0");
        return $stmt->fetch();
    }
}
