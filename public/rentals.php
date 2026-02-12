<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/RentalController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Models/Book.php';
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
            $cashReceived = !empty($_POST['cash_received']) ? (float)$_POST['cash_received'] : null;
            $rctrl->rent((int)$_POST['user_id'], (int)$_POST['book_id'], (int)$_POST['duration'], $cashReceived);
            Flash::add('success','Book rented ✅');
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


<?php
$rentals = $rctrl->getAll();
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

<script>
function editRental(r) {
  document.getElementById('edit-rental-id').value = r.id;
  document.getElementById('edit-due-date').value = r.due_date ? r.due_date.replace(' ', 'T') : '';
  document.getElementById('edit-status').value = r.status;
  document.getElementById('edit-notes').value = r.notes || '';
}
</script>

<?php include __DIR__ . '/templates/footer.php';