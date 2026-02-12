-- Migration: Complete Feature Enhancements
-- Run this migration to add support for:
-- 1. Book pricing and inventory
-- 2. Multiple authors per book
-- 3. User address field
-- 4. Inventory management
-- 5. Transaction history
-- 6. Updated penalty calculation

-- Step 1: Add price and inventory fields to books
ALTER TABLE books ADD COLUMN `price` DECIMAL(10,2) DEFAULT 0.00 AFTER `author`;
ALTER TABLE books ADD COLUMN `stock_count` INT DEFAULT 0 AFTER `total_copies`;

-- Step 2: Create book_authors junction table for multiple authors support
CREATE TABLE IF NOT EXISTS book_authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  author_name VARCHAR(255) NOT NULL,
  author_order INT DEFAULT 0,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  UNIQUE KEY unique_author (book_id, author_order)
);

-- Step 3: Add address field to users
ALTER TABLE users ADD COLUMN `address` TEXT DEFAULT NULL AFTER `contact`;

-- Step 4: Create inventory_logs table for tracking stock changes
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

-- Step 5: Create transaction_history table
CREATE TABLE IF NOT EXISTS transaction_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  transaction_type VARCHAR(50) NOT NULL,
  related_id INT DEFAULT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0.00,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Step 6: Add fields to rentals for cash payment tracking
ALTER TABLE rentals ADD COLUMN `cash_received` DECIMAL(10,2) DEFAULT NULL AFTER `penalty_id`;
ALTER TABLE rentals ADD COLUMN `change_amount` DECIMAL(10,2) DEFAULT NULL AFTER `cash_received`;
ALTER TABLE rentals ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `change_amount`;

-- Step 7: Update penalty_rules to use ₱10 per day
UPDATE penalty_rules SET per_day_rate = 10.00, min_amount = 0.00 WHERE applies = 1;

-- Step 8: Add inventory status tracking columns to books
ALTER TABLE books ADD COLUMN `restock_min_level` INT DEFAULT 3 AFTER `stock_count`;

-- Step 9: Add session table for user authentication
CREATE TABLE IF NOT EXISTS user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_token VARCHAR(255) NOT NULL UNIQUE,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (session_token),
  INDEX idx_expires (expires_at)
);

-- Step 10: Seed sample books with prices and inventory
-- (Optional - uncomment if needed)
-- UPDATE books SET price = 350.00, stock_count = total_copies WHERE price = 0;

