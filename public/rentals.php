<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/RentalController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Models/Book.php';
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
            
            // Create single rental transaction with quantity
            $rctrl->rent((int)$_POST['user_id'], (int)$_POST['book_id'], (int)$_POST['duration'], $quantity, $cashReceived);
            
            // Show message with quantity
            if ($quantity > 1) {
                Flash::add('success', $quantity . ' copies rented in 1 transaction ✅');
            } else {
                Flash::add('success','Book rented ✅');
            }
        } elseif (isset($_POST['edit_id'])) {
            // Edit rental
            $rentalId = (int)$_POST['edit_id'];
            $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $status = $_POST['status'] ?? null;
            $notes = $_POST['notes'] ?? null;
            
            try {
                $db = Database::getInstance()->pdo();
                
                // Build update query dynamically
                $updateFields = [];
                $params = ['id' => $rentalId];
                
                if ($dueDate) {
                    // Convert from datetime-local format (YYYY-MM-DDTHH:mm) to MySQL format (YYYY-MM-DD HH:mm:ss)
                    $dueDate = str_replace('T', ' ', $dueDate) . ':00';
                    $updateFields[] = 'due_date = :due_date';
                    $params['due_date'] = $dueDate;
                }
                if ($status) {
                    $updateFields[] = 'status = :status';
                    $params['status'] = $status;
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
$searchQuery = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$rentals = $rctrl->getAll();

// Filter rentals by search query
if (!empty($searchQuery)) {
    $rentals = array_filter($rentals, function($r) use ($searchQuery) {
        return stripos($r['user_name'] ?? '', $searchQuery) !== false ||
               stripos($r['title'] ?? '', $searchQuery) !== false ||
               stripos($r['isbn'] ?? '', $searchQuery) !== false;
    });
}
?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Book</th>
      <th>User</th>
      <th>Rented</th>
      <th>Due</th>
      <th>Returned</th>
      <th>Status</th>
      <th>Notes</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rentals as $r): ?>
    <tr>
      <td><?=intval($r['id'])?></td>
      <td><?=htmlspecialchars($r['book_title'] ?? $r['title'])?></td>
      <td><?=htmlspecialchars($r['user_name'] ?? $r['username'])?></td>
      <td><?=htmlspecialchars($r['rent_date'])?></td>
      <td><?=htmlspecialchars($r['due_date'])?></td>
      <td><?=htmlspecialchars($r['return_date'] ?? '-')?></td>
      <td><span class="badge <?=($r['status']==='active'?'bg-success':'bg-secondary')?>"><?=ucfirst($r['status'])?></span></td>
      <td><?=htmlspecialchars($r['notes'] ?? '')?></td>
      <td>
        <?php if($r['status']==='active'): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Return this book?')">
          <input type="hidden" name="return_id" value="<?=intval($r['id'])?>">
          <button class="btn btn-sm btn-outline-success btn-sm-icon" type="submit" title="Return"><i class="bi bi-arrow-counterclockwise"></i></button>
        </form>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-info btn-sm-icon ms-1" data-bs-toggle="modal" data-bs-target="#receiptModal" onclick="viewReceipt(<?=htmlspecialchars(json_encode($r))?>)" title="View Receipt"><i class="bi bi-receipt"></i></button>
        <button class="btn btn-sm btn-outline-primary btn-sm-icon ms-1" data-bs-toggle="modal" data-bs-target="#editRentalModal" onclick="editRental(<?=htmlspecialchars(json_encode($r))?>)"><i class="bi bi-pencil"></i></button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Edit Rental Modal -->
<div class="modal fade" id="editRentalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="rentals.php">
        <div class="modal-header">
          <h5 class="modal-title">Edit Rental</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit-rental-id">
          <div class="mb-3">
            <label class="form-label">Due Date</label>
            <input type="datetime-local" class="form-control" name="due_date" id="edit-due-date">
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" id="edit-status">
              <option value="active">Active</option>
              <option value="returned">Returned</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" id="edit-notes" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-receipt"></i> Rental Receipt</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="receiptContent" style="background-color: #f9f9f9;">
        <!-- Receipt will be populated by JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="printReceipt()"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<script>
function editRental(r) {
  document.getElementById('edit-rental-id').value = r.id;
  // Format date for datetime-local input (YYYY-MM-DDTHH:mm)
  if (r.due_date) {
    const dateStr = r.due_date.replace(' ', 'T').substring(0, 16); // Remove seconds
    document.getElementById('edit-due-date').value = dateStr;
  } else {
    document.getElementById('edit-due-date').value = '';
  }
  document.getElementById('edit-status').value = r.status;
  document.getElementById('edit-notes').value = r.notes || '';
}

function viewReceipt(r) {
  // Format dates
  const rentDate = new Date(r.rent_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  const dueDate = new Date(r.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  const returnDate = r.return_date ? new Date(r.return_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'Not yet returned';
  
  // Parse payment details
  const paymentMethod = r.payment_method || 'Not specified';
  const cashReceived = r.cash_received ? parseFloat(r.cash_received).toFixed(2) : '0.00';
  const changeAmount = r.change_amount ? parseFloat(r.change_amount).toFixed(2) : '0.00';
  
  // Build receipt HTML
  const receiptHTML = `
    <div style="padding: 20px; background: white; border: 1px solid #ddd; border-radius: 4px; font-family: 'Courier New', monospace;">
      <div style="text-align: center; margin-bottom: 20px;">
        <h4 style="margin: 0; color: #4f03c8;"><i class="bi bi-receipt"></i> RENTAL RECEIPT</h4>
        <p style="margin: 5px 0; color: #666; font-size: 0.9em;">Transaction ID: #${r.id}</p>
      </div>
      
      <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
      
      <!-- Rental Details -->
      <div style="margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Book Title:</span>
          <span>${r.book_title || 'N/A'}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Renter:</span>
          <span>${r.user_name || 'N/A'}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Contact:</span>
          <span>${r.contact_no || r.email || 'N/A'}</span>
        </div>
      </div>
      
      <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
      
      <!-- Dates -->
      <div style="margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Rent Date:</span>
          <span>${rentDate}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Due Date:</span>
          <span>${dueDate}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Return Date:</span>
          <span>${returnDate}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Duration:</span>
          <span>${r.duration_days} day(s)</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Quantity:</span>
          <span>${r.quantity || 1} copy/copies</span>
        </div>
      </div>
      
      <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
      
      <!-- Payment Details -->
      <div style="margin-bottom: 15px;">
        <h5 style="margin: 10px 0; color: #4f03c8;">Payment Information</h5>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
          <span style="font-weight: bold;">Method:</span>
          <span style="text-transform: capitalize;">${paymentMethod}</span>
        </div>
        ${paymentMethod === 'cash' ? `
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-weight: bold;">Cash Received:</span>
            <span>₱${cashReceived}</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-weight: bold;">Change:</span>
            <span>₱${changeAmount}</span>
          </div>
        ` : ''}
        ${paymentMethod === 'card' && r.card_last_four ? `
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-weight: bold;">Card:</span>
            <span>****${r.card_last_four}</span>
          </div>
        ` : ''}
        ${paymentMethod === 'online' && r.online_transaction_no ? `
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-weight: bold;">Transaction #:</span>
            <span>${r.online_transaction_no}</span>
          </div>
        ` : ''}
      </div>
      
      <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
      
      <!-- Status -->
      <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
        <span style="font-weight: bold;">Status:</span>
        <span style="text-transform: capitalize; padding: 2px 8px; background: ${r.status === 'active' ? '#d4edda' : '#e2e3e5'}; color: ${r.status === 'active' ? '#155724' : '#383d41'}; border-radius: 3px; font-size: 0.9em;">${r.status}</span>
      </div>
      
      ${r.notes ? `
        <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
        <div>
          <span style="font-weight: bold;">Notes:</span>
          <p style="margin: 8px 0; color: #666;">${r.notes}</p>
        </div>
      ` : ''}
      
      <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
      
      <div style="text-align: center; color: #999; font-size: 0.9em;">
        <p style="margin: 0;">This is an official rental receipt</p>
        <p style="margin: 5px 0;">BookRent Management System</p>
      </div>
    </div>
  `;
  
  document.getElementById('receiptContent').innerHTML = receiptHTML;
}

function printReceipt() {
  const receiptContent = document.getElementById('receiptContent').innerHTML;
  const printWindow = window.open('', '', 'width=800,height=600');
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Rental Receipt</title>
      <style>
        body {
          font-family: 'Courier New', monospace;
          margin: 20px;
          padding: 0;
        }
        @media print {
          body { margin: 0; }
        }
      </style>
    </head>
    <body>
      ${receiptContent}
      <script>
        window.print();
        window.close();
      <\/script>
    </body>
    </html>
  `);
  printWindow.document.close();
}
</script>

<?php include __DIR__ . '/templates/footer.php';