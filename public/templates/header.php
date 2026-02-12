<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BookRent</title>
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
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- mobile toggle -->
  <div class="d-md-none p-2 bg-white border-bottom">
    <button class="btn btn-light" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar"><i class="bi bi-list"></i></button>
    <span class="ms-2 text-muted">Menu</span>
  </div>

  <!-- Logout button top right -->
  <div class="position-absolute top-0 end-0 mt-3 me-4">
    <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center">
      <i class="bi bi-box-arrow-right me-2"></i> Logout
    </a>
  </div>

  <main class="app-main container py-4">
  <?php require_once __DIR__ . '/../../src/Helpers/Flash.php'; Flash::init(); echo Flash::render();