<?php
require_once __DIR__ . '/../Models/Book.php';

class BookController
{
    // Authors management
    public function listAuthors()
    {
        $pdo = \Database::getInstance()->pdo();
        // Get standalone authors from authors table
        $stmt = $pdo->query('SELECT * FROM authors ORDER BY author_name');
        return $stmt->fetchAll();
    }

    public function addAuthor($input)
    {
        $pdo = \Database::getInstance()->pdo();
        $name = trim($input['author_name'] ?? '');
        if (!$name) throw new Exception('Author name required');
        // Insert into authors table for standalone author management
        $stmt = $pdo->prepare('INSERT INTO authors (author_name) VALUES (:name)');
        $stmt->execute(['name'=>$name]);
        return $pdo->lastInsertId();
    }
    private $book;
    public function __construct()
    {
        $this->book = new Book();
    }

    public function add($input, $file = null)
    {
        // handle image upload if provided
        if ($file && isset($file['tmp_name']) && $file['tmp_name']) {
            $allowed = ['image/jpeg','image/png','image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed)) throw new Exception('Invalid image type');
            if ($file['size'] > 2 * 1024 * 1024) throw new Exception('Image too large (max 2MB)');

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fname = uniqid('bimg_') . '.' . $ext;
            $destDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $destDir . $fname)) throw new Exception('Failed to move uploaded file');
            $input['image'] = $fname;
        }
        return $this->book->add($input);
    }

    public function update($id, $input, $file = null)
    {
        // handle image upload if provided
        if ($file && isset($file['tmp_name']) && $file['tmp_name']) {
            $allowed = ['image/jpeg','image/png','image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed)) throw new Exception('Invalid image type');
            if ($file['size'] > 2 * 1024 * 1024) throw new Exception('Image too large (max 2MB)');

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fname = uniqid('bimg_') . '.' . $ext;
            $destDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $destDir . $fname)) throw new Exception('Failed to move uploaded file');
            $input['image'] = $fname;
        }
        return $this->book->update($id, $input);
    }

    public function archive($id)
    {
        return $this->book->archive($id);
    }

    public function search($q, $onlyAvailable=false)
    {
        return $this->book->search($q, $onlyAvailable);
    }
}