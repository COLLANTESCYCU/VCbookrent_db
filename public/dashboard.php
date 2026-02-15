<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Database.php';

$ctrl = new ReportController();
$bookModel = new Book();
$db = Database::getInstance()->pdo();

// Get all metrics
$counts = $ctrl->counts();
$top = $ctrl->mostRentedBooks(5);
$recent = $ctrl->recentRentals(10);
$trends = $ctrl->rentalTrends('daily');
$bookStats = $bookModel->getAvailabilityStats();
$inventoryStats = $bookModel->getInventoryStats();
$topUsers = $ctrl->mostActiveUsers(5);

// Get payment data
$stmt = $db->query("SELECT SUM(amount_received) as total_revenue FROM tbl_payments WHERE payment_status = 'completed'");
$paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
$totalRevenue = (float)($paymentData['total_revenue'] ?? 0);

// Get penalties data
$stmt = $db->query("SELECT COUNT(*) as unpaid_count, SUM(amount) as total_amount FROM penalties WHERE paid = 0");
$penaltyData = $stmt->fetch(PDO::FETCH_ASSOC);

// This month's rentals
$stmt = $db->query("SELECT COUNT(*) as count FROM rentals WHERE MONTH(rent_date) = MONTH(NOW()) AND YEAR(rent_date) = YEAR(NOW())");
$monthlyRentals = $stmt->fetch()['count'] ?? 0;

include __DIR__ . '/templates/header.php';
?>
<style>
  .stat-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    overflow: hidden;
  }
  .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  }
  .stat-card .card-body {
    padding: 20px;
  }
  .stat-icon {
    font-size: 2.5rem;
    opacity: 0.3;
  }
  .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    line-height: 1;
  }
  .stat-label {
    color: #666;
    font-size: 0.9rem;
    margin-top: 8px;
  }
  .stat-subtitle {
    color: #999;
    font-size: 0.85rem;
    margin-top: 4px;
  }
  .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
  }
  .chart-container {
    position: relative;
    height: 300px;
  }
</style>

