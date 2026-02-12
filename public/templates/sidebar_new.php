<?php
require_once __DIR__ . '/../../src/Auth.php';
// RBAC removed: No authentication required
?>
<aside class="sidebar d-none d-md-block bg-white border-end">
  <div class="sidebar-inner p-3">
    <a class="d-flex align-items-center mb-3 text-decoration-none text-accent" href="index.php">
      <i class="bi bi-book-half me-2" style="font-size:1.4rem"></i>
      <span class="h6 mb-0">BookRent</span>
    </a>
    
    <!-- RBAC removed: No user info -->

    <nav class="nav nav-pills flex-column">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php"><i class="bi bi-house-door me-2"></i> Home</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'books.php' ? 'active' : '' ?>" href="books.php"><i class="bi bi-journal-bookmark me-2"></i> Books</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>" href="inventory.php"><i class="bi bi-boxes me-2"></i> Inventory</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php"><i class="bi bi-people me-2"></i> Users</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'rentals.php' ? 'active' : '' ?>" href="rentals.php"><i class="bi bi-cart-plus me-2"></i> Rentals</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'overdue.php' ? 'active' : '' ?>" href="overdue.php"><i class="bi bi-exclamation-triangle me-2"></i> Overdue</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
        <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i> Login</a>
        <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-2"></i> Register</a>
    </nav>

      <?php if ($user): ?>
        <hr>
        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
      <?php endif; ?>
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
      <?php if ($isUser): ?>
        <!-- User Navigation -->
        <a class="nav-link" href="books.php"><i class="bi bi-journal-bookmark me-2"></i> Browse Books</a>
        <a class="nav-link" href="transactions.php"><i class="bi bi-receipt me-2"></i> Transaction History</a>
      <?php elseif ($isStaff): ?>
        <!-- Staff/Admin Navigation -->
        <a class="nav-link" href="index.php"><i class="bi bi-house-door me-2"></i> Home</a>
        <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a class="nav-link" href="books.php"><i class="bi bi-journal-bookmark me-2"></i> Books</a>
        <a class="nav-link" href="inventory.php"><i class="bi bi-boxes me-2"></i> Inventory</a>
        <a class="nav-link" href="users.php"><i class="bi bi-people me-2"></i> Users</a>
        <a class="nav-link" href="rentals.php"><i class="bi bi-cart-plus me-2"></i> Rentals</a>
        <a class="nav-link" href="overdue.php"><i class="bi bi-exclamation-triangle me-2"></i> Overdue</a>
        <a class="nav-link" href="penalties.php"><i class="bi bi-cash-stack me-2"></i> Penalties</a>
        <a class="nav-link" href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
      <?php else: ?>
        <!-- Public Navigation -->
        <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i> Login</a>
        <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-2"></i> Register</a>
      <?php endif; ?>

      <?php if ($user): ?>
        <hr>
        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
      <?php endif; ?>
    </nav>
  </div>
</div>