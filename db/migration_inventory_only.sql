-- Inventory-related schema changes for bookrent_db
-- Add inventory fields to books table
ALTER TABLE books 
  ADD COLUMN `price` DECIMAL(10,2) DEFAULT 0.00 AFTER `author`,
  ADD COLUMN `stock_count` INT DEFAULT 0 AFTER `total_copies`,
  ADD COLUMN `restock_min_level` INT DEFAULT 3 AFTER `stock_count`;

-- Create inventory_logs table for tracking stock changes
CREATE TABLE IF NOT EXISTS inventory_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  quantity_change INT NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
