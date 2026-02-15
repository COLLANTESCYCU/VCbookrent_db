-- Add quantity column to rentals table
-- Each rental transaction now records how many copies were rented in that single transaction

ALTER TABLE rentals ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `duration_days`;

-- Create an index on quantity for filtering single vs. multi-copy rentals
CREATE INDEX idx_rental_quantity ON rentals(quantity);
