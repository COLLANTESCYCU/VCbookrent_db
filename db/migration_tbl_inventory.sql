-- Create tbl_inventory for bookrent_db
CREATE TABLE IF NOT EXISTS tbl_inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  stock_count INT NOT NULL DEFAULT 0,
  restock_min_level INT NOT NULL DEFAULT 3,
  last_restocked_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  UNIQUE KEY unique_book (book_id)
);

-- Optional: Migrate current stock from books table to tbl_inventory
INSERT INTO tbl_inventory (book_id, stock_count, restock_min_level, last_restocked_at)
SELECT id, stock_count, restock_min_level, NULL FROM books
ON DUPLICATE KEY UPDATE stock_count=VALUES(stock_count), restock_min_level=VALUES(restock_min_level);
