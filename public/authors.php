<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$ctrl = new BookController();
$db = Database::getInstance()->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            // Check for duplicate
            $stmt = $db->prepare('SELECT COUNT(*) FROM authors WHERE LOWER(author_name) = LOWER(:name)');
            $stmt->execute(['name' => trim($_POST['author_name'] ?? '')]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Author already exists');
            }
            $ctrl->addAuthor($_POST);
            Flash::add('success','Author added ✅');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['author_name'] ?? '');
            if (!$name) throw new Exception('Author name required');
            
            // Check for duplicate (excluding current record)
            $stmt = $db->prepare('SELECT COUNT(*) FROM authors WHERE LOWER(author_name) = LOWER(:name) AND id != :id');
            $stmt->execute(['name' => $name, 'id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Author name already exists');
            }
            
            $stmt = $db->prepare('UPDATE authors SET author_name = :name WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $id]);
            Flash::add('success','Author updated ✅');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare('DELETE FROM authors WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Flash::add('success','Author deleted ✅');
        }
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: authors.php'); exit;
}
include __DIR__ . '/templates/header.php';
?>
<h2 class="mb-4">Authors</h2>

<form method="GET" class="mb-3">
  <div class="row g-2">
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search authors..." value="<?=htmlspecialchars($_GET['search'] ?? '')?>">
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
      <?php if(!empty($_GET['search'])): ?>
        <a href="authors.php" class="btn btn-secondary"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<div class="card p-3 mb-4">
  <form method="POST" class="row g-3 align-items-end">
    <input type="hidden" name="action" value="add">
    <div class="col-md-8"><input class="form-control" name="author_name" placeholder="Author Name" required></div>
    <div class="col-md-4"><button class="btn btn-accent w-100" type="submit"><i class="bi bi-person-plus me-1"></i> Add Author</button></div>
  </form>
</div>

<?php 
$searchQuery = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
// Get all authors from authors table
$authors = $db->query('SELECT * FROM authors ORDER BY author_name')->fetchAll(PDO::FETCH_ASSOC);

// Filter authors by search query
if (!empty($searchQuery)) {
    $authors = array_filter($authors, function($a) use ($searchQuery) {
        return stripos($a['author_name'] ?? '', $searchQuery) !== false;
    });
}
?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>Author Name</th>
      <th style="width: 100px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($authors as $a): ?>
    <tr>
      <td><?=htmlspecialchars($a['author_name'] ?? '')?></td>
      <td>
        <button class="btn btn-sm btn-outline-primary btn-sm-icon" data-bs-toggle="modal" data-bs-target="#editAuthorModal" onclick="editAuthor(<?=htmlspecialchars(json_encode($a))?>)" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this author?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=intval($a['id'])?>">
          <button type="submit" class="btn btn-sm btn-outline-danger btn-sm-icon" title="Delete"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Edit Author Modal -->
<div class="modal fade" id="editAuthorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Author</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="auth-id">
          <div class="mb-3">
            <label class="form-label">Author Name</label>
            <input type="text" class="form-control" name="author_name" id="auth-name" required>
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
function editAuthor(author) {
  document.getElementById('auth-id').value = author.id;
  document.getElementById('auth-name').value = author.author_name;
}
</script>

<?php include __DIR__ . '/templates/footer.php';
