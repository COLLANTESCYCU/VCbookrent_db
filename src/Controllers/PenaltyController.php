<?php
require_once __DIR__ . '/../Models/Penalty.php';

class PenaltyController
{
    private $penalty;
    public function __construct()
    {
        $this->penalty = new Penalty();
    }

    public function markPaid($id)
    {
        return $this->penalty->markPaid($id);
    }

    public function find($id)
    {
        return $this->penalty->find($id);
    }

    public function listUnpaid()
    {
        return $this->penalty->listUnpaid();
    }

    public function listForUser($userId)
    {
        return $this->penalty->listForUser($userId);
    }
}