<div class="container-fluid py-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="mb-2" style="color: #333; font-weight: 700;">Dashboard</h1>
      <p class="text-muted mb-0">Real-time system overview and analytics</p>
    </div>
    <div class="text-end">
      <small class="text-muted d-block">Last updated: <?=date('M d, Y H:i')?></small>
      <small class="text-muted d-block">Today: <?=date('l')?></small>
    </div>
  </div>

  <!-- Key Metrics Row 1 -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Total Books</p>
              <h3 class="stat-value mb-0"><?=intval($counts['books'])?></h3>
              <p class="stat-subtitle mb-0"><?=intval($bookStats['available_books'])?> available for rent</p>
            </div>
            <i class="bi bi-journal-bookmark stat-icon text-primary"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Active Rentals</p>
              <h3 class="stat-value mb-0"><?=intval($counts['active_rentals'])?></h3>
              <p class="stat-subtitle mb-0"><span class="badge bg-danger">Overdue: <?=intval($counts['overdue'])?></span></p>
            </div>
            <i class="bi bi-cart-check stat-icon text-success"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Total Revenue</p>
              <h3 class="stat-value mb-0">₱<?=number_format($totalRevenue, 0)?></h3>
              <p class="stat-subtitle mb-0">From <?=$monthlyRentals?> rentals this month</p>
            </div>
            <i class="bi bi-cash-coin stat-icon text-warning"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Total Users</p>
              <h3 class="stat-value mb-0"><?=intval($counts['users'])?></h3>
              <p class="stat-subtitle mb-0">Active users in system</p>
            </div>
            <i class="bi bi-people-fill stat-icon text-info"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Inventory & Penalties Row -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Low Stock</p>
              <h3 class="stat-value mb-0 text-warning"><?=intval($inventoryStats['low_stock'])?></h3>
              <p class="stat-subtitle mb-0">Books needing restock</p>
            </div>
            <i class="bi bi-exclamation-triangle stat-icon text-warning"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Out of Stock</p>
              <h3 class="stat-value mb-0 text-danger"><?=intval($inventoryStats['out_of_stock'])?></h3>
              <p class="stat-subtitle mb-0">Unavailable for rent</p>
            </div>
            <i class="bi bi-x-circle stat-icon text-danger"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Unpaid Penalties</p>
              <h3 class="stat-value mb-0 text-danger"><?=intval($penaltyData['unpaid_count'])?></h3>
              <p class="stat-subtitle mb-0">₱<?=number_format($penaltyData['total_amount'] ?? 0, 0)?> pending</p>
            </div>
            <i class="bi bi-receipt stat-icon text-danger"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card stat-card border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label m-0">Inventory Value</p>
              <h3 class="stat-value mb-0">₱<?=number_format($inventoryStats['total_value'], 0)?></h3>
              <p class="stat-subtitle mb-0">Total book value</p>
            </div>
            <i class="bi bi-graph-up stat-icon text-success"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Section -->
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card border-0">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Rental Activity (Last 30 Days)</h6>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="rentalsChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Inventory Status</h6>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="inventoryChart" style="max-height: 250px;"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Content Row -->
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card border-0">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-star-fill me-2"></i>Most Rented Books</h6>
        </div>
        <div class="card-body">
          <?php if(!empty($top)): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <tbody>
                  <?php foreach($top as $idx => $b): ?>
                  <tr>
                    <td style="width: 30px;">
                      <span class="badge bg-primary"><?=$idx+1?></span>
                    </td>
                    <td>
                      <strong><?=htmlspecialchars($b['title'])?></strong>
                      <br><small class="text-muted">by <?=htmlspecialchars($b['author'])?></small>
                    </td>
                    <td class="text-end">
                      <span class="badge bg-success"><?=intval($b['times_rented'])?> rentals</span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted text-center py-4">No rental data yet</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Most Active Users</h6>
        </div>
        <div class="card-body">
          <?php if(!empty($topUsers)): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <tbody>
                  <?php foreach($topUsers as $idx => $u): ?>
                  <tr>
                    <td style="width: 30px;">
                      <span class="badge bg-info"><?=$idx+1?></span>
                    </td>
                    <td>
                      <strong><?=htmlspecialchars($u['fullname'] ?? 'Unknown')?></strong>
                      <br><small class="text-muted"><?=htmlspecialchars($u['email'] ?? '')?></small>
                    </td>
                    <td class="text-end">
                      <span class="badge bg-warning"><?=intval($u['total_rentals'])?> rentals</span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted text-center py-4">No user data yet</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Activities -->
  <div class="row g-3">
    <div class="col-12">
      <div class="card border-0">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Rental Activity</h6>
        </div>
        <div class="card-body">
          <?php if(!empty($recent)): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="text-muted small" style="background: #f9f9f9;">
                  <tr>
                    <th>Rental ID</th>
                    <th>User</th>
                    <th>Book</th>
                    <th>Rented</th>
                    <th>Due</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($recent as $r): ?>
                  <tr>
                    <td><strong>#<?=intval($r['id'])?></strong></td>
                    <td><?=htmlspecialchars($r['user_name'] ?? 'N/A')?></td>
                    <td><?=htmlspecialchars($r['title'] ?? 'N/A')?></td>
                    <td><small><?=date('M d, Y', strtotime($r['rent_date'] ?? 'now'))?></small></td>
                    <td><small><?=date('M d, Y', strtotime($r['due_date'] ?? 'now'))?></small></td>
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
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted text-center py-4">No recent rentals</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
  // Rental Trends Chart
  const trendsData = <?=json_encode($trends)?>;
  const trendDates = trendsData.map(t => t['d']);
  const trendCounts = trendsData.map(t => parseInt(t['cnt']));

  const ctx = document.getElementById('rentalsChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: trendDates,
      datasets: [{
        label: 'Daily Rentals',
        data: trendCounts,
        borderColor: '#667eea',
        backgroundColor: 'rgba(102, 126, 234, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#667eea',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });

  // Inventory Status Chart
  const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
  new Chart(inventoryCtx, {
    type: 'doughnut',
    data: {
      labels: ['In Stock', 'Low Stock', 'Out of Stock'],
      datasets: [{
        data: [
          <?=(intval($counts['books']) - intval($inventoryStats['low_stock']) - intval($inventoryStats['out_of_stock']))?>,
          <?=intval($inventoryStats['low_stock'])?>,
          <?=intval($inventoryStats['out_of_stock'])?>
        ],
        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
        borderColor: '#fff',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
</script>

<?php include __DIR__ . '/templates/footer.php';