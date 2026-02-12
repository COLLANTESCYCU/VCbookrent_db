# VCBookRent - Quick Setup Guide

## 🚀 STEP 1: Run Database Migration

This MUST be done first before testing anything.

### Option A: Using phpMyAdmin
1. Open phpMyAdmin at `http://localhost/phpmyadmin`
2. Select the `bookrent_db` database
3. Go to SQL tab
4. Copy all content from `db/migration_complete_features.sql`
5. Paste and execute

### Option B: Using Command Line
```bash
cd C:\xampp\htdocs\VCbookrent_db
mysql -u root bookrent_db < db/migration_complete_features.sql
```

**What the migration does:**
- Adds `price` column to books
- Adds `stock_count` and `restock_min_level` to books
- Creates `book_authors` table for multiple authors
- Adds `address` field to users
- Creates `inventory_logs` table
- Creates `transaction_history` table
- Updates `rentals` table with cash payment fields
- Creates `user_sessions` table
- Sets penalty rate to ₱10/day

---

## 🧪 STEP 2: Test Basic Authentication

1. Open `http://localhost/VCbookrent_db/public/register.php`
2. Create a test user account:
   - Name: Test User
   - Email: test@example.com
   - Password: test123456
   - Contact: 09123456789
   - Address: Test Address

3. You should see "Registration successful!" message
4. Redirected to login page after 2 seconds
5. Login with email: test@example.com, password: test123456
6. Should redirect to books.php

**If you get errors:**
- Check that migration ran successfully
- Check browser console for PHP errors
- Look at error log in `C:\xampp\apache\logs\error.log`

---

## 📝 STEP 3: Create Admin User (For Testing)

You need to manually create an admin user in the database:

```sql
INSERT INTO users (name, email, password_hash, role, status, created_at) 
VALUES (
  'Admin User',
  'admin@example.com',
  '$2y$10$.PASSWORD_HASH_HERE',
  'admin',
  'active',
  NOW()
);
```

**To generate password hash for "admin123":**
1. Create a temp PHP file with:
```php
<?php
echo password_hash('admin123', PASSWORD_DEFAULT);
?>
```
2. Run it and copy the output
3. Replace `.PASSWORD_HASH_HERE` above with that output

OR

Use online tool: https://www.php.net/manual/en/funct ion.password-hash.php

---

## 🔄 STEP 4: Update Remaining Frontend Pages

### Priority 1: books.php
**Changes needed:**
- Display book price below title
- Show genre name
- Display multiple authors
- Show stock status (green/yellow/red badge)
- Add price and stock info to rent form validation

### Priority 2: rentals.php  
**Changes needed:**
- Add "Cash Amount" input field to rent form
- Add automatic change calculation: Change = Cash - Price
- Add validation: Cash >= Price (with error message)
- Display book price in the form

### Priority 3: users.php
**Changes needed:**
- Remove username field from add/edit forms
- Add address textarea field
- Update insert/update SQL queries to match new schema
- Display address in user list

### Priority 4: reports.php
**Changes needed:**
- Create dashboard with summary cards
- Add rental trends chart (30 days)
- Add popular books table
- Add penalty statistics
- Add genre breakdown chart
- Add active rentals count
- AddUser metrics

---

## 📊 STEP 5: Testing Complete Flow

After updating pages, test this workflow:

1. **Register** → New account creation works
2. **Login** → as admin (if created) or regular user
3. **Browse Books** → See books with prices and genres
4. **Rent Book** → Fill cash amount, see change calculation
5. **Check Transactions** → See rental transaction recorded
6. **Return Book** → System automatically calculates penalties
7. **Check Penalties** → Penalty recorded in transaction history
8. **Admin Dashboard** → See reports and inventory stats

---

## 🐛 TROUBLESHOOTING

### "Class 'AuditLog' not found"
✅ **Already Fixed** - Added require_once in Penalty.php

### Login redirects to wrong page
- Check user role in database
- Ensure Auth::getInstance() is properly initialized
- Check session_start() is called

### Can't see new pages/menu items
- Make sure sidebar.php is using the new RBAC logic
- Or rename sidebar_new.php to sidebar.php after backing up old one

### Database fields not appearing
- Run the migration again
- Check for SQL errors in phpMyAdmin
- Verify all ALTER TABLE statements succeeded

### Cash payment form not working
- Ensure book has `price` field set in database
- Check form validation JavaScript in books.php
- Verify change calculation: `(float)$cash - (float)$book_price`

---

## 📱 DEFAULT TEST ACCOUNTS

After setup, create these accounts for testing:

**Admin:**
- Email: admin@example.com
- Password: admin123
- Role: admin

**Staff:**
- Email: staff@example.com
- Password: staff123
- Role: staff

**User:**
- Email: user@example.com
- Password: user123
- Role: user

---

## 📚 Key Features Now Available

✅ **Authentication System**
- Login/Register for users
- Role-based access control (Admin, Staff, User)

✅ **Book Management**
- Multiple authors per book
- Pricing system
- Inventory tracking
- Genre categorization

✅ **Rental System**
- Cash payment tracking
- Automatic change calculation
- Price validation

✅ **Penalty System**
- Automatic ₱10/day calculation
- Penalty recording on book return
- Transaction history tracking

✅ **Inventory Management** (Staff/Admin)
- Stock level monitoring
- Low stock alerts
- Restock logging
- Inventory value calculation

✅ **User Transaction History**
- View all transactions
- Track active rentals
- Monitor overdue items
- See penalty amounts

✅ **Reports & Analytics** (Admin/Staff)
- Dashboard summary
- Rental trends
- Popular books
- Penalty statistics
- Genre analytics

---

## 🎯 NEXT ACTIONS

1. ✅ Run database migration (CRITICAL)
2. ✅ Test registration/login
3. ✅ Create admin test account
4. Update books.php (add price/genre display)
5. Update rentals.php (add cash payment form)
6. Update users.php (remove username, add address)
7. Update reports.php (dashboard redesign)
8. Test complete workflow
9. Go live!

---

## 📞 NOTES

- All timestamps use system timezone
- Penalties are calculated at return time
- Stock status is color-coded: Red (out), Yellow (low), Green (ok)
- Transaction history is immutable (for audit trail)
- All calculations use precise decimal(10,2) fields

---

**Last Updated:** February 11, 2026  
**System Version:** 2.0 Complete Enhancement
