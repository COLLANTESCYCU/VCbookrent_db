<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$db = Database::getInstance()->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $name = trim($_POST['genre_name'] ?? '');
            if (!$name) throw new Exception('Genre name required');
            
            // Check for duplicate
            $stmt = $db->prepare('SELECT COUNT(*) FROM genres WHERE LOWER(name) = LOWER(:name)');
            $stmt->execute(['name' => $name]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Genre already exists');
            }
            
            $stmt = $db->prepare('INSERT INTO genres (name) VALUES (:name)');
            $stmt->execute(['name' => $name]);
            Flash::add('success','Genre added ✅');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['genre_name'] ?? '');
            if (!$name) throw new Exception('Genre name required');
            
            // Check for duplicate (excluding current record)
            $stmt = $db->prepare('SELECT COUNT(*) FROM genres WHERE LOWER(name) = LOWER(:name) AND id != :id');
            $stmt->execute(['name' => $name, 'id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Genre name already exists');
            }
            
            $stmt = $db->prepare('UPDATE genres SET name = :name WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $id]);
            Flash::add('success','Genre updated ✅');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare('DELETE FROM genres WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Flash::add('success','Genre deleted ✅');
        }
    } catch (Exception $e) { Flash::add('danger', $e->getMessage()); }
    header('Location: genre.php'); exit;
}
include __DIR__ . '/templates/header.php';
$genres = $db->query('SELECT * FROM genres ORDER BY name')->fetchAll();
?>
<h2 class="mb-4">Genres</h2>

<form method="GET" class="mb-3">
  <div class="row g-2">
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search genres..." value="<?=htmlspecialchars($_GET['search'] ?? '')?>">
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
      <?php if(!empty($_GET['search'])): ?>
        <a href="genre.php" class="btn btn-secondary"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<div class="card p-3 mb-4">
  <form method="POST" class="row g-3 align-items-end">
    <input type="hidden" name="action" value="add">
    <div class="col-md-8"><input class="form-control" name="genre_name" placeholder="Genre Name" required></div>
    <div class="col-md-4"><button class="btn btn-accent w-100" type="submit"><i class="bi bi-plus-circle me-1"></i> Add Genre</button></div>
  </form>
</div>

<?php 
$searchQuery = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';

// Filter genres by search query
if (!empty($searchQuery)) {
    $genres = array_filter($genres, function($g) use ($searchQuery) {
        return stripos($g['name'] ?? '', $searchQuery) !== false;
    });
}
?>
</div>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>Genre Name</th>
      <th style="width: 100px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($genres as $g): ?>
    <tr>
      <td><?=htmlspecialchars($g['name'] ?? '')?></td>
      <td>
        <button class="btn btn-sm btn-outline-primary btn-sm-icon" data-bs-toggle="modal" data-bs-target="#editGenreModal" onclick="editGenre(<?=htmlspecialchars(json_encode($g))?>)" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this genre?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=intval($g['id'])?>">
          <button type="submit" class="btn btn-sm btn-outline-danger btn-sm-icon" title="Delete"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Edit Genre Modal -->
<div class="modal fade" id="editGenreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Genre</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="gen-id">
          <div class="mb-3">
            <label class="form-label">Genre Name</label>
            <input type="text" class="form-control" name="genre_name" id="gen-name" required>
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
function editGenre(genre) {
  document.getElementById('gen-id').value = genre.id;
  document.getElementById('gen-name').value = genre.name;
}
</script>

<?php include __DIR__ . '/templates/footer.php';
