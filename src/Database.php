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
        
        // Auto-apply pending migrations
        $this->applyPendingMigrations();
    }
    
    private function applyPendingMigrations()
    {
        try {
            // Migration 1: Add quantity column to rentals if it doesn't exist
            $stmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rentals' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='quantity'");
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                // Column doesn't exist, add it
                $this->pdo->exec("ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`");
                
                // Try to add index
                try {
                    $this->pdo->exec("CREATE INDEX idx_rental_quantity ON rentals(quantity)");
                } catch (Exception $e) {
                    // Index might already exist, ignore
                }
            }
            
            // Migration 2: Create authors table if it doesn't exist
            $stmt = $this->pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='authors' AND TABLE_SCHEMA=DATABASE()");
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                // Table doesn't exist, create it
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS authors (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  author_name VARCHAR(255) NOT NULL UNIQUE,
                  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }
        } catch (Exception $e) {
            // Silently fail - tables might not exist yet during initial setup
        }
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