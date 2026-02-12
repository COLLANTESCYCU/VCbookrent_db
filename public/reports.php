<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';
$ctrl = new ReportController();
$books = $ctrl->mostRentedBooks();
$users = $ctrl->mostActiveUsers();
$trends = $ctrl->rentalTrends('daily');
include __DIR__ . '/templates/header.php';
?>
<h2>Reports</h2>
<h3>Most Rented Books</h3>
<ul>
<?php foreach($books as $b):?><li><?=htmlspecialchars($b['title'])?> — <?=intval($b['times_rented'])?> times</li><?php endforeach;?>
</ul>
<h3>Most Active Users</h3>
<ul>
<?php foreach($users as $u):?><li><?=htmlspecialchars($u['name'])?> — <?=intval($u['total_rentals'])?></li><?php endforeach;?>
</ul>
<h3>Daily Rental Trend (last 30 days)</h3>
<ul>
<?php foreach($trends as $t):?><li><?=htmlspecialchars($t['d'])?> — <?=intval($t['cnt'])?></li><?php endforeach;?>
</ul>
<?php include __DIR__ . '/templates/footer.php';