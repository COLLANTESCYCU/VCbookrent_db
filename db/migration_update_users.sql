-- Migration: Update users table for new registration requirements
ALTER TABLE users 
  DROP COLUMN username,
  DROP COLUMN role,
  DROP COLUMN status,
  DROP COLUMN last_status_change_at,
  ADD COLUMN address TEXT DEFAULT NULL AFTER contact;

-- Note: Passwords should still be stored securely in password_hash.
-- Admin/staff can set a default password for new users.
