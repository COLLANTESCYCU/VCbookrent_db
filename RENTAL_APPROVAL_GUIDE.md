# Rental Approval System - Troubleshooting Guide

## What We Fixed

1. **RentalController::getAll()** - Now handles both `fullname` and `name` columns in users table
2. **Rental::approveRental()** - Now handles missing `quantity` column gracefully
3. **Database Setup Script** - Created to ensure all necessary columns exist

## How the System Works

### Flow from home.php (Requires Admin Approval)
1. User goes to home.php
2. Clicks "Rent Now" on any book
3. Selects rent date, due date, quantity, user
4. Proceeds to payment 
5. Selects payment method (Cash/Card/Online)
6. Submits form to **rental_history.php**
7. Rental is created with **status='pending'** ✓
8. Rental appears in their "Pending Rentals" section
9. Goes to rentals.php page (staff/admin only)
10. Sees the "X pending rental(s)" alert
11. Finds rental in table with yellow "Pending ⏳" badge
12. Clicks "Approve" button
13. System marks as status='active' and decreases inventory
14. Rental is now ready for pickup

## How the System Works

### Flow from index.php (Direct Entry)
- Form also submits to rentals.php with rental details
- Creates rental with status='pending'
- Staff/admin approves immediately from rentals.php

## Next Steps to Verify Everything Works

### Step 1: Ensure Database is Set Up
Run this from the command line:
```
cd c:\xampp\htdocs\VCbookrent_db
php setup_database.php
```

### Step 2: Test the Full Rental Flow
1. **Open home.php** in your browser:
   - http://localhost:80/bookrent_db/public/home.php
   
2. **Log in as a regular user** (if not already logged in)
   - Use a non-admin/staff account

3. **Click "Rent Now"** on any book

4. **Fill in the rental form:**
   - Select a user (your own account)
   - Pick rent date (today)
   - Pick due date (7 days from now, default suggested)
   - Quantity: 1
   - Click "Proceed to Payment"

5. **Choose payment method:**
   - Select "Cash"
   - Enter an amount (should be at least book price)
   - Click "Rent Now/Confirm"

6. **You should be redirected to rental_history.php** and see:
   - Green success message: "Rental created successfully!"
   - Your "Pending Rentals" section with your new rental
   - Yellow "Pending ⏳" badge showing it awaits approval

### Step 3: Test Approval Workflow
1. **Open rentals.php** (staff/admin page):
   - http://localhost:80/bookrent_db/public/rentals.php
   - Must be logged in as admin/staff

2. **You should see:**
   - Yellow alert box saying "1 pending rental(s) awaiting approval!"
   - Your new rental in the table with "Pending ⏳" badge
   - Green "Approve" button in Actions column

3. **Click "Approve" button:**
   - Confirm the dialog
   - Rental status should change to "Active" (green badge)
   - Book inventory should decrease

## If Something Doesn't Work

### Issue: Form doesn't submit
- **Check browser console** (F12 → Console tab)
- Look for JavaScript errors
- Check form inputs are actually filled

### Issue: Rental not appearing in rental_history.php
- Check the green success message at the top
- If no message, form wasn't submitted properly
- Check browser network tab (F12 → Network) to see POST request
- Should see form data submitted to rental_history.php

### Issue: Rental appears in history but not in rentals.php
- This is a data retrieval issue
- Verify using: `php setup_database.php` 
- Check for database columns with: Check error logs for SQL errors

### Issue: Buttons not visible in Actions column
- Click Inspect Element (F12) on rentals table
- Right-click Actions cell → Inspect
- Check if HTML elements exist but are hidden
- Check browser console for errors

## Files Modified This Session

- **src/Controllers/RentalController.php** - Improved user name handling
- **src/Models/Rental.php** - Made approveRental() more robust
- **setup_database.php** - Created new database setup script
- **quick_rental_check.php** - Created diagnostic script

## Testing Commands

From command line in c:\xampp\htdocs\VCbookrent_db:

```
# Run database setup
php setup_database.php

# Check PHP syntax
php -l src/Models/Rental.php
php -l src/Controllers/RentalController.php
php -l public/rental_history.php
php -l public/rentals.php
```

## Important Notes

⚠️ **Workflow Rule:**
- **All rentals start as status='pending'** regardless of source
- Only admins/staff can approve (change to 'active')
- Book inventory only decreases when approved
- Users see pending rentals in rental_history.php
- Staff see and manage all rentals in rentals.php

✅ **Payment Methods:**
- Cash: Amount must be >= book price
- Card: Card details stored (not actually charged)
- Online: Transaction number recorded

✅ **Status Badges:**
- 🟨 Pending ⏳ (yellow) = Awaiting admin approval
- 🟩 Active (green) = Approved, ready for pickup  
- 🟦 Returned (blue) = Book returned
- 🔴 Overdue (red) = Past due date
