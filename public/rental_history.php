<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Models/Rental.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';

$auth = Auth::getInstance();
$currentUser = $auth->currentUser();

// Redirect if not logged in
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Only regular users can view their own rental history
// Admin/Staff can view from dashboard/rentals.php
if ($currentUser['role'] !== 'user') {
    header('Location: dashboard.php');
    exit;
}

// Handle rental submission (POST from payment form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $error = null;
    try {
        // Validate required fields
        $bookId = isset($_POST['book_id']) ? intval($_POST['book_id']) : null;
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : $currentUser['id'];
        $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : null;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $rentDate = isset($_POST['rent_date']) ? trim($_POST['rent_date']) : null;
        $dueDate = isset($_POST['due_date']) ? trim($_POST['due_date']) : null;
        
        // Debug log
        error_log("Rental POST received: book_id=$bookId, user_id=$userId, qty=$quantity, rentDate=$rentDate, dueDate=$dueDate, method=$paymentMethod");
        
        // Validate book and user IDs
        if (!$bookId) {
            throw new Exception('Book ID is required');
        }
        
        if (!$userId) {
            throw new Exception('User ID is required');
        }
        
        // Regular users can only rent for themselves
        if ($currentUser['role'] === 'user' && $userId !== $currentUser['id']) {
            throw new Exception('You can only rent for yourself');
        }
        
        // Validate dates
        if (!$rentDate || !$dueDate) {
            throw new Exception('Rent and due dates are required');
        }
        
        // Calculate duration in days
        $rentDateObj = new DateTime($rentDate);
        $dueDateObj = new DateTime($dueDate);
        $interval = $rentDateObj->diff($dueDateObj);
        $durationDays = $interval->days;
        
        if ($durationDays < 1) {
            throw new Exception('Due date must be after rent date');
        }
        
        // Validate payment method
        if (!$paymentMethod || !in_array($paymentMethod, ['card', 'online'])) {
            throw new Exception('Invalid or missing payment method');
        }
        
        // Prepare payment details
        $cardDetails = [];
        $cashReceived = null;
        $onlineTxn = null;
        
        if ($paymentMethod === 'card') {
            $cardDetails = [
                'card_number' => isset($_POST['card_number']) ? trim($_POST['card_number']) : null,
                'card_holder' => isset($_POST['card_holder']) ? trim($_POST['card_holder']) : null,
                'card_expiry' => isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : null,
                'card_cvv' => isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : null,
            ];
        } elseif ($paymentMethod === 'online') {
            $onlineTxn = isset($_POST['online_transaction_no']) ? trim($_POST['online_transaction_no']) : null;
        }
        
        // Create rental using the Rental model
        $rentalModel = new Rental();
        $rentalId = $rentalModel->rentBook(
            $userId,
            $bookId,
            $durationDays,
            $quantity,
            $cashReceived,
            $paymentMethod,
            $cardDetails,
            $onlineTxn
        );
        
        if (!$rentalId) {
            throw new Exception('Failed to create rental - no ID returned');
        }
        
        error_log("Rental created successfully with ID: $rentalId");
        Flash::add('success', 'Rental created successfully! Your rental is now pending admin approval.');
        header('Location: rental_history.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Rental creation error: " . $e->getMessage());
        Flash::add('error', 'Failed to create rental: ' . htmlspecialchars($e->getMessage()));
        // Stay on rental_history page to show error (but don't recurse into GET handler)
        // Just continue to load the page with the error message displayed
    }
}

// Get user's rentals from database
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
$rentals = $stmt->fetchAll();

// Separate rentals by status
$pendingRentals = [];
$activeRentals = [];
$returnedRentals = [];
$overdueRentals = [];

