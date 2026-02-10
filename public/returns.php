<?php
require_once __DIR__ . '/../src/Controllers/RentalController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$rctrl = new RentalController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return'])) {
    try {
        $res = $rctrl->doReturn((int)$_POST['rental_id'], $_POST['return_date'] ?? null);
        Flash::add('success','Returned. Overdue days: '.intval($res['overdue_days']));
    } catch (Exception $e) { Flash::add('danger',$e->getMessage()); }
    header('Location: returns.php'); exit;
}
include __DIR__ . '/templates/header.php';
?> 
<h2>Return Book</h2>

<form method="POST" class="form-inline">
  <label>Rental ID <input name="rental_id" type="number" required></label>
  <label>Return Date (optional) <input name="return_date" type="datetime-local"></label>
  <button name="return" type="submit">Return</button>
</form>
<?php include __DIR__ . '/templates/footer.php'; ?>