<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Auth.php';

$book = new Book();
$auth = Auth::getInstance();
$currentUser = $auth->currentUser();
$isStaff = $auth->isStaff(); // Admin or Staff can see all users

// Get search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Search books
$latest = $book->search($searchQuery, true);
$uctrl = new UserController();
$users = $uctrl->listAll(false);

// Filter users based on role
// Staff/Admin see all users, Regular users only see themselves
if ($isStaff === false && $currentUser) {
    // Regular user: only show their own record
    $users = array_filter($users, function($u) use ($currentUser) {
        return $u['id'] === $currentUser['id'];
    });
}

// Enhance books with authors and genre info
foreach ($latest as &$b) {
    $b['authors'] = $book->getAuthors($b['id']);
    $b['stock_status'] = 'ok_stock';
    // Use available_copies for rental availability, not stock_count
    if (isset($b['available_copies'])) {
        if ($b['available_copies'] == 0) {
            $b['stock_status'] = 'out_of_stock';
        } elseif ($b['available_copies'] <= 2) {
            $b['stock_status'] = 'low_stock';
        }
    }
}
unset($b); // Important: unset reference to avoid issues in next loop

// Group books by genre and sort
$booksByGenre = [];
foreach ($latest as $b) {
    $genre = htmlspecialchars($b['genre'] ?? 'General');
    if (!isset($booksByGenre[$genre])) {
        $booksByGenre[$genre] = [];
    }
    $booksByGenre[$genre][] = $b;
}
ksort($booksByGenre); // Sort genres alphabetically

