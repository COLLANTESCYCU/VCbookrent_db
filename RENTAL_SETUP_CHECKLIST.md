# ✅ Rental System - Quick Setup Checklist

## Complete the following steps to get rentals working:

### Step 1: Database Migration (CRITICAL)
- [ ] Open your browser and go to: `http://localhost/bookrent_db/public/check_and_apply_migrations.php`
- [ ] Wait for the script to complete
- [ ] Verify all items show ✅ (check marks)
- [ ] If any show ❌, note the errors and try again

### Step 2: Test Rental Creation
Go to Home page (`http://localhost/bookrent_db/public/home.php`)
- [ ] Click "Rent" button on any book
- [ ] A modal should open with book details
- [ ] Check if:
  - [ ] Book title, author, ISBN, genre are displayed
  - [ ] Stock status shows (green/yellow/red badge)
  - [ ] "Proceed to Payment" button is enabled

### Step 3: Complete Payment
In the rental form:
- [ ] Select rent date and due date (7 days apart is default)
- [ ] Select quantity (between 1-3)
- [ ] Click "Proceed to Payment"

In the payment form:
- [ ] Select payment method (Cash, Card, or Online)
- [ ] Fill in the required fields:
  - **Cash**: Enter amount (must be ≥ book price)
  - **Card**: Enter card number and holder name (minimum)
  - **Online**: Just select the method
- [ ] "Complete Rental" button should be enabled
- [ ] Click "Complete Rental"

### Step 4: Verify Rental Appears
After completing the rental:
- [ ] Page should redirect to Rental History
- [ ] You should see a green success message
- [ ] Your rental should appear in "Pending Rentals (Awaiting Approval)" section
- [ ] The rental should show:
  - [ ] Book title
  - [ ] ISBN (in code format)
  - [ ] Quantity rented
  - [ ] Rent date
  - [ ] Due date
  - [ ] Payment method (Cash/Card/Online)
  - [ ] Status badge (yellow "pending ⏳")

## If Something Goes Wrong

### Rental form won't save?
1. Check that Migration step completed successfully
2. Look at the browser console (F12 → Console) for errors
3. Check the page for red error messages

### Payment form fields won't validate?
- For **Cash**: Make sure amount is a number ≥ book price
- For **Card**: Enter at least a card number (test: 4111111111111111)
- The button will enable when validation passes

### Rental appears as "Pending" but you want to test "Active"?
- This is normal! Regular users create "pending" rentals
- To approve and mark as "active", use the Dashboard (requires staff/admin role)

## Quick Test Data

If you don't have books, here's test data you can use:
```sql
INSERT INTO books (isbn, title, author, genre_id, total_copies, available_copies, price)
VALUES ('978-0-7432-7356-5', 'The Last Lecture', 'Randy Pausch', 1, 5, 5, 350.00);
```

## Success Indicators

You'll know it's working when:
✅ New rentals appear in Rental History
✅ Rental displays in correct status section (Pending/Active/Returned/Overdue)
✅ Payment method is recorded and displayed
✅ Quantity and dates are shown correctly
✅ Book details are fetched and displayed completely

---

**First Step**: Run the database migration check!
👉 http://localhost/bookrent_db/public/check_and_apply_migrations.php
