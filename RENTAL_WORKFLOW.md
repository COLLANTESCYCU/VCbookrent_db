# Complete Rental System Workflow

## Overview
The book rental system follows a three-stage approval workflow:
1. **Pending (User Creates Rental)** - Rental created with status='pending', inventory NOT decreased
2. **Awaiting Approval (Shown in Rental History)** - User sees "Pending ⏳" badge in rental_history.php
3. **Approved (Admin Approves)** - Status changes to 'active', inventory DECREASED

---

## Detailed Workflow Steps

### Stage 1: User Rents Book (home.php)

**User Actions:**
1. Browse home.php and view available books
2. Click **"Rent Now"** button on any book
3. Book Details Modal opens showing:
   - Book title, author(s), ISBN
   - Genre, available copies, rental price
   - Stock status

**Fill Rental Details:**
- Select a user (regular users see only themselves)
- Select **Rent Date** (when they want to start)
- Select **Due Date** (when to return)
- Select **Quantity** (1-3 copies allowed)
- View automatic cost calculation

**Proceed to Payment:**
- Click **"Proceed to Payment"** button
- Payment Confirmation Modal opens

**Select Payment Method:**
- **Card** (required fields: card number, holder name, expiry, CVV)
- **Online Transfer** (required field: transaction number)
- ~~Cash~~ (REMOVED - no longer available)

**Complete Rental:**
- Click **"Complete Rental"** button
- Form submits to rental_history.php with:
  - book_id, user_id, duration_days, quantity
  - rent_date, due_date
  - payment_method and payment details

---

### Stage 2: Rental Created in Database

**Database Entry (rentals table):**
- Rental marked with **status = 'pending'**
- Book's available_copies: **NOT YET DECREASED**
- Payment method and details stored
- Quantity recorded

**Flash Message Displayed:**
- "Book rented successfully! ✅"
- "Please wait for admin approval. Once approved, your rental will be ready for pickup at our store..."

---

### Stage 3: User Views Rental History (rental_history.php)

**Pending Rentals Section:**
- Yellow badge: **"Pending ⏳"**
- Shows: Book title, ISBN, quantity, rent date, due date, payment method
- User waiting for admin approval

**What Happens:**
- No inventory change yet
- Book still available for other users to rent
- Quantity doesn't count toward inventory

---

### Stage 4: Admin Approves Rental (rentals.php)

**Staff/Admin Views Rentals:**
1. Go to rentals.php
2. See all rentals in a table with columns:
   - ID, Book Title, User, Rent Date, Due Date, Return Date, Status, Actions

**Pending Rentals Display:**
- Yellow badge: **"Pending ⏳"**
- Action button: ✓ (Approve button)

**Approval Action:**
1. Admin clicks ✓ (Approve) button on pending rental
2. Confirmation dialog: "Approve this rental?"
3. Click "OK" to confirm

**What Happens on Approval:**
1. Rental status changes: **pending → active**
2. **inventory decrease triggered:**
   - Book's `available_copies` decreased by quantity rented
   - `times_rented` incremented by quantity
   - `last_rented_at` updated to NOW()
3. Rental now shows green "Active" badge in rentals.php

---

### Stage 5: Rental Status in Rental History

**After Approval:**
- Rental moves to **"Active Rentals"** section in rental_history.php
- Blue badge: **"Active"**
- Shows how many days left until due date
- User can now "pick up" the book from the store

**Return Process:**
- Admin clicks ← (Return) button in rentals.php
- Returns the book and updates status to "Returned"
- If overdue, penalty automatically calculated

**Final Status:**
- Rental moves to **"Completed/Returned"** section
- Green badge: **"Returned"**

---

## Database Schema (Key Fields)

### rentals table
```
id (PK)
user_id (FK - users)
book_id (FK - books)
rent_date (DATE)
due_date (DATE)
return_date (DATE, NULL until returned)
status ENUM('pending','active','returned','overdue','cancelled')
duration_days INT
quantity INT (how many copies rented)
payment_method VARCHAR('card', 'online')
card_number VARCHAR (optional)
card_holder VARCHAR (optional)
card_expiry VARCHAR (optional)
card_cvv VARCHAR (optional)
online_transaction_no VARCHAR (optional)
cash_received DECIMAL (always NULL - cash removed)
change_amount DECIMAL (always NULL - cash removed)
created_at TIMESTAMP
```

### books table (affected columns)
```
available_copies INT (DECREASED on approval)
times_rented INT (INCREMENTED on approval)
last_rented_at TIMESTAMP (UPDATED on approval)
```

---

## Key Code Files

