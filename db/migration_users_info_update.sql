ALTER TABLE users 
  CHANGE COLUMN name fullname VARCHAR(255) NOT NULL,
  CHANGE COLUMN contact contact_no VARCHAR(255) DEFAULT NULL,
  CHANGE COLUMN address address TEXT DEFAULT NULL,
  DROP COLUMN username,
  DROP COLUMN role,
  DROP COLUMN status,
  DROP COLUMN last_status_change_at;
-- Passwords are stored securely in password_hash, not shown in user info tables.
