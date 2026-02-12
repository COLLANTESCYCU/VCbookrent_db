<aside class="sidebar d-none d-md-block bg-white border-end">
  <div class="sidebar-inner p-3">
    <a class="d-flex align-items-center mb-3 text-decoration-none text-accent" href="index.php">
      <i class="bi bi-book-half me-2" style="font-size:1.4rem"></i>
      <span class="h6 mb-0">BookRent</span>
    </a>
    <!-- DEBUG -->
    <small class="d-block text-muted mb-2">Current: <?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?></small>
    <nav class="nav nav-pills flex-column">
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php"><i class="bi bi-house-door me-2"></i> Home</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'books.php' ? 'active' : '' ?>" href="books.php"><i class="bi bi-journal-bookmark me-2"></i> Books</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php"><i class="bi bi-people me-2"></i> Users</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'rentals.php' ? 'active' : '' ?>" href="rentals.php"><i class="bi bi-cart-plus me-2"></i> Rentals</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'overdue.php' ? 'active' : '' ?>" href="overdue.php"><i class="bi bi-exclamation-triangle me-2"></i> Overdue</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'penalties.php' ? 'active' : '' ?>" href="penalties.php"><i class="bi bi-cash-stack me-2"></i> Penalties</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>" href="inventory.php"><i class="bi bi-boxes me-2"></i> Inventory</a>
    </nav>
  </div>
</aside>

<!-- Mobile offcanvas sidebar -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="mobileSidebarLabel">BookRent</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <nav class="nav nav-pills flex-column">
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php"><i class="bi bi-house-door me-2"></i> Home</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'books.php' ? 'active' : '' ?>" href="books.php"><i class="bi bi-journal-bookmark me-2"></i> Books</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php"><i class="bi bi-people me-2"></i> Users</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'rentals.php' ? 'active' : '' ?>" href="rentals.php"><i class="bi bi-cart-plus me-2"></i> Rentals</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'overdue.php' ? 'active' : '' ?>" href="overdue.php"><i class="bi bi-exclamation-triangle me-2"></i> Overdue</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'penalties.php' ? 'active' : '' ?>" href="penalties.php"><i class="bi bi-cash-stack me-2"></i> Penalties</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
      <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>" href="inventory.php"><i class="bi bi-boxes me-2"></i> Inventory</a>
    </nav>
  </div>
</div>