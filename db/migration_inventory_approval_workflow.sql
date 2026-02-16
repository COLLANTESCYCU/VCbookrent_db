-- Migration: Inventory Approval Workflow
-- Date: 2026-02-15
-- Purpose: Implement delayed book inventory decrease until rental approval
-- 
-- Changes:
-- 1. Book inventory is NO LONGER decreased when rental is created (status='pending')
-- 2. Book inventory is only decreased when rental is APPROVED (status changed to 'active')
-- 3. This prevents inventory counting issues during the approval process
-- 
-- Implementation Notes:
-- - No SQL changes needed - schema already supports this
-- - Business logic changes:
--   a. Rental.rentBook() no longer calls bookModel->markRented()
--   b. New Rental.approveRental() method handles inventory decrease
--   c. Admin approval in rentals.php calls approveRental() when changing status from 'pending' to 'active'
--
-- Benefits:
-- - Prevents double-booking during approval workflow
-- - Accurate inventory counts while rentals are pending
-- - Clear separation of request (pending) vs active rental states
-- - Users notified to wait for approval before pickup

-- No SQL changes required - all logic changes are in PHP code
-- This migration serves as documentation of the workflow change

SELECT 'Inventory Approval Workflow migration complete' as status;
