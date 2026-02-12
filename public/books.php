<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Models/Genre.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
require_once __DIR__ . '/../src/Database.php';
Flash::init();
$ctrl = new BookController();
$bookModel = new Book();
$db = Database::getInstance()->pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['archive_id'])) {
            $ctrl->archive((int)$_POST['archive_id']);
            Flash::add('success','Book archived ✅');
        } else {
            // Process authors array
            $authors = isset($_POST['authors']) && is_array($_POST['authors']) 
                ? array_filter($_POST['authors'], fn($a) => !empty(trim($a)))
                : [];
            if (empty($authors)) {
                throw new Exception('At least one author is required');
            }
            $_POST['authors'] = $authors;
            $ctrl->add($_POST, $_FILES['image'] ?? null);
            Flash::add('success','Book added ✅');
        }
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
    header('Location: books.php'); exit;
}
$books = $ctrl->search('', false);

// Get genres for the form
$genres = [];
try {
    $stmt = $db->prepare('SELECT id, name FROM genres ORDER BY name');
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // genres table may not exist
}

// Enhance books with authors and genre info
foreach ($books as &$book) {
    $book['authors'] = $bookModel->getAuthors($book['id']);
    $book['stock_status'] = 'ok_stock';
    if (isset($book['stock_count'])) {
        if ($book['stock_count'] == 0) {
            $book['stock_status'] = 'out_of_stock';
        } elseif ($book['stock_count'] <= ($book['restock_min_level'] ?? 3)) {
            $book['stock_status'] = 'low_stock';
        }
    }
}

include __DIR__ . '/templates/header.php';
?>
<div class="d-flex align-items-center justify-content-between">
  <h2>Books</h2>
  <div>
    <a href="books.php" class="btn btn-outline-secondary me-2"><i class="bi bi-funnel"></i> Filters</a>
    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addBookModal"><i class="bi bi-plus-lg"></i> Add Book</button>
  </div>
</div>


<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="addBookModalLabel">Add New Book</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><input class="form-control" placeholder="ISBN" name="isbn" required></div>
            <div class="col-md-8"><input class="form-control" placeholder="Title" name="title" required></div>
            <div class="col-md-6">
              <label class="form-label">Author(s)</label>
              <div id="authors-container">
                <div class="input-group mb-2">
                  <input type="text" class="form-control" placeholder="Author name" name="authors[]" required>
                  <button type="button" class="btn btn-outline-secondary" onclick="addAuthorField()"><i class="bi bi-plus"></i></button>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Genre</label>
              <select class="form-select" name="genre_id">
                <option value="">-- Select Genre --</option>
                <?php foreach($genres as $g): ?>
                <option value="<?=intval($g['id'])?>"><?=htmlspecialchars($g['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Price (₱)</label>
              <input type="number" class="form-control" placeholder="0.00" name="price" step="0.01" min="0" value="0.00" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Total Copies</label>
              <input type="number" class="form-control" placeholder="Copies" name="total_copies" min="1" value="1" required>
            </div>
            <div class="col-md-12">
              <label class="form-label">Cover Image (optional)</label>
              <input class="form-control" type="file" name="image" accept="image/*" id="book-image-input">
              <img id="book-image-preview" src="#" alt="" class="img-preview d-none mt-2" style="max-height: 150px; object-fit: cover;" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add Book</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function addAuthorField() {
  const container = document.getElementById('authors-container');
  const div = document.createElement('div');
  div.className = 'input-group mb-2';
  div.innerHTML = `
    <input type="text" class="form-control" placeholder="Author name" name="authors[]">
    <button type="button" class="btn btn-outline-danger" onclick="removeAuthorField(this)"><i class="bi bi-x"></i></button>
  `;
  container.appendChild(div);
}

function removeAuthorField(btn) {
  btn.closest('.input-group').remove();
}
</script>
<table class="table table-hover">
  <thead><tr><th>Cover</th><th>Title</th><th>Genre</th><th>Author(s)</th><th>Price</th><th>Stock</th><th style="width:120px">Actions</th></tr></thead>
  <tbody>
  <?php foreach($books as $b):
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
  <tr>
    <td style="width:80px">
      <?php if(!empty($b['image'])): ?>
        <img src="/bookrent_db/public/uploads/<?=htmlspecialchars($b['image'])?>" alt="" style="height:50px; object-fit:cover;" />
      <?php else: ?>
        <div style="width:48px;height:50px;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#9aa">📚</div>
      <?php endif; ?>
    </td>
    <td><strong><?=htmlspecialchars($b['title'])?></strong></td>
    <td><?=htmlspecialchars($b['genre'] ?? 'General')?></td>
    <td><?=htmlspecialchars($authorsList)?></td>
    <td><strong>₱<?=number_format($b['price'] ?? 0, 2)?></strong></td>
    <td><span class="badge bg-<?=$statusBadge?>"><?=$statusText?></span></td>
    <td>
      <a href="edit_book.php?id=<?=intval($b['id'])?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit"><i class="bi bi-pencil"></i></a>
      <form method="POST" action="books.php" style="display:inline" onsubmit="return confirm('Archive this book?')">
        <input type="hidden" name="archive_id" value="<?=intval($b['id'])?>">
        <button class="btn btn-sm btn-outline-danger btn-sm-icon" type="submit" title="Archive"><i class="bi bi-archive"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
<?php include __DIR__ . '/templates/footer.php';