### home.php
- **Line 412-419:** Payment method dropdown (Card, Online only - no Cash)
- **Line 910-925:** updatePaymentFields() function - shows/hides payment fields
- **Function:** openBookDetailsModal() - opens rental modal with book details

### rental_history.php
- **Lines 26-118:** POST handler - creates rental with status='pending'
- **Lines 258-305:** Pending Rentals Display Section
- **Validation:** Requires card or online payment (not cash)

### rentals.php
- **Lines 46-92:** Edit rental handler - calls approveRental() when status pending→active
- **Table:** Shows all rentals with status badges
- **Approve Button:** ✓ icon - changes pending→active and decreases inventory

### src/Models/Rental.php
- **approveRental($rentalId):** 
  - Changes status pending→active
  - Calls bookModel->markRented() to decrease inventory
  - Logs the approval
  
### src/Models/Book.php
- **markRented($id, $quantity):**
  - `UPDATE available_copies = available_copies - $quantity`
  - `UPDATE times_rented = times_rented + $quantity`
  - `UPDATE last_rented_at = NOW()`

---

## Testing Checklist

- [ ] User can open home.php and see books
- [ ] Clicking "Rent Now" opens book details modal
- [ ] Modal shows correct book title, ISBN, price, available copies
- [ ] Payment method dropdown shows **only Card and Online** (NO Cash)
- [ ] Can fill rental details and proceed to payment
- [ ] Can select card or online payment method
- [ ] Completing rental shows "Pending admin approval" message
- [ ] Rental appears in rental_history.php Pending section with ⏳ badge
- [ ] Book's available_copies stays SAME (not decreased yet)
- [ ] Admin can see rental in rentals.php with ⏳ badge
- [ ] Admin clicks Approve (✓) button
- [ ] Rental status changes to Active (green badge)
- [ ] Book's available_copies **DECREASED** by quantity rented
- [ ] Rental appears in rental_history.php Active section with green badge
- [ ] Admin can click Return (←) button to mark as returned
- [ ] Rental moves to Returned section with green badge

---

## Payment Methods Configuration

### Removed (No longer available)
- ❌ Cash payment
- ❌ Cash received field
- ❌ Change calculation

### Available Methods
- ✅ **Card Payment**: 
  - Card number (13+ digits)
  - Cardholder name
  - Expiry date (MM/YYYY)
  - CVV (3-4 digits)
  
- ✅ **Online Transfer**:
  - Transaction/Reference number

---

## Important Notes

1. **Inventory Change Timing**
   - NOT when rental is created (pending)
   - ONLY when approved (pending→active)
   - This allows admin to verify before consuming inventory

2. **Quantity Handling**
   - Users can rent 1-3 copies of a single book
   - All copies show as rented once approved
   - Returns also return all rented copies

3. **User Restrictions**
   - Regular users can only rent for themselves
   - Staff/Admin can rent for any user
   - Users with unpaid penalties cannot rent

4. **Book Requirements**
   - Must have available_copies > 0 in inventory
   - Must not be archived
   - Price must be set

---

## Common Issues & Solutions

### Issue: "Only pending rentals can be approved"
**Cause:** Trying to approve non-pending rental
**Solution:** Only click Approve on rentals with ⏳ badge

### Issue: Book shows out of stock before approval
**Cause:** None - book should still show available until approved
**Solution:** Check available_copies in database

### Issue: Payment form disabled
**Cause:** All required fields not filled
**Solution:** Fill rent/due date, select user, select payment method

### Issue: Cash payment option appears
**Solution:** Clear browser cache and reload, or use Ctrl+F5 for hard refresh

---

## Database Queries to Check Status

```sql
-- Check pending rentals
SELECT * FROM rentals WHERE status = 'pending' ORDER BY created_at DESC;

-- Check active rentals (approved)
SELECT * FROM rentals WHERE status = 'active' ORDER BY rent_date DESC;

-- Check book inventory
SELECT id, title, available_copies, times_rented, last_rented_at FROM books ORDER BY title;

-- Check rental with payment details
SELECT r.id, r.status, r.quantity, r.payment_method, b.title, u.fullname
FROM rentals r
JOIN books b ON r.book_id = b.id
JOIN users u ON r.user_id = u.id
WHERE r.status = 'pending'
ORDER BY r.created_at DESC;
```

---

## Summary

The rental system now has a clean, three-stage workflow:
1. **User creates** rental → Status = pending (NO inventory change)
2. **Rental displayed** in history → Shows "Pending ⏳" badge
3. **Admin approves** → Status = active (inventory DECREASED)

Payment is now **card or online only** - cash option removed completely.
