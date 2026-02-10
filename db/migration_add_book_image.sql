-- Migration: add image column to books table if it does not exist
-- Run this if the DB is already created:
ALTER TABLE books ADD COLUMN `image` VARCHAR(255) DEFAULT NULL;