<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$ctrl = new UserController();
$db = Database::getInstance()->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            // Validation
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'user');
            
            if (empty($name)) throw new Exception('Full name is required');
            if (empty($email)) throw new Exception('Email is required');
            if (empty($contact)) throw new Exception('Contact number is required');
            if (empty($password)) throw new Exception('Password is required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email format');
            if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters');
            if (!in_array($role, ['admin', 'staff', 'user'])) throw new Exception('Invalid role selected');
            
            // Check for duplicate email
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email)');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Email already exists');
            }
            
            // Prepare data with role
            $_POST['role'] = $role;
            $ctrl->register($_POST);
            Flash::add('success','User registered ✅');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $role = trim($_POST['role'] ?? 'user');
            
            // Validation
            if (empty($name)) throw new Exception('Full name is required');
            if (empty($email)) throw new Exception('Email is required');
            if (empty($contact)) throw new Exception('Contact number is required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email format');
            if (!in_array($role, ['admin', 'staff', 'user'])) throw new Exception('Invalid role selected');
            
            // Check for duplicate email (excluding current user)
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email) AND id != :id');
            $stmt->execute(['email' => $email, 'id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Email already exists');
            }
            
            $stmt = $db->prepare('UPDATE users SET fullname = :name, contact_no = :contact, email = :email, address = :address, role = :role WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'contact' => $contact,
                'email' => $email,
                'address' => $address,
                'role' => $role,
                'id' => $id
            ]);
            Flash::add('success','User updated ✅');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Flash::add('success','User deleted ✅');
        }
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: users.php'); exit;
}
include __DIR__ . '/templates/header.php';
?>
<h2 class="mb-4">Registered Users</h2>

<form method="GET" class="mb-3">
  <div class="row g-2">
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search by name, email, or contact..." value="<?=htmlspecialchars($_GET['search'] ?? '')?>">
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
      <?php if(!empty($_GET['search'])): ?>
        <a href="users.php" class="btn btn-secondary"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<div class="card p-3 mb-4">
  <form method="POST" class="row g-3 align-items-end" id="addUserForm" onsubmit="return validateAddForm()">
    <input type="hidden" name="action" value="add">
    <div class="col-md-2"><input class="form-control" name="name" placeholder="Full name" minlength="2" required></div>
    <div class="col-md-2"><input class="form-control" name="contact" placeholder="Contact No." required></div>
    <div class="col-md-2"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" name="address" placeholder="Address" required></div>
    <div class="col-md-1"><select class="form-select" name="role" required>
      <option value="user">User</option>
      <option value="staff">Staff</option>
      <option value="admin">Admin</option>
    </select></div>
    <div class="col-md-1"><input class="form-control" name="password" type="password" placeholder="Password" minlength="6" required></div>
    <div class="col-md-2"><button class="btn btn-accent w-100" type="submit"><i class="bi bi-person-plus me-1"></i> Register</button></div>
  </form>
</div>

<?php 
$searchQuery = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$users = $ctrl->listAll(false);

// Filter users by search query
if (!empty($searchQuery)) {
    $users = array_filter($users, function($u) use ($searchQuery) {
        return stripos($u['fullname'] ?? '', $searchQuery) !== false ||
               stripos($u['email'] ?? '', $searchQuery) !== false ||
               stripos($u['contact_no'] ?? '', $searchQuery) !== false;
    });
}
?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>Full Name</th>
      <th>Contact No.</th>
      <th>Email</th>
      <th>Address</th>
      <th>Role</th>
      <th style="width: 100px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($users as $u): ?>
    <tr>
      <td><?=htmlspecialchars($u['fullname'] ?? '')?></td>
      <td><?=htmlspecialchars($u['contact_no'] ?? '')?></td>
      <td><?=htmlspecialchars($u['email'] ?? '')?></td>
      <td><?=htmlspecialchars($u['address'] ?? '')?></td>
      <td><span class="badge bg-<?=($u['role'] ?? 'user') === 'admin' ? 'danger' : (($u['role'] ?? 'user') === 'staff' ? 'warning' : 'info')?>"><?=htmlspecialchars(ucfirst($u['role'] ?? 'user'))?></span></td>
      <td>
        <button class="btn btn-sm btn-outline-primary btn-sm-icon" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="editUser(<?=htmlspecialchars(json_encode($u))?>)" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=intval($u['id'])?>">
          <button type="submit" class="btn btn-sm btn-outline-danger btn-sm-icon" title="Delete"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="user-id">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" name="name" id="user-name" minlength="2" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Contact No.</label>
            <input type="text" class="form-control" name="contact" id="user-contact" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" id="user-email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="text" class="form-control" name="address" id="user-address" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" id="user-role" required>
              <option value="user">User</option>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editUser(user) {
  document.getElementById('user-id').value = user.id;
  document.getElementById('user-name').value = user.fullname;
  document.getElementById('user-contact').value = user.contact_no || '';
  document.getElementById('user-email').value = user.email;
  document.getElementById('user-address').value = user.address || '';
  document.getElementById('user-role').value = user.role || 'user';
}

function validateAddForm() {
  const name = document.querySelector('input[name="name"]').value.trim();
  const email = document.querySelector('input[name="email"]').value.trim();
  const contact = document.querySelector('input[name="contact"]').value.trim();
  const password = document.querySelector('input[name="password"]').value;
  const role = document.querySelector('select[name="role"]').value;
  
  if (name.length < 2) {
    alert('Full name must be at least 2 characters');
    return false;
  }
  if (!email.includes('@')) {
    alert('Please enter a valid email address');
    return false;
  }
  if (contact.length < 7) {
    alert('Contact number must be at least 7 characters');
    return false;
  }
  if (password.length < 6) {
    alert('Password must be at least 6 characters');
    return false;
  }
  if (!['admin', 'staff', 'user'].includes(role)) {
    alert('Please select a valid role');
    return false;
  }
  return true;
}
</script>

<?php include __DIR__ . '/templates/footer.php';