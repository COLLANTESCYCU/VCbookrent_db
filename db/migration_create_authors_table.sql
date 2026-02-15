-- Create authors table for standalone author management
-- Separate from book_authors which is a junction table linking books to authors

CREATE TABLE IF NOT EXISTS authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_name VARCHAR(255) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
