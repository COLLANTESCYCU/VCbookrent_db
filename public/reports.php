<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';
require_once __DIR__ . '/../src/Database.php';

$ctrl = new ReportController();
$db = Database::getInstance()->pdo();

// Get filter parameters
$reportType = $_GET['type'] ?? 'rental';
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');

// Books report
$stmt = $db->prepare('SELECT b.*, COUNT(r.id) as total_rentals, SUM(CASE WHEN r.status="returned" THEN 1 ELSE 0 END) as returned_count FROM books b LEFT JOIN rentals r ON b.id = r.book_id AND r.rent_date >= :from AND r.rent_date <= :to WHERE b.archived = 0 GROUP BY b.id ORDER BY total_rentals DESC');
$stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$booksReport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Users report
$stmt = $db->prepare('SELECT u.*, COUNT(r.id) as rentals_count, SUM(CASE WHEN r.status="returned" THEN 1 ELSE 0 END) as returned_count FROM users u LEFT JOIN rentals r ON u.id = r.user_id AND r.rent_date >= :from AND r.rent_date <= :to GROUP BY u.id ORDER BY rentals_count DESC');
$stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$usersReport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rentals report
$stmt = $db->prepare('SELECT r.*, b.title, b.price, u.fullname, u.email FROM rentals r JOIN books b ON r.book_id = b.id JOIN users u ON r.user_id = u.id WHERE r.rent_date >= :from AND r.rent_date <= :to ORDER BY r.rent_date DESC');
$stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$rentalsReport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment report
$stmt = $db->prepare('SELECT tp.*, u.fullname, b.title FROM tbl_payments tp JOIN rentals r ON tp.rental_id = r.id JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id WHERE tp.payment_date >= :from AND tp.payment_date <= :to ORDER BY tp.payment_date DESC');
$stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$paymentsReport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Penalties report
$stmt = $db->prepare('SELECT p.*, u.fullname, r.id as rental_id FROM penalties p JOIN users u ON p.user_id = u.id JOIN rentals r ON p.rental_id = r.id WHERE p.created_at >= :from AND p.created_at <= :to ORDER BY p.created_at DESC');
$stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$penaltiesReport = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/templates/header.php';
?>
<style>
  .report-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
  }
  .report-tab-content {
    display: none;
  }
  .report-tab-content.active {
    display: block;
  }
  .table-hover tbody tr:hover {
    background-color: #f8f9fa;
  }
</style>

