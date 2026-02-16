<?php
// Start session without requiring auth
session_start();

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

$db = \Database::getInstance();
$pdo = $db->pdo();
$auth = Auth::getInstance();

// Get current user if logged in
$currentUser = $auth->currentUser();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt System - Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body class="bg-light p-4">
    <div class="container">
        <h1>📋 Receipt System Diagnostics</h1>
        <hr>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5>Login Status</h5>
            </div>
            <div class="card-body">
                <?php if ($currentUser): ?>
                    <p class="status-ok"><strong>✓ LOGGED IN</strong></p>
                    <p>Email: <?= htmlspecialchars($currentUser['email']) ?></p>
                    <p>Role: <?= htmlspecialchars($currentUser['role']) ?></p>
                    <p>User ID: <?= htmlspecialchars($currentUser['id']) ?></p>
                    
                    <?php
                    // Check if this user has rentals
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rentals WHERE user_id = ?');
                    $stmt->execute([$currentUser['id']]);
                    $rentalCount = $stmt->fetchColumn();
                    ?>
                    
                    <p><strong>Rentals for this user: <?= $rentalCount ?></strong></p>
                    
                    <?php if ($rentalCount > 0): ?>
                        <p class="status-ok">✓ This user has rentals!</p>
                        <p style="color: #666; font-size: 0.9em;">You should be able to view receipts in <a href="/VCbookrent_db/public/rental_history.php">rental_history.php</a></p>
                    <?php else: ?>
                        <p class="status-error">✗ This user has NO rentals</p>
                        <p style="color: #666; font-size: 0.9em;">The receipt system will have no data. Try logging in as a different user or create rentals for this user.</p>
                    <?php endif; ?>
                    
                    <hr style="margin: 15px 0;">
                    
                    <a href="/VCbookrent_db/public/logout.php" class="btn btn-danger btn-sm">Logout</a>
                    <a href="/VCbookrent_db/public/rental_history.php" class="btn btn-primary btn-sm">Go to Rental History</a>
                    
                <?php else: ?>
                    <p class="status-error"><strong>✗ NOT LOGGED IN</strong></p>
                    <p style="color: #666;">You need to login to test the receipt system.</p>
                    <a href="/VCbookrent_db/public/login.php" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white">
                <h5>Test Users (with rentals)</h5>
            </div>
            <div class="card-body">
                <p style="color: #666; margin-bottom: 15px;">Use these accounts to test the receipt system:</p>
                
                <?php
                // Find all users with rentals
                $result = $pdo->query('
                    SELECT DISTINCT u.id, u.email, u.role, COUNT(r.id) as rental_count
                    FROM users u
                    LEFT JOIN rentals r ON u.id = r.user_id
                    WHERE u.role = "user"
                    GROUP BY u.id
                    HAVING COUNT(r.id) > 0
                    ORDER BY u.email
                ');
                
                $hasUsers = false;
                while ($user = $result->fetch(PDO::FETCH_ASSOC)) {
                    $hasUsers = true;
                    echo '<div style="background: #f8f9fa; padding: 10px; margin-bottom: 10px; border-radius: 4px; border-left: 4px solid #17a2b8;">';
                    echo '<p style="margin: 0;"><strong>' . htmlspecialchars($user['email']) . '</strong></p>';
                    echo '<p style="margin: 5px 0; font-size: 0.9em; color: #666;">Rentals: ' . $user['rental_count'] . '</p>';
                    echo '<p style="margin: 5px 0; font-size: 0.85em; color: #999;">Note: Password depends on your system setup</p>';
                    echo '</div>';
                }
                
                if (!$hasUsers) {
                    echo '<p class="status-error">No users found with rentals</p>';
                }
                ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5>Quick Test</h5>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-primary" onclick="testReceiptFunction()">
                    Test Receipt JavaScript Function
                </button>
                <div id="testOutput" style="margin-top: 15px;"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5>Troubleshooting</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Receipt button not showing:</strong> Make sure you're viewing rental_history.php and logged in with a user that has rentals</li>
                    <li><strong>Receipt button shows but modal doesn't appear:</strong> Check browser console (F12) for JavaScript errors</li>
                    <li><strong>Bootstrap not loading:</strong> Check if you have internet connection (CDN assets required)</li>
                    <li><strong>Modal shows but empty:</strong> The rentalData object may not be populated. Check console logs</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Receipt Modal for Testing -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h6 class="modal-title"><i class="bi bi-receipt"></i> Rental Receipt</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent" style="background: #f8f9fa;">
                    <!-- Receipt will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test rental data
        const testRentalData = {
            "id": 31,
            "rent_date": "2026-02-16 04:57:56",
            "due_date": "2026-02-17 04:57:56",
            "return_date": null,
            "status": "active",
            "duration_days": 1,
            "quantity": 2,
            "payment_method": "online",
            "title": "The 48 Laws of Power",
            "isbn": "978-1861972781",
            "price": "300.00",
            "penalty_amount": null,
            "penalty_paid": null
        };

        function testReceiptFunction() {
            const output = document.getElementById('testOutput');
            try {
                output.innerHTML = '<p class="text-info">Testing...</p>';
                
                // Test 1: Check Modal Element
                const modalEl = document.getElementById('receiptModal');
                if (!modalEl) throw new Error('Modal element not found');
                output.innerHTML += '<p class="status-ok">✓ Modal element exists</p>';
                
                // Test 2: Check Content Element
                const contentEl = document.getElementById('receiptContent');
                if (!contentEl) throw new Error('Content element not found');
                output.innerHTML += '<p class="status-ok">✓ Content element exists</p>';
                
                // Test 3: Check Bootstrap
                if (typeof bootstrap === 'undefined') throw new Error('Bootstrap library not loaded');
                output.innerHTML += '<p class="status-ok">✓ Bootstrap library loaded</p>';
                
                // Test 4: Display Receipt
                const receiptHTML = '<div style="padding: 20px;"><h4>📋 Test Receipt</h4><p>Rental ID: ' + testRentalData.id + '</p><p>Book: ' + testRentalData.title + '</p><p>Price: ₱' + testRentalData.price + '</p></div>';
                contentEl.innerHTML = receiptHTML;
                output.innerHTML += '<p class="status-ok">✓ Receipt HTML injected</p>';
                
                // Test 5: Show Modal
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
                output.innerHTML += '<p class="status-ok">✅ SUCCESS: Modal displayed</p>';
                output.innerHTML += '<p style="color: #666; margin-top: 10px;">The receipt system is working! Check that you\'re logged in and have rentals.</p>';
                
            } catch (err) {
                output.innerHTML += '<p class="status-error">❌ Error: ' + err.message + '</p>';
                console.error('Test Error:', err);
            }
        }
    </script>
</body>
</html>
