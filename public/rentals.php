<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/RentalController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Models/Rental.php';
require_once __DIR__ . '/../src/Database.php';
$rctrl = new RentalController();
$uctrl = new UserController();
$bctrl = new BookController();
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['cancel_id'])) {
            $rctrl->cancel((int)$_POST['cancel_id']);
            Flash::add('success','Rental cancelled ✅');
        } elseif (isset($_POST['return_id'])) {
            $res = $rctrl->doReturn((int)$_POST['return_id'], $_POST['return_date'] ?? null);
            $msgText = 'Book returned';
            if ($res['overdue_days'] > 0) $msgText .= ' — Overdue: ' . intval($res['overdue_days']) . ' day(s)';
            if (!empty($res['penalty_id'])) $msgText .= ' — Penalty recorded (ID: ' . intval($res['penalty_id']) . ')';
            Flash::add('success', $msgText);
        } elseif (isset($_POST['rent'])) {
            $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            $cashReceived = !empty($_POST['cash_received']) ? (float)$_POST['cash_received'] : null;
            $paymentMethod = $_POST['payment_method'] ?? null;
            $cardDetails = [
                'card_number' => $_POST['card_number'] ?? null,
                'card_holder' => $_POST['card_holder'] ?? null,
                'card_expiry' => $_POST['card_expiry'] ?? null,
                'card_cvv' => $_POST['card_cvv'] ?? null
            ];
            $onlineTxn = $_POST['online_transaction_no'] ?? null;
            
            // Create single rental transaction with quantity - status is now active automatically
            $rentalId = $rctrl->rent((int)$_POST['user_id'], (int)$_POST['book_id'], (int)$_POST['duration'], $quantity, $cashReceived);
            
            // Automatically approve the rental (set status to active)
            try {
                $rentalModel = new Rental();
                $rentalModel->approveRental($rentalId);
            } catch (Exception $e) {
                error_log('Auto-approval error: ' . $e->getMessage());
            }
            
            // Show success message without approval notice
            if ($quantity > 1) {
                Flash::add('success', $quantity . ' copies rented successfully! ✅ Your rental is now active and ready for pickup at: <strong>Bookrent Store, 123 Main Street, City Center</strong>');
            } else {
                Flash::add('success','Book rented successfully! ✅ Your rental is now active and ready for pickup at: <strong>Bookrent Store, 123 Main Street, City Center</strong>');
            }
        } elseif (isset($_POST['edit_id'])) {
            // Edit rental
            $rentalId = (int)$_POST['edit_id'];
            $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $newStatus = $_POST['status'] ?? null;
            $notes = $_POST['notes'] ?? null;
            
            try {
                $db = Database::getInstance()->pdo();
                
                // Get current rental status before update
                $getCurrentStmt = $db->prepare('SELECT status, book_id, quantity FROM rentals WHERE id = :id');
                $getCurrentStmt->execute(['id' => $rentalId]);
                $currentRental = $getCurrentStmt->fetch();
                
                if (!$currentRental) {
                    throw new Exception('Rental not found');
                }
                
                // If status changed from pending to active, use approveRental method
                if ($currentRental['status'] === 'pending' && $newStatus === 'active') {
                    try {
                        $rentalModel = new Rental();
                        $rentalModel->approveRental($rentalId);
                        Flash::add('success', 'Rental approved and inventory updated ✅');
                    } catch (Exception $approveError) {
                        Flash::add('danger', 'Error approving rental: ' . $approveError->getMessage());
                    }
                } else {
                    // For other changes, just update the fields
                    $updateFields = [];
                    $params = ['id' => $rentalId];
                    
                    if ($dueDate) {
                        // Convert from datetime-local format (YYYY-MM-DDTHH:mm) to MySQL format (YYYY-MM-DD HH:mm:ss)
                        $dueDate = str_replace('T', ' ', $dueDate) . ':00';
                        $updateFields[] = 'due_date = :due_date';
                        $params['due_date'] = $dueDate;
                    }
                    if ($newStatus && $newStatus !== $currentRental['status']) {
                        $updateFields[] = 'status = :status';
                        $params['status'] = $newStatus;
                    }
                    if ($notes !== null) {
                        $updateFields[] = 'notes = :notes';
                        $params['notes'] = $notes;
                    }
                    
                    if (!empty($updateFields)) {
                        $sql = 'UPDATE rentals SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        Flash::add('success', 'Rental updated ✅');
                    } else {
                        Flash::add('warning', 'No changes made');
                    }
                }
            } catch (Exception $e) {
                Flash::add('danger', 'Error updating rental: ' . $e->getMessage());
            }
        }
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: rentals.php'); exit;
} 

