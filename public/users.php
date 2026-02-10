<?php
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$ctrl = new UserController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ctrl->register($_POST);
        Flash::add('success','User registered ✅');
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: users.php'); exit;
}
include __DIR__ . '/templates/header.php';
?>
<h2 class="mb-4">Registered Users</h2>
<div class="card p-3 mb-4">
  <form method="POST" class="row g-3 align-items-end">
    <div class="col-md-3"><input class="form-control" name="name" placeholder="Full name" required></div>
    <div class="col-md-2"><input class="form-control" name="username" placeholder="Username" required></div>
    <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" name="password" type="password" placeholder="Password" required></div>
    <div class="col-md-2"><button class="btn btn-accent w-100" type="submit"><i class="bi bi-person-plus me-1"></i> Register</button></div>
  </form>
</div>

<?php $users = $ctrl->listAll(false); ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>Name</th>
      <th>Username</th>
      <th>Email</th>
      <th>Role</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($users as $u): ?>
    <tr>
      <td><?=htmlspecialchars($u['name'])?></td>
      <td><?=htmlspecialchars($u['username'])?></td>
      <td><?=htmlspecialchars($u['email'])?></td>
      <td><span class="badge bg-secondary"><?=htmlspecialchars($u['role'] ?? 'user')?></span></td>
      <td>
        <span class="badge <?=($u['status']??'active')==='active'?'bg-success':'bg-danger'?>">
          <?=ucfirst($u['status']??'active')?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>