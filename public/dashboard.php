<?php
require_once __DIR__ . '/../src/Controllers/ReportController.php';
require_once __DIR__ . '/../src/Models/Book.php';

$ctrl = new ReportController();
$bookModel = new Book();

$counts = $ctrl->counts();
$top = $ctrl->mostRentedBooks(6);
$recent = $ctrl->recentRentals(8);
$trends = $ctrl->rentalTrends('daily');
$bookStats = $bookModel->getAvailabilityStats();

include __DIR__ . '/templates/header.php';
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">Dashboard</h2>
    <small class="text-muted">Updated: <?=date('Y-m-d H:i')?></small>
  </div>

  <div class="row g-3">
    <div class="col-sm-6 col-md-3">
      <div class="card stat-card d-flex align-items-center">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <h6>Books</h6>
            <h3><?=intval($counts['books'])?></h3>
            <small class="text-muted">Available <?=intval($bookStats['available_books'])?></small>
          </div>
          <i class="bi bi-journal-bookmark stat-icon book"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card stat-card text-center">
        <div class="card-body">
          <h6>Users</h6>
          <h3><?=intval($counts['users'])?></h3>
          <small class="text-muted">Active approx. <?=intval($counts['users'])?> (see users page)</small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card stat-card text-center">
        <div class="card-body">
          <h6>Active Rentals</h6>
          <h3><?=intval($counts['active_rentals'])?></h3>
          <small class="text-muted">Overdue <?=intval($counts['overdue'])?></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card stat-card text-center">
        <div class="card-body">
          <h6>Recent Rentals</h6>
          <h3><?=intval(count($recent))?></h3>
          <small class="text-muted">Last 8 entries</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4 g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Rental Trends (Last 30 days)</span>
          <small class="text-muted">Daily</small>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="rentalsChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Top Rented Books</span>
          <small class="text-muted">By times rented</small>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach($top as $b): ?>
            <li class="list-group-item d-flex align-items-center">
              <div style="width:50px;height:60px;overflow:hidden;margin-right:12px">
                <?php if(!empty($b['image'])): ?>
                  <img src="/bookrent_db/public/uploads/<?=htmlspecialchars($b['image'])?>" style="height:60px;object-fit:cover" alt="">
                <?php else: ?>
                  <div style="width:50px;height:60px;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#9aa">📚</div>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1">
                <strong><?=htmlspecialchars($b['title'])?></strong>
                <div class="text-muted small">by <?=htmlspecialchars($b['author'])?></div>
              </div>
              <div><span class="badge bg-primary rounded-pill"><?=intval($b['times_rented'])?></span></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="row mt-4 g-3">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">Recent Rentals</div>
        <ul class="list-group list-group-flush">
          <?php foreach($recent as $r): ?>
            <li class="list-group-item">
              <div class="d-flex align-items-center">
                <div style="width:42px;height:52px;overflow:hidden;margin-right:12px">
                  <?php if(!empty($r['image'])): ?>
                    <img src="/bookrent_db/public/uploads/<?=htmlspecialchars($r['image'])?>" style="height:52px;object-fit:cover" alt="">
                  <?php else: ?>
                    <div style="width:42px;height:52px;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#9aa">📚</div>
                  <?php endif; ?>
                </div>
                <div>
                  <div><strong><?=htmlspecialchars($r['user_name'])?></strong> rented <em><?=htmlspecialchars($r['title'])?></em></div>
                  <div class="text-muted small"><?=htmlspecialchars($r['rent_date'])?> &middot; <?=htmlspecialchars($r['status'])?></div>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-header">Availability</div>
        <div class="card-body text-center">
          <canvas id="booksChart"></canvas>
          <div class="mt-2 small text-muted">Available <?=intval($bookStats['available_books'])?> of <?=intval($bookStats['total_books'])?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendsLabels = <?=json_encode(array_column($trends, 'd'))?> || [];
const trendsData = <?=json_encode(array_column($trends, 'cnt'))?> || [];

const rentalsCtx = document.getElementById('rentalsChart')?.getContext('2d');
if (rentalsCtx){
  new Chart(rentalsCtx, {
    type: 'line',
    data: { labels: trendsLabels, datasets: [{ label: 'Rentals', data: trendsData, borderColor: 'rgb(13,110,253)', backgroundColor: 'rgba(13,110,253,0.08)', tension: .2 }] },
    options: { responsive:true, maintainAspectRatio:false }
  });
}

const booksCtx = document.getElementById('booksChart')?.getContext('2d');
if (booksCtx){
  new Chart(booksCtx, {
    type: 'doughnut',
    data: { labels:['Available','Rented'], datasets:[{ data:[<?=intval($bookStats['available_books'])?>, <?=max(0,intval($bookStats['total_books'])-intval($bookStats['available_books']))?>], backgroundColor:['#198754','#dc3545'] }] },
    options:{responsive:true,maintainAspectRatio:false,cutout:'60%'}
  });
}
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
