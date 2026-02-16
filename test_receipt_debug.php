<?php
// Test to verify receipt modal functionality

// Simulate rental data
$rental = [
    'id' => 31,
    'title' => 'The 48 Laws of Power',
    'isbn' => '978-1861972781',
    'price' => '300.00',
    'quantity' => 2,
    'rent_date' => '2026-02-16 04:57:56',
    'due_date' => '2026-02-17 04:57:56',
    'return_date' => null,
    'status' => 'pending',
    'payment_method' => 'online',
    'duration_days' => 1,
    'penalty_amount' => 0,
    'penalty_paid' => 0
];

$rentalJson = htmlspecialchars(json_encode($rental));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt Modal Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <h3>Receipt Modal Test</h3>
    <p>Rental data encoded: <code><?= $rentalJson ?></code></p>
    <button class="btn btn-info text-white" onclick="showReceipt(<?= $rentalJson ?>)">
        <i class="bi bi-receipt me-1"></i>View Receipt
    </button>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h6 class="modal-title"><i class="bi bi-receipt"></i> Rental Receipt</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent" style="background: #f8f9fa; font-family: 'Courier New', monospace; font-size: 0.95em;">
                    <!-- Receipt will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showReceipt(r) {
  try {
    console.log('Receipt data received:', r);
    console.log('Type of r:', typeof r);
    console.log('r.id:', r.id);
    
    if (!r || !r.id) {
      alert('Error: Invalid rental data - missing id');
      console.error('Invalid rental data:', r);
      return;
    }

    const receiptHTML = `
      <div style="padding: 20px; background: white; border-radius: 4px;">
        <h3>Receipt #${r.id}</h3>
        <p>Title: ${r.title}</p>
        <p>Status: ${r.status}</p>
        <p>Total: ₱${(r.price * r.quantity).toFixed(2)}</p>
      </div>
    `;
    
    const receiptContentEl = document.getElementById('receiptContent');
    if (!receiptContentEl) {
      console.error('receiptContent element not found');
      alert('Modal element error: receiptContent not found');
      return;
    }
    
    receiptContentEl.innerHTML = receiptHTML;
    console.log('HTML injected successfully');
    
    const modalEl = document.getElementById('receiptModal');
    if (!modalEl) {
      console.error('receiptModal not found');
      alert('Modal element error: receiptModal not found');
      return;
    }
    
    console.log('Creating Bootstrap modal...');
    const modal = new bootstrap.Modal(modalEl);
    console.log('Showing modal...');
    modal.show();
    console.log('Modal shown successfully');
  } catch (err) {
    console.error('Error in showReceipt:', err);
    console.error('Stack:', err.stack);
    alert('Error: ' + err.message);
  }
}
</script>
</body>
</html>
