-- Migration: Update rental status to include pending status
-- This migration adds 'pending' status to the rental status enum
-- Now rentals can be: pending (awaiting approval), active (approved), returned, or overdue

-- Step 1: Modify the rentals table status column to include 'pending'
ALTER TABLE rentals 
MODIFY COLUMN status ENUM('pending','active','returned','overdue') DEFAULT 'pending';

-- Note: Existing rentals will keep their current status (active, returned, overdue)
-- New rentals will start as 'pending' until admin/staff approves them

