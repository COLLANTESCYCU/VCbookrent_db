<?php
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct(array $config)
    {
        $this->pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['pass']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function pdo()
    {
        return $this->pdo;
    }
}