<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';

$auth = Auth::getInstance();
$currentUser = $auth->currentUser();

// Only continue if logged in
if (!$currentUser) {
    echo '<h1>Not logged in</h1>';
    echo '<p><a href="login.php">Login</a></p>';
    exit;
}

require_once __DIR__ . '/../src/Database.php';

$db = \Database::getInstance();
$pdo = $db->pdo();

// Get user's rentals (same query as rental_history.php)
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
        r.cash_received,
        r.change_amount,
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
');
$stmt->execute(['user_id' => $currentUser['id']]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rentals) === 0) {
    echo '<h1>No rentals found</h1>';
    echo '<p>You don\'t have any rentals. Go back and rent a book.</p>';
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug - Rental Receipt System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1>Debug - Rental Receipt System</h1>
        <hr>

        <h3>Your Rentals:</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Book</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $rental): ?>
                <tr>
                    <td><?= htmlspecialchars($rental['id']) ?></td>
                    <td><?= htmlspecialchars($rental['title']) ?></td>
                    <td><?= htmlspecialchars($rental['status']) ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-info" onclick="testReceipt(<?=$rental['id']?>)">
                            Test Receipt
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr>
        <h3>JavaScript Check:</h3>
        <p><strong>rentalData object should contain:</strong></p>
        <pre id="rentalDataCheck"></pre>
        
        <hr>
        <h3>Console Output:</h3>
        <div id="console" style="background: #222; color: #0f0; padding: 10px; font-family: monospace; height: 300px; overflow-y: auto; border: 1px solid #444;"></div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h6 class="modal-title">Rental Receipt</h6>
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
        // Capture console.log
        const consoleDiv = document.getElementById('console');
        const originalLog = console.log;
        const originalError = console.error;
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a)).join(' ');
            consoleDiv.innerHTML += '<div style="color: #0f0;">' + new Date().toLocaleTimeString() + ' | ' + msg + '</div>';
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a)).join(' ');
            consoleDiv.innerHTML += '<div style="color: #f00;">' + new Date().toLocaleTimeString() + ' | ERROR: ' + msg + '</div>';
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        };

        // Store all rentals in a JavaScript object for easy access
        const rentalData = {};
        <?php foreach ($rentals as $rental): ?>
        rentalData["<?=$rental['id']?>"] = <?=json_encode($rental)?>;
        <?php endforeach; ?>

        console.log('rentalData initialized with ' + Object.keys(rentalData).length + ' rentals');
        document.getElementById('rentalDataCheck').textContent = JSON.stringify(rentalData, null, 2);

        function testReceipt(rentalId) {
            console.log('=== testReceipt called with ID:', rentalId);
            
            try {
                const r = rentalData[rentalId];
                console.log('Rental data:', r);

                if (!r || !r.id) {
                    console.error('No rental data found for ID ' + rentalId);
                    alert('Error: No rental data found');
                    return;
                }

                // Build simple receipt
                let html = '<div style="padding: 20px;">';
                html += '<h4>Receipt for Rental #' + r.id + '</h4>';
                html += '<p><strong>Book:</strong> ' + r.title + '</p>';
                html += '<p><strong>Price:</strong> ₱' + r.price + '</p>';
                html += '<p><strong>Status:</strong> ' + r.status + '</p>';
                html += '</div>';

                document.getElementById('receiptContent').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
                modal.show();
                
                console.log('✅ Receipt modal displayed');
            } catch (err) {
                console.error('Error:', err.message);
                alert('Error: ' + err.message);
            }
        }
    </script>
</body>
</html>
