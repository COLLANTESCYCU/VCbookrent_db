<?php
// Simulate what rental_history.php does with data encoding

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
    'payment_method' => 'online'
];

// This is what's being used in HTML
$encoded = htmlspecialchars(json_encode($rental));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Attribute Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="padding: 20px;">

<div class="container">
    <h3>Debugging Data Attribute Encoding</h3>
    
    <div class="alert alert-info">
        <h5>Raw JSON:</h5>
        <code style="word-break: break-all;" id="rawJson"></code>
    </div>

    <div class="alert alert-warning">
        <h5>HTML Encoded (what PHP outputs):</h5>
        <code style="word-break: break-all;" id="encoded"></code>
    </div>

    <h5>Test Button:</h5>
    <button class="btn btn-info text-white" data-rental="<?= $encoded ?>" onclick="testClick(this)">
        Click to Test Receipt
    </button>

    <div id="result" style="margin-top: 20px;"></div>
</div>

<script>
document.getElementById('rawJson').textContent = JSON.stringify(<?= json_encode($rental) ?>);
document.getElementById('encoded').textContent = '<?= $encoded ?>';

function testClick(btn) {
    const result = document.getElementById('result');
    result.innerHTML = '';
    
    try {
        // This is what the receipt function does
        const rentalJson = btn.getAttribute('data-rental');
        console.log('Raw from attribute:', rentalJson);
        result.innerHTML += '<p><strong>Raw data:</strong> ' + rentalJson.substring(0, 100) + '...</p>';
        
        // Try to parse
        const r = JSON.parse(rentalJson);
        result.innerHTML += '<p style="color: green;"><strong>✓ Successfully parsed!</strong></p>';
        result.innerHTML += '<p>ID: ' + r.id + '</p>';
        result.innerHTML += '<p>Title: ' + r.title + '</p>';
    } catch (err) {
        result.innerHTML += '<p style="color: red;"><strong>✗ Parse Error:</strong> ' + err.message + '</p>';
        console.error('Error:', err);
    }
}
</script>

</body>
</html>
