<?php
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
$book = new Book();
$latest = $book->search('', true);
$uctrl = new UserController();
$users = $uctrl->listAll(false);

// prepare user stats for JS
$userStats = [];
foreach ($users as $u) {
    $userStats[$u['id']] = $uctrl->getStats($u['id']);
}

include __DIR__ . '/templates/header.php';
?>

<div class="container py-4">
  <h2 class="mb-4 text-center" style="font-family:'Poppins',sans-serif;font-weight:700;letter-spacing:0.5px;color:#4f03c8;">Book Gallery</h2>
  <div class="gallery-grid row g-4 justify-content-center">
    <?php foreach($latest as $b): ?>
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card gallery-card h-100 shadow-sm border-0" role="button" onclick="openRentalModal(<?=htmlspecialchars(json_encode($b))?>)" data-bs-toggle="modal" data-bs-target="#rentalModal">
          <?php if(!empty($b['image'])): ?>
            <img src="/bookrent_db/public/uploads/<?=htmlspecialchars($b['image'])?>" alt="<?=htmlspecialchars($b['title'])?>">
          <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px; color:#9aa; font-size:48px">📚</div>
          <?php endif; ?>
          <div class="card-body">
            <h5 class="card-title mb-1" style="font-family:'Poppins',sans-serif;font-weight:600;letter-spacing:0.2px;"><?=htmlspecialchars($b['title'])?></h5>
            <div class="text-muted small mb-1">by <?=htmlspecialchars($b['author'])?></div>
            <span class="badge <?=intval($b['available_copies'])>0?'badge-available':'badge-out'?>">Available: <?=intval($b['available_copies'])?></span>
          </div>
        </div>
      </div>
    <?php endforeach;?>
  </div>
</div>

<!-- Rental Modal (Quick rent from gallery) -->
<div class="modal fade" id="rentalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="rentals.php">
        <div class="modal-header">
          <h5 class="modal-title" id="rentalModalLabel">Rent Book</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-5">
              <img id="home-rental-image" src="#" alt="Cover" class="d-none mb-2" />
              <div id="home-rental-title" class="fw-bold"></div>
              <div id="home-rental-availability"></div>
            </div>
            <div class="col-md-7">
              <input type="hidden" name="book_id" id="home-book-id" value="">
              <label class="form-label">Select User</label>
              <select class="form-select" id="home-user-select" name="user_id" required>
                <option value="">Select user</option>
                <?php foreach($users as $u): ?>
                  <option value="<?=intval($u['id'])?>"><?=htmlspecialchars($u['name'].' ('.$u['username'].')')?></option>
                <?php endforeach; ?>
              </select>
              <small id="home-user-info" class="text-muted d-block mt-1"></small>

              <label class="form-label mt-3">Duration (days)</label>
              <input class="form-control" type="number" name="duration" min="1" placeholder="Enter number of days" required>

              <label class="form-label mt-3">Payment Method</label>
              <select class="form-select" name="payment_method" required>
                <option value="">Select payment method</option>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="online">Online</option>
              </select>

              <label class="form-label mt-3">Payment Details</label>
              <textarea class="form-control" name="payment_details" rows="2" placeholder="Enter payment details (e.g., transaction ID, notes)" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button id="home-rent-btn" name="rent" class="btn btn-accent" type="submit">Rent this book</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // pass PHP data to JS
  window._BOOKRENT = window._BOOKRENT || {};
  window._BOOKRENT.users = <?=json_encode($userStats)?>;
  window._BOOKRENT.maxActive = <?= (int)(require __DIR__ . '/../src/config.php')['settings']['max_active_rentals_per_user'] ?>;
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
