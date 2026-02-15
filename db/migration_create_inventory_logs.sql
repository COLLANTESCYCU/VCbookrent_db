-- Create inventory_logs table for inventory tracking
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
