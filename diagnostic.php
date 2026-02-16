<?php
// Comprehensive diagnostic page
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

$userId = $_SESSION['user_id'];

// Get rentals for this user
$pdo = \Database::getInstance()->pdo();
$stmt = $pdo->prepare('
    SELECT 
        r.id, 
        r.rent_date, 
        r.due_date, 
        r.return_date, 
        r.status, 
        r.duration_days,
        r.quantity,
        r.payment_method,
        b.title,
        b.isbn,
        b.price,
        p.amount as penalty_amount,
        p.paid as penalty_paid
    FROM rentals r
    JOIN books b ON r.book_id = b.id
    LEFT JOIN penalties p ON r.id = p.rental_id
    WHERE r.user_id = :user_id
    ORDER BY r.rent_date DESC
    LIMIT 5
');
$stmt->execute(['user_id' => $userId]);
$rentals = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt Diagnostic Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body style="padding: 20px;">

<div class="container">
    <h2>🔧 Receipt System Diagnostic</h2>
    
    <div class="alert alert-info">
        <p><strong>User ID:</strong> <?php echo $userId; ?></p>
        <p><strong>Rentals Found:</strong> <?php echo count($rentals); ?></p>
    </div>

    <?php if (empty($rentals)): ?>
        <div class="alert alert-danger">
            <h4>❌ NO RENTALS FOUND</h4>
            <p>You don't have any rentals in the system. You cannot test the receipt feature.</p>
            <p><a href="home.php" class="btn btn-primary">Go to Book Gallery to Rent a Book</a></p>
        </div>
    <?php else: ?>
        <h4>✅ Found <?php echo count($rentals); ?> Rental(s)</h4>
        
        <table class="table table-bordered mb-4">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Price</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $r): ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td><?php echo htmlspecialchars($r['title']); ?></td>
                    <td><span class="badge bg-warning"><?php echo $r['status']; ?></span></td>
                    <td>₱<?php echo number_format($r['price'], 2); ?></td>
                    <td><?php echo date('M d, Y', strtotime($r['rent_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4>Test Receipt Display</h4>
        <p>Click the button below to test if the receipt modal works:</p>
        
        <div class="mb-3">
            <?php foreach ($rentals as $r): ?>
                <button class="btn btn-info text-white me-2 mb-2" onclick="testReceipt(<?php echo $r['id']; ?>)">
                    Test Receipt #<?php echo $r['id']; ?> - <?php echo htmlspecialchars($r['title']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="receiptModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-receipt"></i> Rental Receipt</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="receiptContent" style="background: #f8f9fa; max-height: 600px; overflow-y: auto;">
                        <p>Content will appear here...</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="printReceipt()"><i class="bi bi-printer"></i> Print</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Console -->
        <div style="margin-top: 40px; padding: 20px; background: #1e1e1e; border-radius: 5px; color: #00ff00; font-family: monospace; font-size: 0.8em;">
            <h5 style="color: #00ff00;">📋 Debug Console:</h5>
            <div id="debugConsole" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;"></div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Redirect console to debug div
const debugConsole = document.getElementById('debugConsole');
const originalLog = console.log;
const originalError = console.error;

console.log = function(...args) {
    const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a)).join(' ');
    debugConsole.textContent += '[LOG] ' + msg + '\n';
    debugConsole.scrollTop = debugConsole.scrollHeight;
    originalLog(...args);
};

console.error = function(...args) {
    const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a)).join(' ');
    debugConsole.textContent += '[ERROR] ' + msg + '\n';
    debugConsole.scrollTop = debugConsole.scrollHeight;
    originalError(...args);
};

// Store all rentals in a JavaScript object for easy access
const rentalData = {
    <?php foreach ($rentals as $rental): ?>
    "<?=$rental['id']?>": <?=json_encode($rental)?>,
    <?php endforeach; ?>
};

console.log('======== DIAGNOSTIC START ========');
console.log('Bootstrap loaded:', typeof bootstrap !== 'undefined');
console.log('rentalData object keys:', Object.keys(rentalData));
console.log('rentalData object:', rentalData);
console.log('receiptModal element exists:', !!document.getElementById('receiptModal'));
console.log('receiptContent element exists:', !!document.getElementById('receiptContent'));
console.log('======== DIAGNOSTIC END ========');

