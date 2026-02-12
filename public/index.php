<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
$book = new Book();
$latest = $book->search('', true);
$uctrl = new UserController();
$users = $uctrl->listAll(false);

// Enhance books with authors and genre info
foreach ($latest as &$b) {
    $b['authors'] = $book->getAuthors($b['id']);
    $b['stock_status'] = 'ok_stock';
    if (isset($b['stock_count'])) {
        if ($b['stock_count'] == 0) {
            $b['stock_status'] = 'out_of_stock';
        } elseif ($b['stock_count'] <= ($b['restock_min_level'] ?? 3)) {
            $b['stock_status'] = 'low_stock';
        }
    }
}

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

include __DIR__ . '/templates/header.php';
?>

<div class="container py-4">
  <h2 class="mb-4 text-center" style="font-family:'Poppins',sans-serif;font-weight:700;letter-spacing:0.5px;color:#4f03c8;">Book Gallery</h2>

  <!-- Books Gallery Grouped by Genre -->
  <div class="books-container">
    <?php foreach($booksByGenre as $genre => $genreBooks): ?>
    
    <div class="genre-section mb-5">
      <h3 class="mb-4" style="color: #667eea; border-bottom: 3px solid #667eea; padding-bottom: 10px;">
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
            <div class="book-cover-container position-relative" style="height: 250px; overflow: hidden; background: #f0f0f0; cursor: pointer;" role="button" onclick="openRentalModal(<?=htmlspecialchars(json_encode($b))?>)" data-bs-toggle="modal" data-bs-target="#rentalModal">
              <?php if(!empty($b['image'])): ?>
                <img src="/bookrent_db/public/uploads/<?=htmlspecialchars($b['image'])?>" 
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
            <div class="card-footer bg-light">
              <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#bookDetailsModal" onclick="openBookDetailsModal(<?=htmlspecialchars(json_encode($b))?>)" title="Rent Book">
                <i class="bi bi-cart-plus"></i> Rent Now
              </button>
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
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Rent Book</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <!-- Book Image & Details (Left) -->
          <div class="col-md-4">
            <img id="bookDetailsImage" src="#" alt="Book cover" class="img-fluid rounded mb-3" style="max-height: 300px; object-fit: cover; width: 100%; display: none;">
            <div id="bookDetailsPlaceholder" style="width: 100%; height: 300px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 80px; margin-bottom: 1rem;">📚</div>
            
            <dl class="row small">
              <dt class="col-sm-6">Genre:</dt>
              <dd class="col-sm-6" id="bookDetailsGenre">-</dd>
              
              <dt class="col-sm-6">Stock:</dt>
              <dd class="col-sm-6"><span id="bookDetailsAvailable">0</span> available</dd>
              
              <dt class="col-sm-6">Price per Rent:</dt>
              <dd class="col-sm-6"><strong id="bookDetailsPrice">₱0.00</strong></dd>
              
              <dt class="col-sm-6">Rented:</dt>
              <dd class="col-sm-6" id="bookDetailsRented">0</dd>
            </dl>
          </div>

          <!-- Rental Form (Right) -->
          <div class="col-md-8">
            <form id="rentalForm" method="POST" action="rentals.php">
              <input type="hidden" name="book_id" id="rentalFormBookId">
              
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
                <label class="form-label">Select User *</label>
                <select class="form-select" name="user_id" id="rentalUserSelect" required onchange="updateUserInfo()">
                  <option value="">-- Select User --</option>
                  <?php foreach($users as $u): ?>
                    <option value="<?=intval($u['id'])?>" data-status="<?=htmlspecialchars($u['status'])?>" data-active="<?=$uctrl->getStats($u['id'])['active_rentals']?>" data-penalties="<?=$uctrl->getStats($u['id'])['unpaid_penalties']?>">
                      <?=htmlspecialchars($u['name'].' ('.$u['email'].')')?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div id="userInfoMessage" class="small mt-2 text-muted"></div>
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

              <!-- Rental Cost Summary -->
              <div class="alert alert-info">
                <div class="d-flex justify-content-between">
                  <span>Book Price:</span>
                  <strong id="rentalCostDisplay">₱0.00</strong>
                </div>
              </div>

              <div class="modal-footer p-0 mt-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="proceedPaymentBtn" onclick="proceedToPayment()" disabled>
                  Proceed to Payment
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="rentals.php" id="paymentForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Payment Confirmation</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
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
              
              <dt class="col-6">Price:</dt>
              <dd class="col-6"><strong id="paymentSummaryPrice">₱0.00</strong></dd>
            </dl>
          </div>

          <hr>

          <!-- Payment Information -->
          <h6 class="mb-3">Payment Information (Optional)</h6>
          
          <div class="mb-3">
            <label class="form-label">Cash Received</label>
            <input type="number" class="form-control" name="cash_received" id="paymentCashInput" step="0.01" min="0" placeholder="Leave blank for no cash payment" oninput="calculateChange()">
          </div>

          <div class="mb-3">
            <label class="form-label">Change Amount</label>
            <input type="text" class="form-control" id="paymentChangeDisplay" value="₱0.00" disabled>
          </div>
        </div>
        <div class="modal-footer">
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

    // Update image
    if (book.image) {
      document.getElementById('bookDetailsImage').src = '/bookrent_db/public/uploads/' + book.image;
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
    
    // Set default due date to 7 days from today
    const dueDate = new Date(today);
    dueDate.setDate(dueDate.getDate() + 7);
    const dueDateStr = dueDate.toISOString().split('T')[0];
    document.getElementById('rentalDueDate').value = dueDateStr;

    // Reset form
    document.getElementById('rentalUserSelect').value = '';
    document.getElementById('userInfoMessage').textContent = '';
    document.getElementById('proceedPaymentBtn').disabled = true;
  }

  // Update user eligibility info
  function updateUserInfo() {
    const select = document.getElementById('rentalUserSelect');
    const option = select.options[select.selectedIndex];
    const msgDiv = document.getElementById('userInfoMessage');
    const proceedBtn = document.getElementById('proceedPaymentBtn');
    
    if (!option || !option.value) {
      msgDiv.textContent = '';
      proceedBtn.disabled = true;
      return;
    }

    const status = option.getAttribute('data-status');
    const active = parseInt(option.getAttribute('data-active')) || 0;
    const penalties = parseInt(option.getAttribute('data-penalties')) || 0;
    const maxActive = window._BOOKRENT.maxActive || 3;

    let msg = status + ' • ' + active + ' active rental(s)';
    if (penalties > 0) msg += ' • ' + penalties + ' unpaid penalty(ies)';

    msgDiv.innerHTML = msg;

    // Check eligibility
    const canRent = (status === 'active') && (active < maxActive) && (penalties === 0);
    proceedBtn.disabled = !canRent;

    if (!canRent) {
      let reason = [];
      if (status !== 'active') reason.push('User not active');
      if (active >= maxActive) reason.push('Max rentals reached');
      if (penalties > 0) reason.push('Unpaid penalties');
      proceedBtn.title = 'Cannot rent: ' + reason.join(', ');
    } else {
      proceedBtn.title = '';
    }
  }

  // Validate rent and due dates
  function validateDates() {
    const rentDateInput = document.getElementById('rentalRentDate');
    const dueDateInput = document.getElementById('rentalDueDate');

    if (!rentDateInput.value || !dueDateInput.value) return;

    try {
      const rentDate = new Date(rentDateInput.value + 'T00:00:00');
      const dueDate = new Date(dueDateInput.value + 'T00:00:00');

      // Ensure due date is after rent date
      if (dueDate <= rentDate) {
        alert('Due date must be after rent date');
        dueDateInput.value = rentDateInput.value; // Reset to same date
      }
    } catch (e) {
      console.error('Date validation error:', e);
    }
  }

  // Proceed to payment modal
  function proceedToPayment() {
    const userSelect = document.getElementById('rentalUserSelect');
    const rentDate = document.getElementById('rentalRentDate').value;
    const dueDate = document.getElementById('rentalDueDate').value;

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

    document.getElementById('paymentSummaryTitle').textContent = currentBook.title;
    document.getElementById('paymentSummaryUser').textContent = userName;
    document.getElementById('paymentSummaryRentDate').textContent = rentDateDisplay;
    document.getElementById('paymentSummaryDueDate').textContent = dueDateDisplay;
    document.getElementById('paymentSummaryPrice').textContent = '₱' + parseFloat(currentBook.price || 0).toFixed(2);

    // Reset payment form
    document.getElementById('paymentCashInput').value = '';
    document.getElementById('paymentChangeDisplay').value = 'No cash payment';
    document.getElementById('paymentChangeDisplay').style.color = 'inherit';
    document.getElementById('paymentCashInput').classList.remove('is-invalid');
    document.getElementById('paymentChangeDisplay').classList.remove('is-invalid');
    document.getElementById('paymentSubmitBtn').disabled = false;

    // Close first modal and open payment modal
    const bookDetailsModal = bootstrap.Modal.getInstance(document.getElementById('bookDetailsModal'));
    if (bookDetailsModal) bookDetailsModal.hide();
    
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentConfirmModal'));
    paymentModal.show();
  }

  // Calculate change amount with validation
  function calculateChange() {
    const price = parseFloat(currentBook.price || 0);
    const cashInput = document.getElementById('paymentCashInput');
    const changeDisplay = document.getElementById('paymentChangeDisplay');
    const submitBtn = document.querySelector('#paymentForm button[type="submit"]');
    const cashReceived = parseFloat(cashInput.value) || 0;

    if (cashReceived === 0) {
      // No cash payment
      changeDisplay.value = 'No cash payment';
      changeDisplay.style.color = 'inherit';
      changeDisplay.classList.remove('is-invalid');
      cashInput.classList.remove('is-invalid');
      submitBtn.disabled = false;
    } else if (cashReceived > 0) {
      if (cashReceived < price) {
        // Insufficient payment
        changeDisplay.value = '❌ Insufficient! Need ₱' + (price - cashReceived).toFixed(2) + ' more';
        changeDisplay.style.color = 'red';
        changeDisplay.classList.add('is-invalid');
        cashInput.classList.add('is-invalid');
        submitBtn.disabled = true;
      } else {
        // Valid payment
        const change = cashReceived - price;
        changeDisplay.value = '₱' + change.toFixed(2);
        changeDisplay.style.color = 'green';
        changeDisplay.classList.remove('is-invalid');
        cashInput.classList.remove('is-invalid');
        submitBtn.disabled = false;
      }
    }
  }
</script>

<?php include __DIR__ . '/templates/footer.php';