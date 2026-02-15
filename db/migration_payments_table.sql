-- Migration: Create Payments Table
-- This table handles all payment transactions with support for multiple payment methods
-- Connects to rentals table for transaction tracking

CREATE TABLE IF NOT EXISTS tbl_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rental_id INT NOT NULL,
  user_id INT NOT NULL,
  amount_charged DECIMAL(10,2) NOT NULL,
  amount_received DECIMAL(10,2) DEFAULT 0.00,
  change_amount DECIMAL(10,2) DEFAULT 0.00,
  payment_method ENUM('cash','card','online','check','other') NOT NULL,
  payment_status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
  
  -- Cash payment details
  cash_received DECIMAL(10,2) DEFAULT NULL,
  
  -- Card payment details
  card_number VARCHAR(19) DEFAULT NULL,
  card_holder VARCHAR(100) DEFAULT NULL,
  card_expiry VARCHAR(7) DEFAULT NULL,
  card_cvv VARCHAR(4) DEFAULT NULL,
  card_last_four VARCHAR(4) DEFAULT NULL,
  
  -- Online payment details
  online_transaction_no VARCHAR(100) DEFAULT NULL,
  online_gateway VARCHAR(50) DEFAULT NULL,
  
  -- Check payment details
  check_number VARCHAR(50) DEFAULT NULL,
  check_bank VARCHAR(100) DEFAULT NULL,
  
  -- General fields
  payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_by INT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  reference_no VARCHAR(100) DEFAULT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign keys
  FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
  
  -- Indexes for faster queries
  INDEX idx_rental (rental_id),
  INDEX idx_user (user_id),
  INDEX idx_payment_date (payment_date),
  INDEX idx_payment_method (payment_method),
  INDEX idx_payment_status (payment_status),
  INDEX idx_reference (reference_no)
);

-- Create a view for payment summary (optional but useful)
CREATE OR REPLACE VIEW vw_payment_summary AS
SELECT 
  p.id,
  p.rental_id,
  p.user_id,
  u.fullname as user_name,
  u.email,
  b.title as book_title,
  p.amount_charged,
  p.amount_received,
  p.change_amount,
  p.payment_method,
  p.payment_status,
  p.payment_date,
  p.reference_no,
  r.rent_date,
  r.due_date
FROM tbl_payments p
JOIN rentals r ON p.rental_id = r.id
JOIN users u ON p.user_id = u.id
JOIN books b ON r.book_id = b.id
ORDER BY p.payment_date DESC;

-- Create a view for daily sales summary
CREATE OR REPLACE VIEW vw_daily_sales AS
SELECT 
  DATE(payment_date) as sale_date,
  payment_method,
  payment_status,
  COUNT(*) as transaction_count,
  SUM(amount_charged) as total_charged,
  SUM(amount_received) as total_received
FROM tbl_payments
WHERE payment_status = 'completed'
GROUP BY DATE(payment_date), payment_method, payment_status
ORDER BY sale_date DESC;