foreach ($rentals as $rental) {
    if ($rental['status'] === 'pending') {
        $pendingRentals[] = $rental;
    } elseif ($rental['status'] === 'active') {
        $activeRentals[] = $rental;
    } elseif ($rental['status'] === 'overdue') {
        $overdueRentals[] = $rental;
    } elseif ($rental['status'] === 'returned') {
        $returnedRentals[] = $rental;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rental History - BookRent</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/bookrent_db/public/css/style.css">
  <style>
    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    .status-active { background-color: #cfe2ff; color: #084298; }
    .status-returned { background-color: #d1e7dd; color: #0f5132; }
    .status-overdue { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    
    .payment-method-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
    }
    .payment-cash { background-color: #d4edda; color: #155724; }
    .payment-card { background-color: #cce5ff; color: #0c5de4; }
    .payment-online { background-color: #e2e3e5; color: #383d41; }
    
    .section-header {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      color: #4f03c8;
      margin-top: 2rem;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid #4f03c8;
      padding-bottom: 0.5rem;
    }
    
    .rental-table {
      font-size: 0.95rem;
    }
    
    .rental-table thead {
      background-color: #f8f9fa;
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #6c757d;
    }
    
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }
  </style>
</head>
<body>
  <!-- Top Navigation Bar -->
  <nav class="navbar navbar-light bg-white border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="home.php">
        <i class="bi bi-book-half me-2" style="font-size:1.4rem; color:#4f03c8;"></i>
        <span class="h6 mb-0" style="color:#4f03c8;font-weight:700;">BookRent</span>
      </a>
      <div class="d-flex align-items-center">
        <a href="home.php" class="nav-link me-3">
          <i class="bi bi-house-door me-1"></i> Home
        </a>
        <a href="rental_history.php" class="nav-link me-3 active">
          <i class="bi bi-clock-history me-1"></i> Rentals
        </a>
        <?php if ($currentUser): ?>
          <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
          </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <?php Flash::init(); echo Flash::render(); ?>
    <div class="row">
      <div class="col-12">
        <h1 style="font-family:'Poppins',sans-serif;font-weight:700;color:#4f03c8;margin-bottom:2rem;">
          <i class="bi bi-clock-history me-2"></i>Rental History
        </h1>

        <!-- Pending Rentals Section (Awaiting Approval) -->
        <div class="section-header">
          <i class="bi bi-clock-circle me-2"></i>Pending Rentals (Awaiting Approval)
        </div>

        <?php if (empty($pendingRentals)): ?>
          <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <p>No pending rentals</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover rental-table">
              <thead>
                <tr>
                  <th>Book Title</th>
                  <th>ISBN</th>
                  <th>Qty</th>
                  <th>Rent Date</th>
                  <th>Due Date</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th style="width: 50px;">Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingRentals as $rental): 
                  $statusClass = 'status-' . $rental['status'];
                ?>
                <tr>
                  <td><strong><?=htmlspecialchars($rental['title'])?></strong></td>
                  <td><code><?=htmlspecialchars($rental['isbn'])?></code></td>
                  <td align="center"><?=intval($rental['quantity'])?></td>
                  <td><?=date('M d, Y', strtotime($rental['rent_date']))?></td>
                  <td><?=date('M d, Y', strtotime($rental['due_date']))?></td>
                  <td>
                    <?php if ($rental['payment_method']): ?>
                      <span class="payment-method-badge payment-<?=$rental['payment_method']?>">
                        <?=ucfirst($rental['payment_method'])?>
                      </span>
                      <?php if ($rental['payment_method'] === 'cash' && $rental['change_amount'] !== null): ?>
                        <br><small>Change: ₱<?=number_format($rental['change_amount'], 2)?></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="status-badge <?=$statusClass?>">
                      <?=ucfirst($rental['status'])?> ⏳
                    </span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-info text-white" onclick="showSimpleReceipt(<?=$rental['id']?>, '<?=addslashes($rental['title'])?>', '<?=addslashes($rental['isbn'])?>', <?=$rental['price']?>, <?=$rental['quantity']?>, '<?=$rental['rent_date']?>', '<?=$rental['due_date']?>', '<?=$rental['return_date']?>', '<?=$rental['status']?>', '<?=$rental['payment_method']?>', <?=$rental['penalty_amount'] ?? 0?>)" title="View Receipt" style="cursor: pointer;"><i class="bi bi-receipt me-1"></i>View</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <!-- Active Rentals Section -->
        <div class="section-header">
          <i class="bi bi-play-circle me-2"></i>Active Rentals
        </div>

        <?php if (empty($activeRentals)): ?>
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>No active rentals at the moment</p>
            <a href="home.php" class="btn btn-primary btn-sm">Browse Books</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover rental-table">
              <thead>
                <tr>
                  <th>Book Title</th>
                  <th>ISBN</th>
                  <th>Qty</th>
                  <th>Rent Date</th>
                  <th>Due Date</th>
                  <th>Days Left</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th style="width: 50px;">Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activeRentals as $rental): 
                  $statusClass = 'status-' . $rental['status'];
                  $dueDate = new DateTime($rental['due_date']);
                  $today = new DateTime();
                  $daysLeft = $dueDate->diff($today)->days;
                ?>
                <tr>
                  <td><strong><?=htmlspecialchars($rental['title'])?></strong></td>
                  <td><code><?=htmlspecialchars($rental['isbn'])?></code></td>
                  <td align="center"><?=intval($rental['quantity'])?></td>
                  <td><?=date('M d, Y', strtotime($rental['rent_date']))?></td>
                  <td><?=date('M d, Y', strtotime($rental['due_date']))?></td>
                  <td><span class="badge bg-info"><?=$daysLeft?> days</span></td>
                  <td>
                    <?php if ($rental['payment_method']): ?>
                      <span class="payment-method-badge payment-<?=$rental['payment_method']?>">
                        <?=ucfirst($rental['payment_method'])?>
                      </span>
                      <?php if ($rental['payment_method'] === 'cash' && $rental['change_amount'] !== null): ?>
                        <br><small>Change: ₱<?=number_format($rental['change_amount'], 2)?></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="status-badge <?=$statusClass?>">
                      <?=ucfirst($rental['status'])?>
                    </span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-info text-white" onclick="showSimpleReceipt(<?=$rental['id']?>, '<?=addslashes($rental['title'])?>', '<?=addslashes($rental['isbn'])?>', <?=$rental['price']?>, <?=$rental['quantity']?>, '<?=$rental['rent_date']?>', '<?=$rental['due_date']?>', '<?=$rental['return_date']?>', '<?=$rental['status']?>', '<?=$rental['payment_method']?>', <?=$rental['penalty_amount'] ?? 0?>)" title="View Receipt" style="cursor: pointer;"><i class="bi bi-receipt me-1"></i>View</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <!-- Overdue Rentals Section -->
        <?php if (!empty($overdueRentals)): ?>
        <div class="section-header" style="color: #dc3545; border-bottom-color: #dc3545;">
          <i class="bi bi-exclamation-triangle me-2"></i>Overdue Rentals ⚠️
        </div>

        <div class="table-responsive">
          <table class="table table-hover rental-table">
            <thead class="table-danger">
              <tr>
                <th>Book Title</th>
                <th>ISBN</th>
                <th>Qty</th>
                <th>Due Date</th>
                <th>Days Overdue</th>
                <th>Payment</th>
                <th>Status</th>
                <th style="width: 50px;">Receipt</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($overdueRentals as $rental): 
                $statusClass = 'status-' . $rental['status'];
                $dueDate = new DateTime($rental['due_date']);
                $today = new DateTime();
                $daysOverdue = $dueDate->diff($today)->days;
              ?>
              <tr>
                <td><strong><?=htmlspecialchars($rental['title'])?></strong></td>
                <td><code><?=htmlspecialchars($rental['isbn'])?></code></td>
                <td align="center"><?=intval($rental['quantity'])?></td>
                <td><?=date('M d, Y', strtotime($rental['due_date']))?></td>
                <td><span class="badge bg-danger"><?=$daysOverdue?> days</span></td>
                <td>
                  <?php if ($rental['payment_method']): ?>
                    <span class="payment-method-badge payment-<?=$rental['payment_method']?>">
                      <?=ucfirst($rental['payment_method'])?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="status-badge <?=$statusClass?>">
                    <?=ucfirst($rental['status'])?>
                  </span>
                </td>
                <td>
                  <button type="button" class="btn btn-sm btn-info text-white" onclick="showSimpleReceipt(<?=$rental['id']?>, '<?=addslashes($rental['title'])?>', '<?=addslashes($rental['isbn'])?>', <?=$rental['price']?>, <?=$rental['quantity']?>, '<?=$rental['rent_date']?>', '<?=$rental['due_date']?>', '<?=$rental['return_date']?>', '<?=$rental['status']?>', '<?=$rental['payment_method']?>', <?=$rental['penalty_amount'] ?? 0?>)" title="View Receipt" style="cursor: pointer;"><i class="bi bi-receipt me-1"></i>View</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <!-- Returned Rentals Section -->
        <div class="section-header">
          <i class="bi bi-check-circle me-2"></i>Completed Rentals
        </div>

        <?php if (empty($returnedRentals)): ?>
          <div class="empty-state">
            <i class="bi bi-check-all"></i>
            <p>No completed rentals yet</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover rental-table">
              <thead>
                <tr>
                  <th>Book Title</th>
                  <th>ISBN</th>
                  <th>Qty</th>
                  <th>Rent Date</th>
                  <th>Due Date</th>
                  <th>Return Date</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th style="width: 50px;">Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($returnedRentals as $rental): 
                  $statusClass = 'status-' . $rental['status'];
                ?>
                <tr>
                  <td><strong><?=htmlspecialchars($rental['title'])?></strong></td>
                  <td><code><?=htmlspecialchars($rental['isbn'])?></code></td>
                  <td align="center"><?=intval($rental['quantity'])?></td>
                  <td><?=date('M d, Y', strtotime($rental['rent_date']))?></td>
                  <td><?=date('M d, Y', strtotime($rental['due_date']))?></td>
                  <td>
                    <?php if ($rental['return_date']): ?>
                      <?=date('M d, Y', strtotime($rental['return_date']))?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($rental['payment_method']): ?>
                      <span class="payment-method-badge payment-<?=$rental['payment_method']?>">
                        <?=ucfirst($rental['payment_method'])?>
                      </span>
                      <?php if ($rental['payment_method'] === 'cash' && $rental['change_amount'] !== null): ?>
                        <br><small>Change: ₱<?=number_format($rental['change_amount'], 2)?></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="status-badge <?=$statusClass?>">
                      <?=ucfirst($rental['status'])?>
                    </span>
                    <?php if ($rental['penalty_amount'] && $rental['penalty_paid'] == 0): ?>
                      <br><small class="text-danger">
                        <i class="bi bi-exclamation-triangle"></i> ₱<?=number_format($rental['penalty_amount'], 2)?>
                      </small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-info text-white" onclick="showSimpleReceipt(<?=$rental['id']?>, '<?=addslashes($rental['title'])?>', '<?=addslashes($rental['isbn'])?>', <?=$rental['price']?>, <?=$rental['quantity']?>, '<?=$rental['rent_date']?>', '<?=$rental['due_date']?>', '<?=$rental['return_date']?>', '<?=$rental['status']?>', '<?=$rental['payment_method']?>', <?=$rental['penalty_amount'] ?? 0?>)" title="View Receipt" style="cursor: pointer;"><i class="bi bi-receipt me-1"></i>View</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="mt-4">
          <a href="home.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Book Gallery
          </a>
        </div>
      </div>
    </div>
  </main>

  <!-- Simple Receipt Modal (No Bootstrap Required) -->
  <div id="simpleReceiptModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; border-radius:8px; max-width:700px; width:90%; max-height:80vh; overflow-y:auto; box-shadow:0 0 20px rgba(0,0,0,0.3);">
      <div style="padding:20px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
        <h4 style="margin:0; color:#333;">Rental Receipt</h4>
        <button onclick="closeSimpleReceiptModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">&times;</button>
      </div>
      <div id="simpleReceiptContent" style="padding:20px; background:#f8f9fa;"></div>
      <div style="padding:15px; border-top:1px solid #ddd; text-align:right; background:white;">
        <button onclick="printSimpleReceipt()" style="background:#007bff; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer; margin-right:10px;">🖨️ Print</button>
        <button onclick="closeSimpleReceiptModal()" style="background:#6c757d; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer;">Close</button>
      </div>
    </div>
  </div>

  <footer class="bg-dark text-light text-center py-3 mt-4">
    <div class="container">
      <p class="mb-0">&copy; <?=date('Y')?> BookRent - Modern Book Rental System</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // Simple Receipt Modal Functions (No Dependencies)
  function showSimpleReceipt(rentalId, title, isbn, price, quantity, rentDate, dueDate, returnDate, status, paymentMethod, penaltyAmount) {
    try {
      console.log('showSimpleReceipt called:', {rentalId, title, quantity, price});
      
      // Validate inputs
      if (!rentalId) throw new Error('Invalid rental ID');
      
      const receiptPrice = parseFloat(price || 0);
      const receiptQty = parseInt(quantity || 1);
      const receiptTotal = receiptPrice * receiptQty;
      const penalty = parseFloat(penaltyAmount || 0);
      const finalAmount = receiptTotal + penalty;
      
      // Build receipt HTML
      let html = '<div style="font-family:Arial, sans-serif;">';
      html += '<div style="text-align:center; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #4f03c8;">';
      html += '<h3 style="margin:0; color:#4f03c8;">📋 RENTAL RECEIPT</h3>';
      html += '<p style="margin:5px 0; color:#666;">Transaction #' + String(rentalId).padStart(5, '0') + '</p>';
      html += '</div>';
      
      html += '<div style="margin-bottom:15px;">';
      html += '<h6 style="color:#333; border-bottom:2px solid #ddd; padding-bottom:5px;">📚 BOOK INFORMATION</h6>';
      html += '<table style="width:100%; border-collapse:collapse;">';
      html += '<tr><td style="padding:5px 0;"><strong>Title:</strong></td><td style="text-align:right;">' + (title || 'N/A') + '</td></tr>';
      html += '<tr><td style="padding:5px 0;"><strong>ISBN:</strong></td><td style="text-align:right;"><code>' + (isbn || 'N/A') + '</code></td></tr>';
      html += '</table>';
      html += '</div>';
      
      html += '<div style="margin-bottom:15px; padding:10px; background:#f8f9fa; border-left:4px solid #28a745;">';
      html += '<h6 style="color:#333; margin-bottom:10px;">📅 RENTAL PERIOD</h6>';
      html += '<table style="width:100%; border-collapse:collapse; font-size:0.9em;">';
      html += '<tr><td style="padding:3px 0;"><strong>Rent Date:</strong></td><td style="text-align:right;">' + new Date(rentDate).toLocaleDateString() + '</td></tr>';
      html += '<tr><td style="padding:3px 0;"><strong>Due Date:</strong></td><td style="text-align:right;">' + new Date(dueDate).toLocaleDateString() + '</td></tr>';
      html += '<tr><td style="padding:3px 0;"><strong>Return Date:</strong></td><td style="text-align:right;">' + (returnDate ? new Date(returnDate).toLocaleDateString() : 'Not returned') + '</td></tr>';
      html += '<tr><td style="padding:3px 0;"><strong>Status:</strong></td><td style="text-align:right;"><strong>' + (status || 'N/A').toUpperCase() + '</strong></td></tr>';
      html += '</table>';
      html += '</div>';
      
      html += '<div style="margin-bottom:15px; padding:10px; background:#fff9e6; border-left:4px solid #fd7e14;">';
      html += '<h6 style="color:#333; margin-bottom:10px;">💰 PRICING DETAILS</h6>';
      html += '<table style="width:100%; border-collapse:collapse; font-size:0.9em;">';
      html += '<tr><td><strong>Quantity:</strong></td><td style="text-align:right;">' + receiptQty + ' copy(ies)</td></tr>';
      html += '<tr><td><strong>Unit Price:</strong></td><td style="text-align:right;">₱' + receiptPrice.toFixed(2) + '</td></tr>';
      html += '<tr style="border-top:2px solid #fd7e14; border-bottom:2px solid #fd7e14;"><td style="padding:8px 0;"><strong>Subtotal:</strong></td><td style="text-align:right;"><strong>₱' + receiptTotal.toFixed(2) + '</strong></td></tr>';
      if (penalty > 0) {
        html += '<tr style="background:#fff3cd;"><td><strong>Penalty:</strong></td><td style="text-align:right;"><strong>₱' + penalty.toFixed(2) + '</strong></td></tr>';
        html += '<tr><td style="padding:8px 0;"><strong style="color:#4f03c8;">TOTAL:</strong></td><td style="text-align:right;"><strong style="color:#4f03c8; font-size:1.1em;">₱' + finalAmount.toFixed(2) + '</strong></td></tr>';
      } else {
        html += '<tr><td style="padding:8px 0;"><strong style="color:#4f03c8;">TOTAL:</strong></td><td style="text-align:right;"><strong style="color:#4f03c8; font-size:1.1em;">₱' + receiptTotal.toFixed(2) + '</strong></td></tr>';
      }
      html += '</table>';
      html += '</div>';
      
      html += '<div style="margin-bottom:15px; padding:10px; background:#e7f0ff; border-left:4px solid #007bff;">';
      html += '<h6 style="color:#333; margin-bottom:10px;">💳 PAYMENT METHOD</h6>';
      html += '<table style="width:100%; border-collapse:collapse; font-size:0.9em;">';
      html += '<tr><td><strong>Method:</strong></td><td style="text-align:right;"><strong>' + (paymentMethod || 'N/A').toUpperCase() + '</strong></td></tr>';
      html += '<tr><td><strong>Status:</strong></td><td style="text-align:right;"><span style="background:#d1e7dd; color:#0f5132; padding:2px 8px; border-radius:3px; font-size:0.85em;">✓ PAID</span></td></tr>';
      html += '</table>';
      html += '</div>';
      
      html += '<div style="text-align:center; color:#666; font-size:0.8em; margin-top:20px; padding-top:10px; border-top:1px solid #ddd;">';
      html += '<p style="margin:3px 0;"><strong>BookRent Store</strong></p>';
      html += '<p style="margin:3px 0;">123 Main Street, City Center | 📞 0912 345 6789</p>';
      html += '<p style="margin:6px 0; font-size:0.75em; color:#999;">Generated: ' + new Date().toLocaleString() + '</p>';
      html += '</div>';
      html += '</div>';
      
      // Show modal
      document.getElementById('simpleReceiptContent').innerHTML = html;
      document.getElementById('simpleReceiptModal').style.display = 'block';
      console.log('✅ Receipt modal shown');
      
    } catch (err) {
      console.error('Error in showSimpleReceipt:', err);
      alert('Error: ' + err.message);
    }
  }

  function closeSimpleReceiptModal() {
    document.getElementById('simpleReceiptModal').style.display = 'none';
  }

  function printSimpleReceipt() {
    const content = document.getElementById('simpleReceiptContent').innerHTML;
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Rental Receipt</title></head><body onload="window.print();window.close();">' + content + '</body></html>');
    printWindow.document.close();
  }

  // Also keep old functions for compatibility
  const rentalData = {};
  <?php foreach ($rentals as $rental): ?>
  rentalData["<?=$rental['id']?>"] = <?=json_encode($rental)?>;
  <?php endforeach; ?>

  function showReceipt(rentalId) {
    try {
      // Debug logging
      console.log('=== showReceipt() called with rentalId:', rentalId, 'Type:', typeof rentalId);
      console.log('rentalData object exists:', typeof rentalData !== 'undefined');
      console.log('rentalData keys:', Object.keys(rentalData || {}));
      console.log('rentalData values:', rentalData);
      
      // Get rental data from the stored object
      const r = rentalData[rentalId];
      console.log('Looking for rental with rentalId:', rentalId);
      console.log('Rental data retrieved:', r);
      
      if (!r || !r.id) {
        const msg = 'Error: No rental data found for ID ' + rentalId + '\n\nrentalData contains: ' + Object.keys(rentalData).length + ' records\n\nAvailable IDs: ' + Object.keys(rentalData).join(', ');
        alert(msg);
        console.error(msg);
        return;
      }
      console.log('✓ Rental data valid');

      // Format dates
      const rentDate = new Date(r.rent_date).toLocaleDateString();
      const dueDate = new Date(r.due_date).toLocaleDateString();
      const returnDate = r.return_date ? new Date(r.return_date).toLocaleDateString() : 'Not returned';
      
      // Calculate totals
      const price = parseFloat(r.price || 0);
      const quantity = parseInt(r.quantity || 1);
      const totalPrice = price * quantity;
      const penaltyAmount = parseFloat(r.penalty_amount || 0);
      const finalAmount = totalPrice + penaltyAmount;
      
      // Calculate days rented
      const rentDateObj = new Date(r.rent_date);
      const dueDateObj = new Date(r.due_date);
      const daysRented = Math.ceil((dueDateObj - rentDateObj) / (1000 * 60 * 60 * 24));

      // Build receipt HTML with simpler approach
      let receiptHTML = '<div style="padding: 20px; background: white; border-radius: 4px;">';
      
      // Header
      receiptHTML += '<div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4f03c8; padding-bottom: 15px;">';
      receiptHTML += '<h3 style="margin: 0; color: #4f03c8;">📋 RENTAL RECEIPT</h3>';
      receiptHTML += '<p style="margin: 5px 0; color: #666;">Transaction #' + String(r.id).padStart(5, '0') + '</p>';
      receiptHTML += '</div>';
      
      // Book Info
      receiptHTML += '<div style="margin-bottom: 15px;">';
      receiptHTML += '<h6 style="color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px;">📚 BOOK INFORMATION</h6>';
      receiptHTML += '<table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">';
      receiptHTML += '<tr><td style="padding: 5px 0;"><strong>Title:</strong></td><td style="text-align: right;">' + (r.title || 'N/A') + '</td></tr>';
      receiptHTML += '<tr><td style="padding: 5px 0;"><strong>ISBN:</strong></td><td style="text-align: right;"><code>' + (r.isbn || 'N/A') + '</code></td></tr>';
      receiptHTML += '</table>';
      receiptHTML += '</div>';
      
      // Rental Period
      receiptHTML += '<div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #28a745;">';
      receiptHTML += '<h6 style="color: #333; margin-bottom: 10px;">📅 RENTAL PERIOD</h6>';
      receiptHTML += '<table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Rent Date:</strong></td><td style="text-align: right;">' + rentDate + '</td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Due Date:</strong></td><td style="text-align: right;">' + dueDate + '</td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Duration:</strong></td><td style="text-align: right;"><strong>' + daysRented + ' day(s)</strong></td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Return Date:</strong></td><td style="text-align: right;">' + returnDate + '</td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Status:</strong></td><td style="text-align: right;"><strong>' + (r.status || 'UNKNOWN').toUpperCase() + '</strong></td></tr>';
      receiptHTML += '</table>';
      receiptHTML += '</div>';
      
      // Pricing
      receiptHTML += '<div style="margin-bottom: 15px; padding: 10px; background: #fff9e6; border-left: 4px solid #fd7e14;">';
      receiptHTML += '<h6 style="color: #333; margin-bottom: 10px;">💰 PRICING DETAILS</h6>';
      receiptHTML += '<table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Book:</strong></td><td style="text-align: right;">' + (r.title || 'N/A') + '</td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Quantity:</strong></td><td style="text-align: right;">' + quantity + ' copy(ies)</td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Unit Price:</strong></td><td style="text-align: right;">₱' + price.toFixed(2) + '</td></tr>';
      receiptHTML += '<tr style="border-top: 2px solid #fd7e14; border-bottom: 2px solid #fd7e14;"><td style="padding: 8px 0;"><strong>Subtotal:</strong></td><td style="text-align: right;"><strong>₱' + totalPrice.toFixed(2) + '</strong></td></tr>';
      if (penaltyAmount > 0) {
        receiptHTML += '<tr style="background: #fff3cd;"><td style="padding: 5px 0;"><strong>Penalty:</strong></td><td style="text-align: right;"><strong>₱' + penaltyAmount.toFixed(2) + '</strong></td></tr>';
        receiptHTML += '<tr style="background: #fffbea;"><td style="padding: 8px 0;"><strong style="color: #4f03c8;">TOTAL:</strong></td><td style="text-align: right;"><strong style="color: #4f03c8; font-size: 1.1em;">₱' + finalAmount.toFixed(2) + '</strong></td></tr>';
      } else {
        receiptHTML += '<tr><td style="padding: 8px 0;"><strong style="color: #4f03c8;">TOTAL:</strong></td><td style="text-align: right;"><strong style="color: #4f03c8; font-size: 1.1em;">₱' + totalPrice.toFixed(2) + '</strong></td></tr>';
      }
      receiptHTML += '</table>';
      receiptHTML += '</div>';
      
      // Payment Method
      receiptHTML += '<div style="margin-bottom: 15px; padding: 10px; background: #e7f0ff; border-left: 4px solid #007bff;">';
      receiptHTML += '<h6 style="color: #333; margin-bottom: 10px;">💳 PAYMENT METHOD</h6>';
      receiptHTML += '<table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Method:</strong></td><td style="text-align: right;"><strong>' + (r.payment_method ? r.payment_method.toUpperCase() : 'N/A') + '</strong></td></tr>';
      receiptHTML += '<tr><td style="padding: 3px 0;"><strong>Status:</strong></td><td style="text-align: right;"><span style="background: #d1e7dd; color: #0f5132; padding: 2px 8px; border-radius: 3px; font-size: 0.85em;">✓ PAID</span></td></tr>';
      receiptHTML += '</table>';
      receiptHTML += '</div>';
      
      // Footer
      receiptHTML += '<div style="text-align: center; color: #666; font-size: 0.8em; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd;">';
      receiptHTML += '<p style="margin: 3px 0;"><strong>BookRent Store</strong></p>';
      receiptHTML += '<p style="margin: 3px 0;">123 Main Street, City Center | 📞 0912 345 6789</p>';
      receiptHTML += '<p style="margin: 6px 0; font-size: 0.75em; color: #999;">Generated: ' + new Date().toLocaleString() + '</p>';
      receiptHTML += '</div>';
      receiptHTML += '</div>';
    
      // Update modal with receipt
      const receiptContentEl = document.getElementById('receiptContent');
      if (!receiptContentEl) {
        alert('CRITICAL ERROR: Receipt modal element (id="receiptContent") not found!\nThe modal HTML may be missing or corrupted.\nTry refreshing the page.');
        window.console && console.error('CRITICAL: receiptContent element not found in DOM');
        return;
      }
      
      receiptContentEl.innerHTML = receiptHTML;
      console.log('✓ Receipt HTML injected into modal, length:', receiptHTML.length);
      
      // Show modal
      const modalEl = document.getElementById('receiptModal');
      if (!modalEl) {
        alert('CRITICAL ERROR: Receipt modal element (id="receiptModal") not found!\nThe modal HTML may be missing.\nTry refreshing the page.');
        window.console && console.error('CRITICAL: receiptModal element not found in DOM');
        return;
      }
      
      console.log('Bootstrap library available:', typeof bootstrap !== 'undefined');
      
      if (typeof bootstrap === 'undefined') {
        alert('CRITICAL ERROR: Bootstrap library not loaded!\nThe page may not have loaded properly.\nTry refreshing the page.');
        console.error('CRITICAL: Bootstrap library not available');
        return;
      }
      
      const modal = new bootstrap.Modal(modalEl);
      console.log('✓ Bootstrap Modal instance created');
      
      modal.show();
      console.log('✅ SUCCESS: Modal displayed');
      
    } catch (err) {
      console.error('ERROR in showReceipt():', err.message, err.stack);
      alert('ERROR: ' + err.message + '\n\nCheck browser console (F12) for details.');
    }
  }

  function printReceipt() {
    const printWindow = window.open('', '', 'height=600,width=800');
    const receiptContent = document.getElementById('receiptContent').innerHTML;
    printWindow.document.write(`
      <html>
      <head>
        <title>Rental Receipt</title>
        <style>
          body { font-family: 'Courier New', monospace; margin: 20px; }
          .receipt { max-width: 500px; margin: 0 auto; }
        </style>
      </head>
      <body>
        <div class="receipt">${receiptContent}</div>
        <script>window.print(); window.close();</script>
      </body>
      </html>
    `);
    printWindow.document.close();
  }
  </script>
</html>
