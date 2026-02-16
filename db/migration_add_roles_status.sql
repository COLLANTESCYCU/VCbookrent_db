-- Migration: Add role and status columns back to users table
-- These were dropped by earlier migrations but are needed for RBAC

-- Check and add role column if it doesn't exist
ALTER TABLE users 
  ADD COLUMN role ENUM('admin','staff','user') DEFAULT 'user' AFTER id,
  ADD COLUMN status ENUM('active','inactive') DEFAULT 'active' AFTER role;
