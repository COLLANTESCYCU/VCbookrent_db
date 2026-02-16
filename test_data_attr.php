<?php
// Quick test of data attribute approach

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
    'duration_days' => 1
];

// Test the encoding
$encoded = htmlspecialchars(json_encode($rental));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt Data Attribute Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h3>Testing Data Attribute Approach</h3>
    
    <h5>Raw JSON:</h5>
    <pre><?php echo json_encode($rental, JSON_PRETTY_PRINT); ?></pre>
    
    <h5>HTML Encoded:</h5>
    <code><?= $encoded ?></code>
    
    <h5>Test Button:</h5>
    <button class="btn btn-info text-white" data-rental="<?= $encoded ?>" onclick="testParse(this)">Click to Test</button>

    <!-- Modal for test -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Parsed Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="testContent"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function testParse(btn) {
    try {
        console.log('Button clicked');
        const rentalJson = btn.getAttribute('data-rental');
        console.log('Raw JSON from attribute:', rentalJson);
        
        const r = JSON.parse(rentalJson);
        console.log('Parsed object:', r);
        
        const html = `
            <p><strong>ID:</strong> ${r.id}</p>
            <p><strong>Title:</strong> ${r.title}</p>
            <p><strong>Status:</strong> ${r.status}</p>
            <p><strong>Total:</strong> ₱${(r.price * r.quantity).toFixed(2)}</p>
        `;
        
        document.getElementById('testContent').innerHTML = html;
        console.log('Modal content updated');
        
        const modal = new bootstrap.Modal(document.getElementById('testModal'));
        modal.show();
        console.log('Modal shown');
    } catch (err) {
        console.error('Error:', err);
        alert('Error: ' + err.message);
    }
}
</script>
</body>
</html>
