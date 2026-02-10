// Minimal UX helpers for BookRent
document.addEventListener('DOMContentLoaded', function(){
  // auto-dismiss non-error alerts after 4s
  Array.from(document.querySelectorAll('.alert')).forEach(function(a){
    if (!a.classList.contains('alert-danger')){
      setTimeout(function(){
        // fade then remove
        a.classList.add('fade-away');
        a.style.opacity = '0';
        setTimeout(()=>a.remove(), 600);
      }, 4000);
    }
  });

  // confirmation helper for elements with data-confirm
  document.body.addEventListener('click', function(e){
    var t = e.target.closest('[data-confirm]');
    if (!t) return;
    var msg = t.getAttribute('data-confirm') || 'Are you sure?';
    if (!confirm(msg)) e.preventDefault();
  });

  // close mobile offcanvas when clicking links inside it
  document.querySelectorAll('.offcanvas .nav-link').forEach(function(a){
    a.addEventListener('click', function(){
      var off = document.getElementById('mobileSidebar');
      if (off) {
        var bs = bootstrap.Offcanvas.getInstance(off) || new bootstrap.Offcanvas(off);
        bs.hide();
      }
    });
  });

  // make table rows clickable (data-href)
  Array.from(document.querySelectorAll('tr[data-href]')).forEach(function(r){
    r.style.cursor = 'pointer';
    r.addEventListener('click', function(){ window.location = r.dataset.href; });
  });

  // image preview for book upload
  var imgInput = document.getElementById('book-image-input');
  var imgPreview = document.getElementById('book-image-preview');
  if (imgInput && imgPreview) {
    imgInput.addEventListener('change', function(){
      var f = this.files[0];
      if (!f) return;
      var url = URL.createObjectURL(f);
      imgPreview.src = url;
      imgPreview.classList.remove('d-none');
    });

    // Clear preview when modal closes (if inside modal)
    var addBookModal = document.getElementById('addBookModal');
    if (addBookModal) {
      addBookModal.addEventListener('hidden.bs.modal', function(){
        imgInput.value = '';
        imgPreview.src = '#';
        imgPreview.classList.add('d-none');
      });
    }
  }

  // Book selection availability helper
  var bookSelect = document.getElementById('book-select');
  var bookAvailability = document.getElementById('book-availability');
  var bookImage = document.getElementById('book-selected-image');
  var userSelect = document.getElementById('user-select');
  var userInfo = document.getElementById('user-info');
  var rentBtn = document.getElementById('rent-btn');

  // Initialize Choices for searchable selects
  if (typeof Choices !== 'undefined'){
    if (bookSelect) new Choices(bookSelect, {searchEnabled:true, shouldSort:false});
    if (userSelect) new Choices(userSelect, {searchEnabled:true, shouldSort:false});
  }

  function updateAvailability(){
    var opt = bookSelect?.selectedOptions[0];
    if (!opt) { bookAvailability.textContent = ''; bookImage.classList.add('d-none'); return; }
    var a = opt.getAttribute('data-available');
    var img = opt.getAttribute('data-image');
    if (a !== null && a !== undefined) {
      bookAvailability.textContent = a + ' copies available';
      if (parseInt(a) <= 0) bookAvailability.classList.add('text-danger'); else bookAvailability.classList.remove('text-danger');
    } else {
      bookAvailability.textContent = '';
    }
    if (img) {
      bookImage.src = '/bookrent_db/public/uploads/' + img;
      bookImage.classList.remove('d-none');
    } else {
      bookImage.src = '#';
      bookImage.classList.add('d-none');
    }
  }

  function updateUserInfo(){
    var uid = userSelect?.value;
    if (!uid) { userInfo.textContent = ''; return; }
    var s = window._BOOKRENT?.users?.[uid];
    if (!s) { userInfo.textContent = ''; return; }
    var msg = s.status + ' • ' + s.active_rentals + ' active rentals';
    if (s.unpaid_penalties) msg += ' • ' + s.unpaid_penalties + ' unpaid penalty(ies)';
    userInfo.textContent = msg;

    // Disable rent button if not allowed
    var maxActive = window._BOOKRENT?.maxActive || 3;
    var can = (s.status === 'active') && (s.active_rentals < maxActive) && (s.unpaid_penalties == 0);
    if (!can) {
      rentBtn.setAttribute('disabled','disabled');
      rentBtn.title = 'User not eligible to rent';
    } else {
      rentBtn.removeAttribute('disabled');
      rentBtn.title = '';
    }
  }

  if (bookSelect) { bookSelect.addEventListener('change', updateAvailability); updateAvailability(); }
  if (userSelect) { userSelect.addEventListener('change', updateUserInfo); updateUserInfo(); }

  // --- Home page quick rental handlers ---
  var homeUserSelect = document.getElementById('home-user-select');
  var homeUserInfo = document.getElementById('home-user-info');
  var homeRentBtn = document.getElementById('home-rent-btn');

  function updateHomeUserInfo(){
    var uid = homeUserSelect?.value;
    if (!uid) { if(homeUserInfo) homeUserInfo.textContent = ''; return; }
    var s = window._BOOKRENT?.users?.[uid];
    if (!s) { homeUserInfo.textContent = ''; return; }
    var msg = s.status + ' • ' + s.active_rentals + ' active rentals';
    if (s.unpaid_penalties) msg += ' • ' + s.unpaid_penalties + ' unpaid penalty(ies)';
    if(homeUserInfo) homeUserInfo.textContent = msg;

    // determine eligibility
    var can = (s.status === 'active') && (s.active_rentals < (window._BOOKRENT?.maxActive || 3)) && (s.unpaid_penalties == 0);
    if (!can) { if(homeRentBtn) { homeRentBtn.setAttribute('disabled','disabled'); homeRentBtn.title = 'User not eligible to rent'; } }
    else { if(homeRentBtn) { homeRentBtn.removeAttribute('disabled'); homeRentBtn.title = ''; } }
  }

  if (homeUserSelect){
    if (typeof Choices !== 'undefined') new Choices(homeUserSelect, {searchEnabled:true, shouldSort:false});
    homeUserSelect.addEventListener('change', updateHomeUserInfo);
  }

  // function to open rental modal prefilled with book info
  window.openRentalModal = function(book){
    try {
      var img = document.getElementById('home-rental-image');
      var title = document.getElementById('home-rental-title');
      var avail = document.getElementById('home-rental-availability');
      var bookId = document.getElementById('home-book-id');
      if (book.image){ img.src = '/bookrent_db/public/uploads/'+book.image; img.classList.remove('d-none'); } else { img.src = '#'; img.classList.add('d-none'); }
      if (title) title.textContent = book.title + ' — ' + book.author;
      if (avail) avail.textContent = (parseInt(book.available_copies)||0) + ' copies available';
      if (bookId) bookId.value = book.id;
      // disable rent if no copies
      if (parseInt(book.available_copies) <= 0){ homeRentBtn.setAttribute('disabled','disabled'); homeRentBtn.title = 'No copies available'; }
      else { homeRentBtn.removeAttribute('disabled'); homeRentBtn.title = ''; }
      // refresh user info eligibility
      if (homeUserSelect) homeUserSelect.dispatchEvent(new Event('change'));
    } catch (e){ console.error('openRentalModal error',e); }
  };

});