<?php
// Test to diagnose receipt issue
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Database.php';

// Get sample rental data
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
    LIMIT 5
');
$stmt->execute();
$rentals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body style="padding: 20px;">

<div class="container">
    <h3>Receipt Modal Debug Test</h3>
    
    <div class="alert alert-info">
        <p><strong>Rentals Found:</strong> <?php echo count($rentals); ?></p>
    </div>

    <?php if (empty($rentals)): ?>
        <div class="alert alert-warning">
            <strong>No rentals in database!</strong> Cannot test.
        </div>
    <?php else: ?>
        <h5>Available Rentals:</h5>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $r): ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td><?php echo htmlspecialchars($r['title'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($r['status'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-info text-white" onclick="testReceipt(<?php echo $r['id']; ?>)">
                            View Receipt
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h6 class="modal-title"><i class="bi bi-receipt"></i> Rental Receipt</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent" style="background: #f8f9fa;">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="debug" style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
        <h5>Debug Console:</h5>
        <pre id="debugOutput" style="background: white; padding: 10px; border-radius: 3px; font-size: 0.85em; max-height: 300px; overflow-y: auto;"></pre>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Store all rentals in a JavaScript object for easy access
const rentalData = {
    <?php foreach ($rentals as $rental): ?>
    "<?=$rental['id']?>": <?=json_encode($rental)?>,
    <?php endforeach; ?>
};

// Redirect console.log to debug div
const debugOutput = document.getElementById('debugOutput');
const originalLog = console.log;
console.log = function(...args) {
    debugOutput.textContent += args.join(' ') + '\n';
    debugOutput.scrollTop = debugOutput.scrollHeight;
    originalLog(...args);
};

console.log('Page loaded');
console.log('rentalData keys:', Object.keys(rentalData));
console.log('rentalData:', rentalData);

function testReceipt(rentalId) {
    console.log('=== testReceipt called with ID:', rentalId);
    
    try {
        const r = rentalData[rentalId];
        console.log('Found rental data:', r);
        
        if (!r || !r.id) {
            console.log('ERROR: No rental found for ID ' + rentalId);
            alert('Error: Invalid rental data');
            return;
        }

        const receiptHTML = `
            <div style="padding: 20px; background: white; border-radius: 4px;">
                <h4>Receipt #${r.id}</h4>
                <p><strong>Title:</strong> ${r.title}</p>
                <p><strong>Status:</strong> ${r.status}</p>
                <p><strong>Price:</strong> ₱${r.price}</p>
                <p><strong>Quantity:</strong> ${r.quantity}</p>
            </div>
        `;
        
        const receiptContentEl = document.getElementById('receiptContent');
        if (!receiptContentEl) {
            console.log('ERROR: receiptContent element not found');
            return;
        }
        
        console.log('Updating receiptContent with HTML');
        receiptContentEl.innerHTML = receiptHTML;
        
        const modalEl = document.getElementById('receiptModal');
        if (!modalEl) {
            console.log('ERROR: receiptModal element not found');
            return;
        }
        
        console.log('Creating and showing modal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        console.log('Modal shown successfully!');
    } catch (err) {
        console.log('ERROR:', err.message);
        alert('Error: ' + err.message);
    }
}
</script>

</body>
</html>