<div class="container-fluid py-4">
  <!-- Header -->
  <div class="report-header">
    <h1 class="mb-2">Reports & Analytics</h1>
    <p class="mb-0">Detailed reports on rentals, users, payments, and penalties</p>
  </div>

  <!-- Filters -->
  <div class="card mb-4 border-0">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">From Date</label>
          <input type="date" name="from" class="form-control" value="<?=htmlspecialchars($dateFrom)?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To Date</label>
          <input type="date" name="to" class="form-control" value="<?=htmlspecialchars($dateTo)?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Report Type</label>
          <select name="type" class="form-select">
            <option value="rental" <?=$reportType==='rental'?'selected':''?>>Rentals</option>
            <option value="payment" <?=$reportType==='payment'?'selected':''?>>Payments</option>
            <option value="user" <?=$reportType==='user'?'selected':''?>>Users</option>
            <option value="books" <?=$reportType==='books'?'selected':''?>>Books</option>
            <option value="penalties" <?=$reportType==='penalties'?'selected':''?>>Penalties</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabs Navigation -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
      <a href="#rentals" class="nav-link <?=$reportType==='rental'?'active':''?>" onclick="switchTab('rentals')">
        <i class="bi bi-cart-check me-2"></i>Rentals
      </a>
    </li>
    <li class="nav-item">
      <a href="#payments" class="nav-link <?=$reportType==='payment'?'active':''?>" onclick="switchTab('payments')">
        <i class="bi bi-cash-coin me-2"></i>Payments
      </a>
    </li>
    <li class="nav-item">
      <a href="#users" class="nav-link <?=$reportType==='user'?'active':''?>" onclick="switchTab('users')">
        <i class="bi bi-people me-2"></i>Users
      </a>
    </li>
    <li class="nav-item">
      <a href="#books" class="nav-link <?=$reportType==='books'?'active':''?>" onclick="switchTab('books')">
        <i class="bi bi-journal-bookmark me-2"></i>Books
      </a>
    </li>
    <li class="nav-item">
      <a href="#penalties" class="nav-link <?=$reportType==='penalties'?'active':''?>" onclick="switchTab('penalties')">
        <i class="bi bi-exclamation-triangle me-2"></i>Penalties
      </a>
    </li>
  </ul>

  <!-- RENTALS REPORT -->
  <div id="rentals" class="report-tab-content <?=$reportType==='rental'?'active':''?>">
    <div class="card border-0">
      <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Rental Report</h6>
          <span class="badge bg-primary rounded-pill"><?=count($rentalsReport)?> rentals</span>
        </div>
      </div>
      <div class="card-body">
        <?php if(!empty($rentalsReport)): ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Book</th>
                  <th>Duration</th>
                  <th>Rent Date</th>
                  <th>Due Date</th>
                  <th>Status</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rentalsReport as $r): ?>
                <tr>
                  <td><strong>#<?=intval($r['id'])?></strong></td>
                  <td><?=htmlspecialchars($r['fullname'] ?? 'N/A')?></td>
                  <td><?=htmlspecialchars($r['title'] ?? 'N/A')?></td>
                  <td><?=intval($r['duration_days'])?>d</td>
                  <td><?=date('M d, Y', strtotime($r['rent_date']))?></td>
                  <td><?=date('M d, Y', strtotime($r['due_date']))?></td>
                  <td>
                    <?php
                      $statusBg = match($r['status']) {
                        'active' => 'bg-success',
                        'returned' => 'bg-secondary',
                        'overdue' => 'bg-danger',
                        'cancelled' => 'bg-warning',
                        default => 'bg-light'
                      };
                    ?>
                    <span class="badge <?=$statusBg?>"><?=ucfirst($r['status'])?></span>
                  </td>
                  <td>₱<?=number_format($r['price'] ?? 0, 2)?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">No rentals found for this period</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- PAYMENTS REPORT -->
  <div id="payments" class="report-tab-content <?=$reportType==='payment'?'active':''?>">
    <div class="card border-0">
      <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Payment Report</h6>
          <span class="badge bg-success rounded-pill">₱<?=number_format(array_sum(array_column($paymentsReport, 'amount_received')), 2)?> total</span>
        </div>
      </div>
      <div class="card-body">
        <?php if(!empty($paymentsReport)): ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Book</th>
                  <th>Amount Charged</th>
                  <th>Amount Received</th>
                  <th>Method</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($paymentsReport as $p): ?>
                <tr>
                  <td><strong>#<?=intval($p['id'])?></strong></td>
                  <td><?=htmlspecialchars($p['fullname'] ?? 'N/A')?></td>
                  <td><?=htmlspecialchars($p['title'] ?? 'N/A')?></td>
                  <td>₱<?=number_format($p['amount_charged'] ?? 0, 2)?></td>
                  <td><strong>₱<?=number_format($p['amount_received'] ?? 0, 2)?></strong></td>
                  <td><span class="badge bg-info"><?=ucfirst($p['payment_method'])?></span></td>
                  <td>
                    <?php
                      $statusBg = match($p['payment_status']) {
                        'completed' => 'bg-success',
                        'pending' => 'bg-warning',
                        'failed' => 'bg-danger',
                        'cancelled' => 'bg-secondary',
                        default => 'bg-light'
                      };
                    ?>
                    <span class="badge <?=$statusBg?>"><?=ucfirst($p['payment_status'])?></span>
                  </td>
                  <td><?=date('M d, Y', strtotime($p['payment_date']))?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">No payments found for this period</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- USERS REPORT -->
  <div id="users" class="report-tab-content <?=$reportType==='user'?'active':''?>">
    <div class="card border-0">
      <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">User Activity Report</h6>
          <span class="badge bg-primary rounded-pill"><?=count($usersReport)?> users</span>
        </div>
      </div>
      <div class="card-body">
        <?php if(!empty($usersReport)): ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Contact</th>
                  <th>Total Rentals</th>
                  <th>Returned</th>
                  <th>Active</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($usersReport as $u): ?>
                <tr>
                  <td><strong>#<?=intval($u['id'])?></strong></td>
                  <td>
                    <strong><?=htmlspecialchars($u['fullname'] ?? 'N/A')?></strong>
                  </td>
                  <td><?=htmlspecialchars($u['email'] ?? '')?></td>
                  <td><?=htmlspecialchars($u['contact_no'] ?? '')?></td>
                  <td><span class="badge bg-primary"><?=intval($u['rentals_count'] ?? 0)?></span></td>
                  <td><span class="badge bg-success"><?=intval($u['returned_count'] ?? 0)?></span></td>
                  <td><span class="badge bg-info"><?=intval(($u['rentals_count'] ?? 0) - ($u['returned_count'] ?? 0))?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">No user data found</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- BOOKS REPORT -->
  <div id="books" class="report-tab-content <?=$reportType==='books'?'active':''?>">
    <div class="card border-0">
      <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Book Performance Report</h6>
          <span class="badge bg-primary rounded-pill"><?=count($booksReport)?> books</span>
        </div>
      </div>
      <div class="card-body">
        <?php if(!empty($booksReport)): ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>Title</th>
                  <th>Author</th>
                  <th>Total Rentals</th>
                  <th>Returned</th>
                  <th>Price</th>
                  <th>Available</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($booksReport as $b): ?>
                <tr>
                  <td><strong>#<?=intval($b['id'])?></strong></td>
                  <td><?=htmlspecialchars($b['title'] ?? 'N/A')?></td>
                  <td><?=htmlspecialchars($b['author'] ?? 'N/A')?></td>
                  <td><span class="badge bg-primary"><?=intval($b['total_rentals'] ?? 0)?></span></td>
                  <td><span class="badge bg-success"><?=intval($b['returned_count'] ?? 0)?></span></td>
                  <td>₱<?=number_format($b['price'] ?? 0, 2)?></td>
                  <td>
                    <span class="badge <?=($b['available_copies'] > 0 ? 'bg-success' : 'bg-danger')?>">
                      <?=intval($b['available_copies'])?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">No book data found</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- PENALTIES REPORT -->
  <div id="penalties" class="report-tab-content <?=$reportType==='penalties'?'active':''?>">
    <div class="card border-0">
      <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Penalties Report</h6>
          <span class="badge bg-danger rounded-pill">₱<?=number_format(array_sum(array_column($penaltiesReport, 'amount')), 2)?> total</span>
        </div>
      </div>
      <div class="card-body">
        <?php if(!empty($penaltiesReport)): ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="bg-light">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Rental ID</th>
                  <th>Amount</th>
                  <th>Overdue Days</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($penaltiesReport as $p): ?>
                <tr>
                  <td><strong>#<?=intval($p['id'])?></strong></td>
                  <td><?=htmlspecialchars($p['fullname'] ?? 'N/A')?></td>
                  <td>#<?=intval($p['rental_id'])?></td>
                  <td><strong>₱<?=number_format($p['amount'] ?? 0, 2)?></strong></td>
                  <td><?=intval($p['days_overdue'])?>d</td>
                  <td>
                    <span class="badge <?=($p['paid'] ? 'bg-success' : 'bg-danger')?>">
                      <?=($p['paid'] ? 'Paid' : 'Unpaid')?>
                    </span>
                  </td>
                  <td><?=date('M d, Y', strtotime($p['created_at']))?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">No penalties found</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.report-tab-content').forEach(el => {
      el.classList.remove('active');
    });
    // Remove active from all nav links
    document.querySelectorAll('.nav-link').forEach(el => {
      el.classList.remove('active');
    });
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    // Add active to clicked nav link
    event.target.closest('a').classList.add('active');
  }
</script>

<?php include __DIR__ . '/templates/footer.php';