// prepare user stats for JS
$userStats = [];
foreach ($users as $u) {
    $userStats[$u['id']] = $uctrl->getStats($u['id']);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BookRent - Home</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts (Inter + Poppins) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- Choices.js for searchable selects -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
  <link rel="stylesheet" href="/bookrent_db/public/css/style.css">
  <!-- Favicon & theme -->
  <link rel="icon" href="/bookrent_db/public/favicon.ico">
  <meta name="theme-color" content="#0d6efd">
</head>
<body>
  <!-- Top Navigation Bar -->
  <nav class="navbar navbar-light bg-white border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="home.php">
        <i class="bi bi-book-half me-2" style="font-size:1.4rem; color:#4f03c8;"></i>
        <span class="h6 mb-0" style="color:#4f03c8;font-weight:700;">BookRent</span>
      </a>
      <div class="d-flex align-items-center">
        <a href="home.php" class="nav-link me-3">
          <i class="bi bi-house-door me-1"></i> Home
        </a>
        <?php if ($currentUser): ?>
          <?php if ($currentUser['role'] === 'user'): ?>
            <a href="rental_history.php" class="nav-link me-3">
              <i class="bi bi-clock-history me-1"></i> Rentals
            </a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
          </a>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline-primary btn-sm d-flex align-items-center me-2">
            <i class="bi bi-box-arrow-in-right me-2"></i> Login
          </a>
          <a href="register.php" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-2"></i> Register
          </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="app-main">
  <?php require_once __DIR__ . '/../src/Helpers/Flash.php'; Flash::init(); echo Flash::render(); ?>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h2 style="font-family:'Poppins',sans-serif;font-weight:700;letter-spacing:0.5px;color:#4f03c8;margin:0;">Book Gallery</h2>
    <form method="GET" class="d-flex gap-2" style="width: 300px;">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Search books..." value="<?=htmlspecialchars($searchQuery)?>">
      <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
      <?php if(!empty($searchQuery)): ?>
        <a href="." class="btn btn-sm btn-secondary"><i class="bi bi-x"></i></a>
      <?php endif; ?>
    </form>
  </div>

  <?php if(!empty($searchQuery) && empty($booksByGenre)): ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i> No books found matching "<strong><?=htmlspecialchars($searchQuery)?></strong>"
    </div>
  <?php elseif(!empty($searchQuery)): ?>
    <div class="alert alert-info">
      <i class="bi bi-search"></i> Results for "<strong><?=htmlspecialchars($searchQuery)?></strong>"
    </div>
  <?php endif; ?>

  <!-- Books Gallery Grouped by Genre -->
  <div class="books-container">
    <?php foreach($booksByGenre as $genre => $genreBooks): ?>
    
    <div class="genre-section mb-5">
      <h3 class="genre-heading">
        <i class="bi bi-bookmark-fill"></i> <?= $genre ?>
      </h3>
      
      <div class="row g-4">
        <?php foreach($genreBooks as $b):
          $statusBadge = 'success';
          $statusText = 'In Stock';
          if ($b['stock_status'] === 'low_stock') {
              $statusBadge = 'warning';
              $statusText = 'Low Stock';
          } elseif ($b['stock_status'] === 'out_of_stock') {
              $statusBadge = 'danger';
              $statusText = 'Out of Stock';
          }
          $authorsList = !empty($b['authors']) ? implode(', ', $b['authors']) : htmlspecialchars($b['author']);
        ?>
        
        <div class="col-md-4 col-lg-3">
          <div class="card book-card h-100 shadow-sm">
            <!-- Book Cover with Price Overlay -->
            <div class="book-cover-container position-relative" style="height: 250px; overflow: hidden; background: #f0f0f0; cursor: pointer;" role="button" onclick="openBookDetailsModal(<?=htmlspecialchars(json_encode($b))?>)" data-bs-toggle="modal" data-bs-target="#bookDetailsModal">
              <?php if(!empty($b['image'])): ?>
                <img src="uploads/<?=htmlspecialchars($b['image'])?>" 
                     alt="<?=htmlspecialchars($b['title'])?>" 
                     class="w-100 h-100" 
                     style="object-fit: cover;">
              <?php else: ?>
                <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 80px;">
                  📚
                </div>
              <?php endif; ?>
              
              <!-- Price Badge Overlay -->
              <div class="price-badge" style="position: absolute; bottom: 10px; right: 10px; background: rgba(102, 126, 234, 0.95); color: white; padding: 8px 12px; border-radius: 8px; font-weight: bold; font-size: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                ₱<?=number_format($b['price'] ?? 0, 2)?>
              </div>
              
              <!-- Stock Status Badge -->
              <div style="position: absolute; top: 10px; right: 10px;">
                <span class="badge bg-<?=$statusBadge?>"><?=$statusText?></span>
              </div>
            </div>
            
            <!-- Book Info -->
            <div class="card-body">
              <h6 class="card-title mb-2" style="color: #333; font-weight: bold; min-height: 50px;">
                <?=htmlspecialchars($b['title'])?>
              </h6>
              
              <p class="mb-2" style="font-size: 0.9rem; color: #666;">
                <strong>Author(s):</strong> <?=htmlspecialchars($authorsList)?>
              </p>
              
              <p class="mb-3" style="font-size: 0.85rem; color: #999;">
                Available: <strong><?=intval($b['available_copies'] ?? 0)?></strong> | 
                Rented: <strong><?=intval($b['times_rented'] ?? 0)?></strong> times
              </p>
            </div>
            
            <!-- Action Button -->
            <div class="card-footer">
              <?php if ($currentUser): ?>
                <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#bookDetailsModal" onclick="openBookDetailsModal(<?=htmlspecialchars(json_encode($b))?>)" title="Rent Book">
                  <i class="bi bi-cart-plus"></i> Rent Now
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled title="Log in to rent books">
                  <i class="bi bi-lock"></i> Log in to Rent
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <?php endforeach; ?>
      </div>
    </div>
    
    <?php endforeach; ?>
  </div>
</div>

<!-- Book Details & Rental Modal -->
<div class="modal fade" id="bookDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-height: 90vh;">
    <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
      <div class="modal-header">
        <h5 class="modal-title">Rent Book</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="overflow-y: auto; flex: 1;">
        <div class="row g-4">
          <!-- Book Image & Details (Left) -->
          <div class="col-md-4">
            <img id="bookDetailsImage" src="#" alt="Book cover" class="img-fluid rounded mb-3" style="max-height: 300px; object-fit: cover; width: 100%; display: none;">
            <div id="bookDetailsPlaceholder" style="width: 100%; height: 300px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 80px; margin-bottom: 1rem;">📚</div>
            
            <dl class="row small">
              <dt class="col-sm-6">Genre:</dt>
              <dd class="col-sm-6" id="bookDetailsGenre">-</dd>
              
              <dt class="col-sm-6">Status:</dt>
              <dd class="col-sm-6"><span id="bookStockStatus" class="badge bg-info">Checking...</span></dd>
              
              <dt class="col-sm-6">Stock:</dt>
              <dd class="col-sm-6"><span id="bookDetailsAvailable">0</span> <span id="stockLabel">available</span></dd>
              
              <dt class="col-sm-6">Price per Rent:</dt>
              <dd class="col-sm-6"><strong id="bookDetailsPrice">₱0.00</strong></dd>
              
              <dt class="col-sm-6">Rented:</dt>
              <dd class="col-sm-6" id="bookDetailsRented">0</dd>
            </dl>
            
            <!-- Stock Status Message -->
            <div id="stockStatusAlert" style="display: none; margin-top: 1rem;"></div>
          </div>

          <!-- Rental Form (Right) -->
          <div class="col-md-8">
            <form id="rentalForm" method="POST" action="rental_history.php">
              <input type="hidden" name="book_id" id="rentalFormBookId">
              <input type="hidden" name="quantity" id="rentalFormQuantity" value="1">
              <input type="hidden" name="rent_date" id="rentalFormRentDate">
              <input type="hidden" name="due_date" id="rentalFormDueDate">
              
              <!-- Title & Author -->
              <div class="mb-3">
                <h6 id="bookDetailsTitle" class="mb-1" style="color: #333; font-weight: bold;"></h6>
                <p id="bookDetailsAuthors" style="font-size: 0.9rem; color: #666; margin: 0;"></p>
              </div>

              <!-- ISBN -->
              <div class="mb-3">
                <label class="form-label">ISBN</label>
                <input type="text" class="form-control" id="bookDetailsISBN" readonly>
              </div>

              <hr>

              <!-- User Selection -->
              <div class="mb-3">
                <?php if ($isStaff): ?>
                  <!-- Admin/Staff can select any user -->
                  <label class="form-label">Select User *</label>
                  <select class="form-select" name="user_id" id="rentalUserSelect" required onchange="updateUserInfo()">
                    <option value="">-- Select User --</option>
                    <?php foreach($users as $u): ?>
                      <?php $stats = $uctrl->getStats($u['id']); ?>
                      <option value="<?=intval($u['id'])?>" data-status="<?=htmlspecialchars($u['status'] ?? 'active')?>" data-active="<?=isset($stats['active_rentals']) ? $stats['active_rentals'] : 0?>" data-penalties="<?=isset($stats['unpaid_penalties']) ? $stats['unpaid_penalties'] : 0?>">
                        <?=htmlspecialchars(($u['fullname'] ?? '').' ('.($u['email'] ?? '').')')?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div id="userInfoMessage" class="small mt-2 text-muted"></div>
                <?php elseif ($currentUser && count($users) > 0): ?>
                  <!-- Regular user can only select themselves -->
                  <label class="form-label">Renter</label>
                  <select class="form-select" name="user_id" id="rentalUserSelect" required onchange="updateUserInfo()">
                    <option value="">-- Select User --</option>
                    <?php foreach($users as $u): ?>
                      <?php $stats = $uctrl->getStats($u['id']); ?>
                      <option value="<?=intval($u['id'])?>" data-status="<?=htmlspecialchars($u['status'] ?? 'active')?>" data-active="<?=isset($stats['active_rentals']) ? $stats['active_rentals'] : 0?>" data-penalties="<?=isset($stats['unpaid_penalties']) ? $stats['unpaid_penalties'] : 0?>" selected>
                        <?=htmlspecialchars(($u['fullname'] ?? '').' ('.($u['email'] ?? '').')')?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-text text-muted">Only you can rent books through your account</small>
                  <div id="userInfoMessage" class="small mt-2 text-muted"></div>
                <?php else: ?>
                  <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Please <a href="login.php" class="alert-link">log in</a> to rent books.
                  </div>
                <?php endif; ?>
              </div>

              <!-- Rent Date -->
              <div class="mb-3">
                <label class="form-label">Rent Date *</label>
                <input type="date" class="form-control" name="rent_date" id="rentalRentDate" required onchange="validateDates()">
                <small class="form-text text-muted">Date when rental starts</small>
              </div>

              <!-- Due Date -->
              <div class="mb-3">
                <label class="form-label">Due Date *</label>
                <input type="date" class="form-control" name="due_date" id="rentalDueDate" required onchange="validateDates()">
                <small class="form-text text-muted">Date when the book must be returned</small>
              </div>

              <!-- Quantity -->
              <div class="mb-3">
                <label class="form-label">Quantity *</label>
                <div class="input-group">
                  <button class="btn btn-outline-secondary" type="button" onclick="decreaseQuantity()">-</button>
                  <input type="number" class="form-control text-center" name="quantity" id="rentalQuantity" value="1" min="1" max="3" required onchange="updateRentalCost()">
                  <button class="btn btn-outline-secondary" type="button" onclick="increaseQuantity()">+</button>
                </div>
                <small class="form-text text-muted" id="quantityHelp">Available: <span id="availableCopiesDisplay">0</span> copies</small>
              </div>

              <!-- Rental Cost Summary -->
              <div class="alert alert-info">
                <div class="d-flex justify-content-between">
                  <span>Unit Price:</span>
                  <strong id="rentalUnitPrice">₱0.00</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span>Quantity:</span>
                  <strong id="rentalQuantityDisplay">1</strong>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                  <span>Total Cost:</span>
                  <strong id="rentalCostDisplay">₱0.00</strong>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="flex-shrink: 0;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="proceedPaymentBtn" onclick="proceedToPayment()" disabled>
          Proceed to Payment
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm" style="max-height: 90vh;">
    <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Payment Confirmation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="rental_history.php" id="paymentForm">
        <div class="modal-body" style="overflow-y: auto; flex: 1;">
          <input type="hidden" name="book_id" id="paymentFormBookId">
          <input type="hidden" name="user_id" id="paymentFormUserId">
          <input type="hidden" name="duration" id="paymentFormDuration">
          <input type="hidden" name="rent" value="1">

          <!-- Rental Summary -->
          <div class="mb-3">
            <h6 id="paymentSummaryTitle" class="mb-2"></h6>
            <dl class="row small">
              <dt class="col-6">Renter:</dt>
              <dd class="col-6" id="paymentSummaryUser"></dd>
              <dt class="col-6">Rent Date:</dt>
              <dd class="col-6" id="paymentSummaryRentDate"></dd>
              <dt class="col-6">Due Date:</dt>
              <dd class="col-6" id="paymentSummaryDueDate"></dd>
              <dt class="col-6">Quantity:</dt>
              <dd class="col-6" id="paymentSummaryQuantity">1</dd>
              <dt class="col-6">Price:</dt>
              <dd class="col-6"><strong id="paymentSummaryPrice">₱0.00</strong></dd>
            </dl>
          </div>
          <hr>
          <!-- Payment Method -->
          <div class="mb-3">
            <label class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method" id="paymentMethodSelect" onchange="updatePaymentFields()" required>
              <option value="">Select method...</option>
              <option value="card">Card</option>
              <option value="online">Online Transfer</option>
            </select>
          </div>
          <!-- Card Fields -->
          <div class="mb-3 payment-method payment-card" style="display:none">
            <label class="form-label">Card Number</label>
            <input type="text" class="form-control" name="card_number" id="paymentCardNumber" maxlength="19" placeholder="XXXX XXXX XXXX XXXX">
          </div>
          <div class="mb-3 payment-method payment-card" style="display:none">
            <label class="form-label">Cardholder Name</label>
            <input type="text" class="form-control" name="card_holder" id="paymentCardHolder" maxlength="50" placeholder="Name on card">
          </div>
          <div class="mb-3 payment-method payment-card" style="display:none">
            <label class="form-label">Expiry Date</label>
            <input type="month" class="form-control" name="card_expiry" id="paymentCardExpiry">
          </div>
          <div class="mb-3 payment-method payment-card" style="display:none">
            <label class="form-label">CVV</label>
            <input type="text" class="form-control" name="card_cvv" id="paymentCardCVV" maxlength="4" placeholder="CVV">
          </div>
          <!-- Online Fields -->
          <div class="mb-3 payment-method payment-online" style="display:none">
            <label class="form-label">Transaction Number</label>
            <input type="text" class="form-control" name="online_transaction_no" id="paymentOnlineTxn" maxlength="30" placeholder="Enter transaction/reference number">
          </div>
        </div>
        <div class="modal-footer" style="flex-shrink: 0;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
          <button type="submit" id="paymentSubmitBtn" class="btn btn-success">
            <i class="bi bi-check-lg"></i> Complete Rental
          </button>
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

  // Store current book for rental
  let currentBook = null;

  // Open book details modal when book is clicked
  function openBookDetailsModal(book) {
    currentBook = book;
    
    // Update book display info
    document.getElementById('bookDetailsTitle').textContent = book.title;
    document.getElementById('bookDetailsAuthors').textContent = (book.authors && book.authors.length > 0) ? 'By: ' + book.authors.join(', ') : 'Author(s) unknown';
    document.getElementById('bookDetailsISBN').value = book.isbn;
    document.getElementById('bookDetailsGenre').textContent = book.genre || 'General';
    document.getElementById('bookDetailsAvailable').textContent = book.available_copies || 0;
    document.getElementById('bookDetailsPrice').textContent = '₱' + parseFloat(book.price || 0).toFixed(2);
    document.getElementById('bookDetailsRented').textContent = book.times_rented || 0;
    document.getElementById('rentalFormBookId').value = book.id;
    document.getElementById('rentalCostDisplay').textContent = '₱' + parseFloat(book.price || 0).toFixed(2);

    // Update stock status display
    const availableCopies = parseInt(book.available_copies) || 0;
    const stockStatusEl = document.getElementById('bookStockStatus');
    const stockStatusAlert = document.getElementById('stockStatusAlert');
    const rentalForm = document.getElementById('rentalForm');
    const proceedBtn = document.getElementById('proceedPaymentBtn');
    
    // Determine stock status
    let statusBadgeClass = 'bg-success';
    let statusText = 'Available';
    let canRent = true;
    let alertMessage = '';
    
    if (availableCopies === 0) {
      statusBadgeClass = 'bg-danger';
      statusText = 'Out of Stock';
      canRent = false;
      alertMessage = '❌ This book is currently out of stock and cannot be rented.';
    } else if (availableCopies <= 2) {
      statusBadgeClass = 'bg-warning';
      statusText = 'Low Stock';
      alertMessage = '⚠️ Only ' + availableCopies + ' copy/copies available. Reserve now!';
    } else {
      statusBadgeClass = 'bg-success';
      statusText = 'In Stock';
    }
    
    // Update stock status badge
    stockStatusEl.className = 'badge ' + statusBadgeClass;
    stockStatusEl.textContent = statusText;
    
    // Update stock label
    document.getElementById('stockLabel').textContent = availableCopies === 1 ? 'copy' : 'copies';
    
    // Show/hide alert
    if (alertMessage) {
      const alertClass = availableCopies === 0 ? 'alert-danger' : 'alert-warning';
      stockStatusAlert.className = 'alert ' + alertClass + ' small';
      stockStatusAlert.innerHTML = alertMessage;
      stockStatusAlert.style.display = 'block';
    } else {
      stockStatusAlert.style.display = 'none';
    }
    
    // Disable/enable rental form based on availability
    if (!canRent) {
      // Disable all form inputs
      rentalForm.querySelectorAll('input, select, button[type="button"]').forEach(el => {
        if (el.id !== 'rentalFormBookId' && el.id !== 'bookStockStatus') {
          el.disabled = true;
        }
      });
      proceedBtn.disabled = true;
      proceedBtn.title = 'Cannot rent: Book is out of stock';
    } else {
      // Enable form inputs
      rentalForm.querySelectorAll('input, select, button[type="button"]').forEach(el => {
        el.disabled = false;
      });
      proceedBtn.disabled = false;
      proceedBtn.title = '';
    }


    // Set quantity field with availability check
    const quantityInput = document.getElementById('rentalQuantity');
    const maxQuantity = Math.min(availableCopies, 3); // Max 3 copies per rental
    quantityInput.value = 1;
    quantityInput.setAttribute('max', Math.max(1, maxQuantity));
    document.getElementById('availableCopiesDisplay').textContent = availableCopies + ' (max ' + maxQuantity + ')';
    updateRentalCost();

    // Update image
    if (book.image) {
      document.getElementById('bookDetailsImage').src = 'uploads/' + book.image;
      document.getElementById('bookDetailsImage').style.display = 'block';
      document.getElementById('bookDetailsPlaceholder').style.display = 'none';
    } else {
      document.getElementById('bookDetailsImage').style.display = 'none';
      document.getElementById('bookDetailsPlaceholder').style.display = 'flex';
    }

    // Set today as default rent date
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    document.getElementById('rentalRentDate').value = todayStr;
    document.getElementById('rentalFormRentDate').value = todayStr;
    
    // Set default due date to 7 days from today
    const dueDate = new Date(today);
    dueDate.setDate(dueDate.getDate() + 7);
    const dueDateStr = dueDate.toISOString().split('T')[0];
    document.getElementById('rentalDueDate').value = dueDateStr;
    document.getElementById('rentalFormDueDate').value = dueDateStr;

    // Reset form
    const userSelect = document.getElementById('rentalUserSelect');
    // If there's only one non-empty user option (regular user case), auto-select it
    if (userSelect.options.length === 2) { // 1 separator + 1 user
      userSelect.value = userSelect.options[1].value;
      updateUserInfo(); // Trigger the info update for auto-selected user
    } else {
      userSelect.value = '';
    }
    document.getElementById('userInfoMessage').textContent = '';
    enableProceedButton();
  }

  // Update user eligibility info
  function updateUserInfo() {
    const select = document.getElementById('rentalUserSelect');
    const option = select.options[select.selectedIndex];
    const msgDiv = document.getElementById('userInfoMessage');
    
    if (!option || !option.value) {
      msgDiv.textContent = '';
      enableProceedButton();
      return;
    }

    const status = option.getAttribute('data-status');
    const active = parseInt(option.getAttribute('data-active')) || 0;
    const penalties = parseInt(option.getAttribute('data-penalties')) || 0;
    const maxActive = window._BOOKRENT.maxActive || 3;

    let msg = status + ' • ' + active + ' active rental(s)';
    if (penalties > 0) msg += ' • ' + penalties + ' unpaid penalty(ies)';

    msgDiv.innerHTML = msg;
    
    enableProceedButton();
  }

  // Validate all form fields and enable/disable proceed button
  function enableProceedButton() {
    const proceedBtn = document.getElementById('proceedPaymentBtn');
    
    // Get all form values
    const userSelect = document.getElementById('rentalUserSelect');
    const rentDate = document.getElementById('rentalRentDate').value;
    const dueDate = document.getElementById('rentalDueDate').value;
    const quantity = parseInt(document.getElementById('rentalQuantity').value) || 0;
    
    // Check if user is selected
    if (!userSelect.value) {
      proceedBtn.disabled = true;
      proceedBtn.title = 'Please select a user';
      return;
    }
    
    // Check if dates are selected
    if (!rentDate || !dueDate) {
      proceedBtn.disabled = true;
      proceedBtn.title = 'Please select rent and due dates';
      return;
    }
    
    // Check if quantity is valid
    if (quantity < 1) {
      proceedBtn.disabled = true;
      proceedBtn.title = 'Quantity must be at least 1';
      return;
    }
    
    // Check if quantity exceeds maximum per book (5 copies)
    if (quantity > 5) {
      proceedBtn.disabled = true;
      proceedBtn.title = 'Cannot rent more than 5 copies of a single book';
      return;
    }
    
    // Validate dates
    const rentDateObj = new Date(rentDate + 'T00:00:00');
    const dueDateObj = new Date(dueDate + 'T00:00:00');
    
    if (dueDateObj <= rentDateObj) {
      proceedBtn.disabled = true;
      proceedBtn.title = 'Due date must be after rent date';
      return;
    }
    
    // Check user eligibility
    const option = userSelect.options[userSelect.selectedIndex];
    const status = option.getAttribute('data-status') || 'active';
    const active = parseInt(option.getAttribute('data-active')) || 0;
    const penalties = parseInt(option.getAttribute('data-penalties')) || 0;
    const maxActive = window._BOOKRENT.maxActive || 3;
    
    // Check if user can rent
    let canRent = true;
    let reason = [];
    
    if (status !== 'active') {
      canRent = false;
      reason.push('User not active');
    }
    if (active >= maxActive) {
      canRent = false;
      reason.push('Max rentals reached');
    }
    if (penalties > 0) {
      canRent = false;
      reason.push('Unpaid penalties');
    }
    
    if (!canRent) {
      proceedBtn.disabled = true;
      proceedBtn.title = 'Cannot rent: ' + reason.join(', ');
      return;
    }
    
    // All checks passed - enable button
    proceedBtn.disabled = false;
    proceedBtn.title = '';
  }

  // Validate rent and due dates
  function validateDates() {
    const rentDateInput = document.getElementById('rentalRentDate');
    const dueDateInput = document.getElementById('rentalDueDate');

    if (!rentDateInput.value || !dueDateInput.value) {
      enableProceedButton();
      return;
    }

    try {
      const rentDate = new Date(rentDateInput.value + 'T00:00:00');
      const dueDate = new Date(dueDateInput.value + 'T00:00:00');

      // Ensure due date is after rent date
      if (dueDate <= rentDate) {
        alert('Due date must be after rent date');
        dueDateInput.value = rentDateInput.value; // Reset to same date
      }
      
      // Update hidden form fields for submission
      document.getElementById('rentalFormRentDate').value = rentDateInput.value;
      document.getElementById('rentalFormDueDate').value = dueDateInput.value;
    } catch (e) {
      console.error('Date validation error:', e);
    }
    enableProceedButton();
  }

  // Update rental cost based on quantity
  function updateRentalCost() {
    const quantity = parseInt(document.getElementById('rentalQuantity').value) || 1;
    const unitPrice = parseFloat(currentBook.price || 0);
    const totalCost = unitPrice * quantity;
    const availableCopies = parseInt(currentBook.available_copies) || 0;
    
    // Update hidden form field
    document.getElementById('rentalFormQuantity').value = quantity;
    
    document.getElementById('rentalUnitPrice').textContent = '₱' + unitPrice.toFixed(2);
    document.getElementById('rentalQuantityDisplay').textContent = quantity;
    
    // Show warning if trying to rent more than available
    if (quantity > availableCopies) {
      document.getElementById('rentalCostDisplay').innerHTML = '<span style="color: red;">❌ Only ' + availableCopies + ' copy/copies available!</span>';
    } else {
      document.getElementById('rentalCostDisplay').textContent = '₱' + totalCost.toFixed(2);
    }
    
    enableProceedButton();
  }

  // Increase quantity
  function increaseQuantity() {
    const quantityInput = document.getElementById('rentalQuantity');
    const availableCopies = parseInt(currentBook.available_copies) || 0;
    const maxQuantity = Math.min(availableCopies, 3); // Max 3 per book limit
    let quantity = parseInt(quantityInput.value) || 1;
    
    if (quantity < maxQuantity) {
      quantity++;
      quantityInput.value = quantity;
      updateRentalCost();
    } else {
      alert('Cannot rent more than ' + maxQuantity + ' available copy/copies');
    }
  }

  // Decrease quantity
  function decreaseQuantity() {
    const quantityInput = document.getElementById('rentalQuantity');
    let quantity = parseInt(quantityInput.value) || 1;
    
    if (quantity > 1) {
      quantity--;
      quantityInput.value = quantity;
      updateRentalCost();
    }
  }

  // Proceed to payment modal
  function proceedToPayment() {
    const userSelect = document.getElementById('rentalUserSelect');
    const rentDate = document.getElementById('rentalRentDate').value;
    const dueDate = document.getElementById('rentalDueDate').value;
    const quantity = parseInt(document.getElementById('rentalQuantity').value) || 1;
    const availableCopies = parseInt(currentBook.available_copies) || 0;

    // Check if any copies available
    if (availableCopies <= 0) {
      alert('This book has no available copies. Please try again when it\'s restocked.');
      return;
    }

    // Validation
    if (!userSelect.value) {
      alert('Please select a user');
      return;
    }

    if (!rentDate) {
      alert('Please select a rent date');
      return;
    }

    if (!dueDate) {
      alert('Please select a due date');
      return;
    }

    if (quantity < 1) {
      alert('Please select a quantity of at least 1');
      return;
    }

    if (quantity > availableCopies) {
      alert('Cannot rent ' + quantity + ' copies. Only ' + availableCopies + ' available.');
      return;
    }
    
    // Check max 3 copies per book limit
    if (quantity > 3) {
      alert('Cannot rent more than 3 copies of a single book');
      return;
    }

    if (!currentBook) {
      alert('Book information missing');
      return;
    }

    // Validate dates
    const rentDateObj = new Date(rentDate + 'T00:00:00');
    const dueDateObj = new Date(dueDate + 'T00:00:00');

    if (dueDateObj <= rentDateObj) {
      alert('Due date must be after rent date');
      return;
    }

    // Get user name from selected option
    const userOption = userSelect.options[userSelect.selectedIndex];
    const userName = userOption.textContent.split(' (')[0];

    // Calculate rental duration in days
    const durationMs = dueDateObj.getTime() - rentDateObj.getTime();
    const durationDays = Math.ceil(durationMs / (1000 * 60 * 60 * 24));

    // Format dates for display
    const rentDateDisplay = new Date(rentDate + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const dueDateDisplay = new Date(dueDate + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    // Populate payment modal
    document.getElementById('paymentFormBookId').value = currentBook.id;
    document.getElementById('paymentFormUserId').value = userSelect.value;
    
    // Add hidden field for duration (calculated from dates)
    let durationInput = document.getElementById('paymentFormDuration');
    if (!durationInput) {
      durationInput = document.createElement('input');
      durationInput.type = 'hidden';
      durationInput.id = 'paymentFormDuration';
      durationInput.name = 'duration';
      document.getElementById('paymentForm').appendChild(durationInput);
    }
    durationInput.value = durationDays;
    
    // Add hidden fields for dates
    let rentDateInput = document.getElementById('paymentFormRentDate');
    if (!rentDateInput) {
      rentDateInput = document.createElement('input');
      rentDateInput.type = 'hidden';
      rentDateInput.id = 'paymentFormRentDate';
      rentDateInput.name = 'rent_date';
      document.getElementById('paymentForm').appendChild(rentDateInput);
    }
    rentDateInput.value = rentDate;

    let dueDateInput = document.getElementById('paymentFormDueDate');
    if (!dueDateInput) {
      dueDateInput = document.createElement('input');
      dueDateInput.type = 'hidden';
      dueDateInput.id = 'paymentFormDueDate';
      dueDateInput.name = 'due_date';
      document.getElementById('paymentForm').appendChild(dueDateInput);
    }
    dueDateInput.value = dueDate;

    // Add hidden field for quantity
    let quantityInput = document.getElementById('paymentFormQuantity');
    if (!quantityInput) {
      quantityInput = document.createElement('input');
      quantityInput.type = 'hidden';
      quantityInput.id = 'paymentFormQuantity';
      quantityInput.name = 'quantity';
      document.getElementById('paymentForm').appendChild(quantityInput);
    }
    quantityInput.value = quantity;

    document.getElementById('paymentSummaryTitle').textContent = currentBook.title;
    document.getElementById('paymentSummaryUser').textContent = userName;
    document.getElementById('paymentSummaryRentDate').textContent = rentDateDisplay;
    document.getElementById('paymentSummaryDueDate').textContent = dueDateDisplay;
    document.getElementById('paymentSummaryQuantity').textContent = quantity + (quantity > 1 ? ' copies' : ' copy');
    
    // Calculate and display total with quantity
    const price = parseFloat(currentBook.price || 0);
    const totalPrice = price * quantity;
    document.getElementById('paymentSummaryPrice').textContent = 
      (quantity > 1 ? '₱' + price.toFixed(2) + ' × ' + quantity + ' = ' : '') + 
      '₱' + totalPrice.toFixed(2);

    // Reset payment form
    document.getElementById('paymentMethodSelect').value = '';
    document.getElementById('paymentCardNumber').value = '';
    document.getElementById('paymentCardHolder').value = '';
    document.getElementById('paymentCardExpiry').value = '';
    document.getElementById('paymentCardCVV').value = '';
    document.getElementById('paymentOnlineTxn').value = '';
    document.getElementById('paymentSubmitBtn').disabled = true;
    document.getElementById('paymentSubmitBtn').title = 'Please select a payment method';
    
    // Hide all payment fields initially
    document.querySelectorAll('.payment-method').forEach(el => el.style.display = 'none');

    // Close first modal and open payment modal
    const bookDetailsModal = bootstrap.Modal.getInstance(document.getElementById('bookDetailsModal'));
    if (bookDetailsModal) bookDetailsModal.hide();
    
    // Use setTimeout to ensure book modal closes before payment modal opens
    setTimeout(function() {
      const paymentModal = new bootstrap.Modal(document.getElementById('paymentConfirmModal'));
      paymentModal.show();
    }, 300);
  }

  // Show/hide payment fields based on method
  function updatePaymentFields() {
    const method = document.getElementById('paymentMethodSelect').value;
    // Hide all
    document.querySelectorAll('.payment-method').forEach(el => el.style.display = 'none');
    if (method === 'card') {
      document.querySelectorAll('.payment-card').forEach(el => el.style.display = 'block');
    } else if (method === 'online') {
      document.querySelectorAll('.payment-online').forEach(el => el.style.display = 'block');
    }
    validatePaymentForm();
  }

  // Validate payment form based on selected method
  function validatePaymentForm() {
    const method = document.getElementById('paymentMethodSelect').value;
    const submitBtn = document.getElementById('paymentSubmitBtn');

    if (!method) {
      submitBtn.disabled = true;
      submitBtn.title = 'Please select a payment method';
      return false;
    }

    let isValid = false;
    let errorMsg = '';

    if (method === 'card') {
      const cardNum = document.getElementById('paymentCardNumber').value.trim();
      const cardHolder = document.getElementById('paymentCardHolder').value.trim();
      const cardExpiry = document.getElementById('paymentCardExpiry').value.trim();
      const cardCVV = document.getElementById('paymentCardCVV').value.trim();
      
      // Card details are optional - allow empty or valid
      const hasCardNum = cardNum.length >= 13;
      const hasHolder = cardHolder.length > 0;
      const hasExpiry = cardExpiry.length > 0;
      const hasCVV = cardCVV.length === 3 || cardCVV.length === 4;
      
      // All filled correctly or all empty is valid
      if ((hasCardNum && hasHolder && hasExpiry && hasCVV) || 
          (!cardNum && !cardHolder && !cardExpiry && !cardCVV)) {
        isValid = true;
        errorMsg = '';
      } else if (cardNum && !hasCardNum) {
        isValid = false;
        errorMsg = 'Card number must be 13+ digits';
      } else if (cardHolder && !hasHolder) {
        isValid = false;
        errorMsg = 'Cardholder name required';
      } else if (cardExpiry && !hasExpiry) {
        isValid = false;
        errorMsg = 'Expiry date required';
      } else if (cardCVV && !hasCVV) {
        isValid = false;
        errorMsg = 'CVV must be 3-4 digits';
      }
    } else if (method === 'online') {
      const txnNo = document.getElementById('paymentOnlineTxn').value.trim();
      // Transaction number optional - always valid
      isValid = true;
      errorMsg = '';
    }

    submitBtn.disabled = !isValid;
    submitBtn.title = errorMsg ? 'Error: ' + errorMsg : '';
    return isValid;
  }

  // Add event listeners for form validation when DOM is ready
  function initPaymentListeners() {
    const cashRcvdEl = document.getElementById('paymentCashReceived');
    const cardNumEl = document.getElementById('paymentCardNumber');
    const cardHolderEl = document.getElementById('paymentCardHolder');
    const cardExpiryEl = document.getElementById('paymentCardExpiry');
    const cardCVVEl = document.getElementById('paymentCardCVV');
    const onlineTxnEl = document.getElementById('paymentOnlineTxn');
    
    if (cashRcvdEl) cashRcvdEl.addEventListener('input', validatePaymentForm);
    if (cardNumEl) cardNumEl.addEventListener('input', validatePaymentForm);
    if (cardHolderEl) cardHolderEl.addEventListener('input', validatePaymentForm);
    if (cardExpiryEl) cardExpiryEl.addEventListener('change', validatePaymentForm);
    if (cardCVVEl) cardCVVEl.addEventListener('input', validatePaymentForm);
    if (onlineTxnEl) onlineTxnEl.addEventListener('input', validatePaymentForm);
  }

  // Initialize when DOM is ready
  document.addEventListener('DOMContentLoaded', function() {
    initPaymentListeners();
  });
</script>

  </main>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Choices.js -->
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="/bookrent_db/public/js/app.js"></script>
  <footer class="bg-dark text-light text-center py-3 mt-4">
    <div class="container">
      <p class="mb-0">&copy; <?=date('Y')?> BookRent - Modern Book Rental System</p>
    </div>
  </footer>
</body>
</html>