$users = $uctrl->listAll(false);
$books = $bctrl->search('', true); // only available books

// prepare user stats for JS
$userStats = [];
foreach ($users as $u) {
    $userStats[$u['id']] = $uctrl->getStats($u['id']);
}

include __DIR__ . '/templates/header.php';
?>
<script>
  // pass PHP data to JS
  window._BOOKRENT = window._BOOKRENT || {};
  window._BOOKRENT.users = <?=json_encode($userStats)?>;
  window._BOOKRENT.maxActive = <?= (int)(require __DIR__ . '/../src/config.php')['settings']['max_active_rentals_per_user'] ?>;
</script>

<h2 class="mb-4">Rental Transactions</h2>
<div class="alert alert-info">
  <i class="bi bi-info-circle"></i> To create a new rental, select a book from the <a href="index.php" class="alert-link">Book Gallery</a> and click "Rent Now"
</div>

<?php if (empty($rentals)): ?>
<div class="alert alert-warning">
  <strong>No rentals found in database.</strong> Create a rental from the Book Gallery to get started.
</div>
<?php else: ?>
<div class="alert alert-success">
  <strong>Total rentals: <?=count($rentals)?></strong>
</div>
<?php endif; ?>

<?php
$searchQuery = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$rentals = $rctrl->getAll();

// Debug: Check if rentals are empty or if they have status field
if (empty($rentals)) {
    error_log("DEBUG: No rentals found in database");
} else {
    error_log("DEBUG: Found " . count($rentals) . " rentals");
    $firstRental = reset($rentals);
    if ($firstRental) {
        error_log("DEBUG: First rental keys: " . json_encode(array_keys($firstRental)));
        error_log("DEBUG: First rental status: " . ($firstRental['status'] ?? 'MISSING'));
    }
}

// Count pending rentals (legacy - new rentals are automatically active)
$pendingCount = count(array_filter($rentals, function($r) { return $r['status'] === 'pending'; }));
if ($pendingCount > 0): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
  <i class="bi bi-info-circle"></i> <strong><?=$pendingCount?> legacy pending rental(s)</strong> (pre-auto-approval system). New rentals are automatically active.
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="GET" class="mb-3">
  <div class="row g-2">
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search by user name, book title, or ISBN..." value="<?=htmlspecialchars($_GET['search'] ?? '')?>">
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
      <?php if(!empty($_GET['search'])): ?>
        <a href="rentals.php" class="btn btn-secondary"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php
// Filter rentals by search query
if (!empty($searchQuery)) {
    $rentals = array_filter($rentals, function($r) use ($searchQuery) {
        return stripos($r['user_name'] ?? '', $searchQuery) !== false ||
               stripos($r['title'] ?? '', $searchQuery) !== false ||
               stripos($r['isbn'] ?? '', $searchQuery) !== false;
    });
}

// Sort rentals: pending first, then by date
usort($rentals, function($a, $b) {
    // pending status first
    if ($a['status'] === 'pending' && $b['status'] !== 'pending') return -1;
    if ($a['status'] !== 'pending' && $b['status'] === 'pending') return 1;
    // then by rent_date descending
    return strtotime($b['rent_date']) - strtotime($a['rent_date']);
});
?>

