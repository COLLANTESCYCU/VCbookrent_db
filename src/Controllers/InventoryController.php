<?php
require_once __DIR__ . '/../Models/Inventory.php';

class InventoryController
{
    private $inventory;

    public function __construct()
    {
        $this->inventory = new Inventory();
    }

    public function getAll()
    {
        return $this->inventory->getAllWithStatus();
    }

    public function getLowStock()
    {
        return $this->inventory->getLowStockBooks();
    }

    public function getOutOfStock()
    {
        return $this->inventory->getOutOfStockBooks();
    }

    public function restockBook($bookId, $quantity, $reason = null, $userId = null)
    {
        return $this->inventory->restockBook($bookId, $quantity, $reason, $userId);
    }

    public function getTransactionLogs($bookId = null, $limit = 50)
    {
        return $this->inventory->getTransactionLogs($bookId, $limit);
    }

    public function getStats()
    {
        return $this->inventory->getStats();
    }
}