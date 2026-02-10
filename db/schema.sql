-- BookRent DB schema (minimal, extend as needed)

CREATE TABLE IF NOT EXISTS genres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  contact VARCHAR(255) DEFAULT NULL,
  role ENUM('admin','staff','user') DEFAULT 'user',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  total_rentals INT DEFAULT 0,
  total_late_returns INT DEFAULT 0,
  last_status_change_at DATETIME DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(50) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  genre_id INT DEFAULT NULL,
  total_copies INT NOT NULL DEFAULT 1,
  available_copies INT NOT NULL DEFAULT 1,
  times_rented INT DEFAULT 0,
  last_rented_at DATETIME DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  archived TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS rentals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  rent_date DATETIME NOT NULL,
  due_date DATETIME NOT NULL,
  return_date DATETIME DEFAULT NULL,
  status ENUM('active','returned','cancelled','overdue') DEFAULT 'active',
  duration_days INT NOT NULL,
  penalty_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS penalties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rental_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  days_overdue INT NOT NULL DEFAULT 0,
  paid TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS penalty_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  per_day_rate DECIMAL(10,2) DEFAULT 0.00,
  min_amount DECIMAL(10,2) DEFAULT 0.00,
  applies TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action VARCHAR(255) NOT NULL,
  context TEXT,
  ip VARCHAR(45) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed a default penalty rule
INSERT INTO penalty_rules (per_day_rate, min_amount, applies) VALUES (1.00, 0.00, 1) 
ON DUPLICATE KEY UPDATE per_day_rate=VALUES(per_day_rate);

-- Sample genre
INSERT IGNORE INTO genres (name) VALUES ('General');