<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Book Title</th>
      <th>User</th>
      <th>Rent Date</th>
      <th>Due Date</th>
      <th>Return Date</th>
      <th>Status</th>
      <th style="width: 100px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rentals)): ?>
    <tr>
      <td colspan="8" class="text-center text-muted">No rental transactions found</td>
    </tr>
    <?php else: ?>
    <?php foreach($rentals as $r): ?>
    <tr>
      <td><?=intval($r['id'])?></td>
      <td><?=htmlspecialchars($r['book_title'] ?? $r['title'] ?? 'Unknown')?></td>
      <td>
        <?php $displayName = $r['user_name'] ?? $r['username'] ?? $r['fullname'] ?? 'Unknown'; ?>
        <?=htmlspecialchars($displayName)?>
      </td>
      <td><?=htmlspecialchars($r['rent_date'] ?? '-')?></td>
      <td><?=htmlspecialchars($r['due_date'] ?? '-')?></td>
      <td><?=htmlspecialchars($r['return_date'] ?? '-')?></td>
      <td>
        <?php $status = $r['status'] ?? null; ?>
        <span class="badge <?=($status==='active'?'bg-success':($status==='pending'?'bg-warning':($status==='returned'?'bg-info':($status==='overdue'?'bg-danger':'bg-secondary'))))?>"><?=ucfirst($status)?><?php if($status==='pending'): ?> ⏳<?php endif; ?></span>
      </td>
      <td>
        <?php if($status === 'pending'): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Approve this rental?')">
            <input type="hidden" name="edit_id" value="<?=intval($r['id'])?>">
            <input type="hidden" name="status" value="active">
            <button type="submit" class="btn btn-sm btn-outline-success btn-sm-icon" title="Approve"><i class="bi bi-check"></i></button>
          </form>
        <?php elseif($status === 'active'): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Mark as returned?')">
            <input type="hidden" name="return_id" value="<?=intval($r['id'])?>">
            <button type="submit" class="btn btn-sm btn-outline-info btn-sm-icon" title="Return"><i class="bi bi-arrow-left"></i></button>
          </form>
        <?php endif; ?>
        
        <button class="btn btn-sm btn-outline-primary btn-sm-icon" data-rental="<?=htmlspecialchars(json_encode($r))?>" onclick="showReceipt(this)" title="Receipt"><i class="bi bi-receipt"></i></button>
        <button class="btn btn-sm btn-outline-primary btn-sm-icon" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editRental(<?=htmlspecialchars(json_encode($r))?>)" title="Edit"><i class="bi bi-pencil"></i></button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Edit Rental Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-light">
          <h6 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Rental</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="editId">
          
          <div class="mb-3">
            <label class="form-label"><strong>Due Date</strong></label>
            <input type="datetime-local" class="form-control" name="due_date" id="editDueDate">
          </div>
          
          <div class="mb-3">
            <label class="form-label"><strong>Status</strong></label>
            <select class="form-select" name="status" id="editStatus">
              <option value="pending">⏳ Pending (Awaiting Approval)</option>
              <option value="active">✓ Active (Approved)</option>
              <option value="returned">✓✓ Returned</option>
              <option value="overdue">⚠ Overdue</option>
              <option value="cancelled">✗ Cancelled</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label"><strong>Notes</strong></label>
            <textarea class="form-control" name="notes" id="editNotes" rows="2" placeholder="Add any notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

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
        <button type="button" class="btn btn-primary btn-sm" onclick="printReceipt()"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<script>
function editRental(r) {
  document.getElementById('editId').value = r.id;
  const dateStr = (r.due_date || '').replace(' ', 'T').substring(0, 16);
  document.getElementById('editDueDate').value = dateStr;
  document.getElementById('editStatus').value = r.status || 'active';
  document.getElementById('editNotes').value = r.notes || '';
}