function testReceipt(rentalId) {
    console.log('\n======== testReceipt CALLED ========');
    console.log('rentalId:', rentalId);
    console.log('Type of rentalId:', typeof rentalId);
    
    try {
        // Get rental data
        const r = rentalData[rentalId];
        console.log('Rental data retrieved:', r);
        
        if (!r) {
            console.error('FATAL: Rental data not found for ID ' + rentalId);
            alert('ERROR: Rental data not found for ID ' + rentalId);
            return;
        }

        if (!r.id) {
            console.error('FATAL: Rental has no ID field');
            alert('ERROR: Rental has no ID field');
            return;
        }

        // Format dates
        const rentDate = new Date(r.rent_date).toLocaleDateString();
        const dueDate = new Date(r.due_date).toLocaleDateString();
        const returnDate = r.return_date ? new Date(r.return_date).toLocaleDateString() : 'Not returned';
        
        console.log('Dates formatted successfully');
        
        // Calculate totals
        const price = parseFloat(r.price || 0);
        const quantity = parseInt(r.quantity || 1);
        const totalPrice = price * quantity;
        const penaltyAmount = parseFloat(r.penalty_amount || 0);
        const finalAmount = totalPrice + penaltyAmount;
        
        console.log('Price:', price);
        console.log('Quantity:', quantity);
        console.log('Total Price:', totalPrice);
        console.log('Penalty:', penaltyAmount);

        // Build simple receipt HTML
        let receiptHTML = '<div style="padding: 15px;">';
        receiptHTML += '<h3 style="color: #4f03c8; text-align: center;">📋 RENTAL RECEIPT</h3>';
        receiptHTML += '<hr>';
        receiptHTML += '<p><strong>Transaction #:</strong> ' + r.id + '</p>';
        receiptHTML += '<p><strong>Book Title:</strong> ' + (r.title || 'N/A') + '</p>';
        receiptHTML += '<p><strong>ISBN:</strong> ' + (r.isbn || 'N/A') + '</p>';
        receiptHTML += '<p><strong>Rent Date:</strong> ' + rentDate + '</p>';
        receiptHTML += '<p><strong>Due Date:</strong> ' + dueDate + '</p>';
        receiptHTML += '<p><strong>Status:</strong> ' + (r.status || 'UNKNOWN').toUpperCase() + '</p>';
        receiptHTML += '<p><strong>Quantity:</strong> ' + quantity + '</p>';
        receiptHTML += '<p><strong>Unit Price:</strong> ₱' + price.toFixed(2) + '</p>';
        receiptHTML += '<p><strong>Total:</strong> <strong style="color: #4f03c8; font-size: 1.2em;">₱' + totalPrice.toFixed(2) + '</strong></p>';
        if (penaltyAmount > 0) {
            receiptHTML += '<p><strong>Penalty:</strong> ₱' + penaltyAmount.toFixed(2) + '</p>';
            receiptHTML += '<p><strong>GRAND TOTAL:</strong> <strong style="color: red;">₱' + finalAmount.toFixed(2) + '</strong></p>';
        }
        receiptHTML += '<hr>';
        receiptHTML += '<p><strong>Payment Method:</strong> ' + (r.payment_method || 'N/A').toUpperCase() + '</p>';
        receiptHTML += '<p style="color: #999; font-size: 0.85em;">Generated: ' + new Date().toLocaleString() + '</p>';
        receiptHTML += '</div>';
        
        console.log('Receipt HTML generated, length:', receiptHTML.length);

        // Update modal
        const receiptContentEl = document.getElementById('receiptContent');
        if (!receiptContentEl) {
            console.error('FATAL: receiptContent element not found in DOM');
            alert('ERROR: receiptContent element not found!');
            return;
        }
        
        console.log('receiptContent element found');
        receiptContentEl.innerHTML = receiptHTML;
        console.log('Receipt HTML injected into modal');

        // Get modal element
        const modalEl = document.getElementById('receiptModal');
        if (!modalEl) {
            console.error('FATAL: receiptModal element not found in DOM');
            alert('ERROR: receiptModal element not found!');
            return;
        }
        
        console.log('receiptModal element found');
        
        // Check if Bootstrap is available
        if (typeof bootstrap === 'undefined') {
            console.error('FATAL: Bootstrap not loaded');
            alert('ERROR: Bootstrap library not loaded!');
            return;
        }
        
        console.log('Bootstrap available, creating modal instance');
        
        // Show modal
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        console.log('✅ Modal shown successfully!');
        console.log('======== testReceipt COMPLETE ========\n');
        
    } catch (err) {
        console.error('EXCEPTION:', err.message);
        console.error('Stack:', err.stack);
        alert('EXCEPTION: ' + err.message);
    }
}

function printReceipt() {
    const printWindow = window.open('', '', 'height=600,width=800');
    const receiptContent = document.getElementById('receiptContent').innerHTML;
    printWindow.document.write(receiptContent);
    printWindow.document.close();
    printWindow.print();
}
</script>

</body>
</html>
