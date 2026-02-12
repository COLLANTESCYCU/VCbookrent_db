<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Models/Rental.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$r = new Rental();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    try {
        $c = $r->markOverdueAndApplyPenalties();
        Flash::add('success', "Marked $c rentals as overdue ✅");
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: overdue.php'); exit;
}
$list = $r->getOverdueRentals();
include __DIR__ . '/templates/header.php';
?> 
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Overdue Rentals</h2>
    <form method="POST"><button name="run_check" class="btn btn-accent">Run Overdue Check</button></form>
  </div>

  <table class="table table-hover">
    <thead><tr><th>User</th><th>Book</th><th>Due</th><th>Days Overdue</th><th>Penalty</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($list as $it):
        $due = new DateTime($it['due_date']);
        $now = new DateTime();
        $days = $now > $due ? $due->diff($now)->days : 0;
      ?>
      <tr>
        <td><?=htmlspecialchars($it['user_name'])?></td>
        <td><?=htmlspecialchars($it['title'])?></td>
        <td><?=htmlspecialchars($it['due_date'])?></td>
        <td><?=intval($days)?></td>
        <td><?=!empty($it['penalty_id']) ? 'Yes (ID: '.intval($it['penalty_id']).')' : 'No'?></td>
        <td>
          <form method="POST" action="rentals.php" style="display:inline">
            <input type="hidden" name="return_id" value="<?=intval($it['id'])?>">
            <button class="btn btn-sm btn-outline-success" type="submit">Return</button>
          </form>
          <!-- Penalties button removed -->
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/templates/footer.php';