function showReceipt(btn) {
  try {
    // Get rental data from data attribute
    const rentalJson = btn.getAttribute('data-rental');
    console.log('Raw rental JSON:', rentalJson);
    const r = JSON.parse(rentalJson);
    console.log('Receipt data received:', r);
    
    if (!r || !r.id) {
      alert('Error: Invalid rental data');
      return;
    }

    const rentDate = new Date(r.rent_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    const dueDate = new Date(r.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    const returnDate = r.return_date ? new Date(r.return_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'Not returned';
    const price = parseFloat(r.price || 0);
    const quantity = parseInt(r.quantity || 1);
    const totalPrice = price * quantity;
    
    // Calculate days rented
    const rentDateObj = new Date(r.rent_date);
    const dueDateObj = new Date(r.due_date);
    const daysRented = Math.ceil((dueDateObj - rentDateObj) / (1000 * 60 * 60 * 24));
    
    const penaltyAmount = parseFloat(r.penalty_amount || 0);
    const penaltyPaid = r.penalty_paid === 1 || r.penalty_paid === true || r.penalty_paid === '1';
    const finalAmount = totalPrice + penaltyAmount;
  
    const receiptHTML = `
      <div style="padding: 20px; background: white; border-radius: 4px; line-height: 1.8; font-size: 0.95em;">
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 25px; border-bottom: 3px solid #4f03c8; padding-bottom: 15px;">
          <h3 style="margin: 0; color: #4f03c8; font-weight: 700;">📋 RENTAL RECEIPT</h3>
          <p style="margin: 5px 0; color: #666; font-size: 0.9em;">BookRent - Modern Book Rental System</p>
          <p style="margin: 5px 0; color: #999; font-size: 0.85em;">📌 Transaction #${String(r.id).padStart(5, '0')}</p>
        </div>
        
        <!-- Book Information -->
        <div style="margin-bottom: 18px; padding: 12px; background: #f8f9fa; border-left: 4px solid #4f03c8; border-radius: 3px;">
          <h6 style="color: #333; margin: 0 0 10px 0; font-weight: 600;">📚 BOOK INFORMATION</h6>
          <table style="width: 100%; border-collapse: collapse; font-size: 0.93em;">
            <tr>
              <td style="padding: 5px 0; width: 35%; color: #666;"><strong>Title:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;">${r.title || 'N/A'}</td>
            </tr>
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>ISBN:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;"><code style="background: #fff; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px;">${r.isbn || 'N/A'}</code></td>
            </tr>
          </table>
        </div>
        
        <!-- Rental Period -->
        <div style="margin-bottom: 18px; padding: 12px; background: #f8f9fa; border-left: 4px solid #28a745; border-radius: 3px;">
          <h6 style="color: #333; margin: 0 0 10px 0; font-weight: 600;">📅 RENTAL PERIOD</h6>
          <table style="width: 100%; border-collapse: collapse; font-size: 0.93em;">
            <tr>
              <td style="padding: 4px 0; width: 35%; color: #666;"><strong>Rent Date:</strong></td>
              <td style="padding: 4px 0; text-align: right; color: #333;">${rentDate}</td>
            </tr>
            <tr>
              <td style="padding: 4px 0; color: #666;"><strong>Due Date:</strong></td>
              <td style="padding: 4px 0; text-align: right; color: #333;">${dueDate}</td>
            </tr>
            <tr>
              <td style="padding: 4px 0; color: #666;"><strong>Duration:</strong></td>
              <td style="padding: 4px 0; text-align: right; color: #333;"><strong>${daysRented} day(s)</strong></td>
            </tr>
            <tr>
              <td style="padding: 4px 0; color: #666;"><strong>Return Date:</strong></td>
              <td style="padding: 4px 0; text-align: right; color: #333;">${returnDate}</td>
            </tr>
            <tr>
              <td style="padding: 4px 0; color: #666;"><strong>Status:</strong></td>
              <td style="padding: 4px 0; text-align: right;">
                <span style="background: ${r.status === 'pending' ? '#fff3cd' : r.status === 'active' ? '#d1e7dd' : r.status === 'returned' ? '#cfe2ff' : r.status === 'overdue' ? '#f8d7da' : '#e2e3e5'}; color: ${r.status === 'pending' ? '#856404' : r.status === 'active' ? '#0f5132' : r.status === 'returned' ? '#084298' : r.status === 'overdue' ? '#842029' : '#383d41'}; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 0.85em;">${(r.status || 'UNKNOWN').toUpperCase()}</span>
              </td>
            </tr>
          </table>
        </div>
        
        <!-- Pricing Details -->
        <div style="margin-bottom: 18px; padding: 12px; background: #f8f9fa; border-left: 4px solid #fd7e14; border-radius: 3px;">
          <h6 style="color: #333; margin: 0 0 10px 0; font-weight: 600;">💰 PRICING DETAILS</h6>
          <table style="width: 100%; border-collapse: collapse; font-size: 0.93em;">
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>Book Title:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;">${r.title || 'N/A'}</td>
            </tr>
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>Quantity:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;"><strong>${quantity} copy(ies)</strong></td>
            </tr>
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>Unit Price:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;">₱${price.toFixed(2)}</td>
            </tr>
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>Rental Duration:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;">${daysRented} day(s) × ₱${(price / daysRented).toFixed(2)}</td>
            </tr>
            <tr style="border-top: 2px solid #4f03c8; border-bottom: 2px solid #4f03c8;">
              <td style="padding: 8px 0; color: #333;"><strong style="font-size: 1.05em;">Rental Subtotal:</strong></td>
              <td style="padding: 8px 0; text-align: right; color: #333;"><strong style="font-size: 1.05em;">₱${totalPrice.toFixed(2)}</strong></td>
            </tr>
            ${penaltyAmount > 0 ? `
            <tr style="background: #fff3cd;">
              <td style="padding: 5px 0; color: #856404;"><strong>Penalty Fee:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #856404;"><strong>₱${penaltyAmount.toFixed(2)}</strong></td>
            </tr>
            <tr style="border-bottom: 2px solid #4f03c8; background: #fffbea;">
              <td style="padding: 8px 0; color: #333;"><strong style="font-size: 1.1em;">TOTAL AMOUNT:</strong></td>
              <td style="padding: 8px 0; text-align: right; color: #333;"><strong style="font-size: 1.1em; color: #4f03c8;">₱${finalAmount.toFixed(2)}</strong></td>
            </tr>
            ` : `
            <tr>
              <td style="padding: 5px 0; color: #333;"><strong style="font-size: 1.1em;">TOTAL AMOUNT:</strong></td>
              <td style="padding: 5px 0; text-align: right; color: #333;"><strong style="font-size: 1.1em; color: #4f03c8;">₱${totalPrice.toFixed(2)}</strong></td>
            </tr>
            `}
          </table>
        </div>
        
        <!-- Payment Method -->
        <div style="margin-bottom: 18px; padding: 12px; background: #f8f9fa; border-left: 4px solid #007bff; border-radius: 3px;">
          <h6 style="color: #333; margin: 0 0 10px 0; font-weight: 600;">💳 PAYMENT METHOD</h6>
          <table style="width: 100%; border-collapse: collapse; font-size: 0.93em;">
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>Method:</strong></td>
              <td style="padding: 5px 0; text-align: right;">
                <span style="background: ${r.payment_method === 'card' ? '#cce5ff' : r.payment_method === 'online' ? '#d1ecf1' : '#e2e3e5'}; color: ${r.payment_method === 'card' ? '#0c5de4' : r.payment_method === 'online' ? '#0c5484' : '#383d41'}; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 0.85em;">
                  ${r.payment_method ? r.payment_method.charAt(0).toUpperCase() + r.payment_method.slice(1) : 'N/A'}
                </span>
              </td>
            </tr>
            <tr>
              <td style="padding: 5px 0; color: #666;"><strong>Payment Status:</strong></td>
              <td style="padding: 5px 0; text-align: right;">
                <span style="background: #d1e7dd; color: #0f5132; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 0.85em;">✓ PAID</span>
              </td>
            </tr>
          </table>
        </div>

        <!-- Footer -->
        <div style="text-align: center; color: #666; font-size: 0.8em; margin-top: 25px; padding-top: 15px; border-top: 2px solid #e0e0e0;">
          <p style="margin: 6px 0;"><strong>BookRent Store</strong></p>
          <p style="margin: 4px 0;">123 Main Street, City Center</p>
          <p style="margin: 4px 0;">📞 0912 345 6789 | 📧 support@bookrent.com</p>
          <p style="margin: 6px 0; color: #999; font-style: italic;">Generated: ${new Date().toLocaleString()}</p>
          <p style="margin: 8px 0 0 0; font-size: 0.75em; color: #aaa;">Thank you for renting with BookRent!</p>
        </div>
      </div>
    `;
    
    try {
      const receiptContentEl = document.getElementById('receiptContent');
      if (!receiptContentEl) {
        alert('Error: Receipt modal element not found');
        console.error('receiptContent element not found');
        return;
      }
      
      receiptContentEl.innerHTML = receiptHTML;
      console.log('Receipt HTML updated successfully');
      
      const modalEl = document.getElementById('receiptModal');
      if (!modalEl) {
        alert('Error: Receipt modal not found');
        console.error('receiptModal element not found');
        return;
      }
      
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      console.log('Modal shown successfully');
    } catch (err) {
      console.error('Error showing receipt:', err);
      alert('Error displaying receipt: ' + err.message);
    }
  } catch (outerErr) {
    console.error('Error in showReceipt function:', outerErr);
    alert('Error: ' + outerErr.message);
  }
}

function printReceipt() {
  const printWindow = window.open('', '', 'width=600');
  printWindow.document.write(document.getElementById('receiptContent').innerHTML);
  printWindow.document.close();
  printWindow.print();
}
</script>

<?php include __DIR__ . '/templates/footer.php';