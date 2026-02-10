<?php
require_once __DIR__ . '/../Database.php';

class Genre
{
    private $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    public function all()
    {
        return $this->pdo->query('SELECT * FROM genres')->fetchAll();
    }
}
