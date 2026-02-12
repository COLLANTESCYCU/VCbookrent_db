<?php
require_once __DIR__ . '/../Models/Book.php';

class SearchController
{
    private $book;
    public function __construct()
    {
        $this->book = new Book();
    }

    public function searchBooks($q, $onlyAvailable=false)
    {
        return $this->book->search($q, $onlyAvailable);
    }
}