<?php
require_once __DIR__ . '/../Models/User.php';

class UserController
{
    private $user;
    public function __construct()
    {
        $this->user = new User();
    }

    public function register($input)
    {
        // Clean inputs and pass through
        return $this->user->register($input);
    }

    public function view($id)
    {
        return $this->user->findById($id);
    }

    public function update($id, $input)
    {
        return $this->user->update($id, $input);
    }

    public function setStatus($id, $status)
    {
        return $this->user->setStatus($id, $status);
    }

    public function listAll($onlyActive = false)
    {
        return $this->user->all($onlyActive);
    }

    public function getStats($id)
    {
        return $this->user->getStats($id);
    }
}