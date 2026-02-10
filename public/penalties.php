<?php
require_once __DIR__ . '/../src/Controllers/PenaltyController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$ctrl = new PenaltyController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_id'])) {
    try {
        $ctrl->markPaid((int)$_POST['pay_id']);
        Flash::add('success','Penalty marked as paid ✅');
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: penalties.php'); exit;
}
$penalties = $ctrl->listUnpaid();
include __DIR__ . '/templates/header.php';
?>
<div class="container mt-4">
  <h2>Penalties</h2>
  <table class="table">
    <thead><tr><th>User</th><th>Amount</th><th>Days Overdue</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($penalties as $p):?>
        <tr>
          <td><?=htmlspecialchars($p['user_name'] ?? 'User')?></td>
          <td><?=number_format($p['amount'],2)?></td>
          <td><?=intval($p['days_overdue'])?></td>
          <td><?=htmlspecialchars($p['created_at'])?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="pay_id" value="<?=intval($p['id'])?>">
              <button class="btn btn-sm btn-accent" type="submit">Mark Paid</button>
            </form>
          </td>
        </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>