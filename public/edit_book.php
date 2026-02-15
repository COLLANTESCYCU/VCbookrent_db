<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Models/Book.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
require_once __DIR__ . '/../src/Database.php';
Flash::init();

$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$bookId) {
    Flash::add('danger', 'Invalid book ID');
    header('Location: books.php');
    exit;
}

$bookModel = new Book();
$book = $bookModel->find($bookId);

if (!$book) {
    Flash::add('danger', 'Book not found');
    header('Location: books.php');
    exit;
}

$ctrl = new BookController();
$db = Database::getInstance()->pdo();

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Prepare data for update
        $input = [
            'title' => $_POST['title'] ?? '',
            'isbn' => $_POST['isbn'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'genre_id' => !empty($_POST['genre_id']) ? $_POST['genre_id'] : null,
        ];

        // Process authors
        $authors = isset($_POST['authors']) && is_array($_POST['authors']) 
            ? array_filter($_POST['authors'], fn($a) => !empty(trim($a)))
            : [];
        
        if (empty($authors)) {
            throw new Exception('At least one author is required');
        }
        
        $input['authors'] = $authors;

        // Update with file if provided
        $ctrl->update($bookId, $input, $_FILES['image'] ?? null);
        Flash::add('success', 'Book updated ✅');
        header('Location: books.php');
        exit;
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
}

// Get genres
$genres = [];
try {
    $stmt = $db->prepare('SELECT id, name FROM genres ORDER BY name');
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // genres table may not exist
}

include __DIR__ . '/templates/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h2>Edit Book</h2>
  <a href="books.php" class="btn btn-outline-secondary">Back to Books</a>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <div class="row g-3">
            <!-- ISBN -->
            <div class="col-md-6">
              <label class="form-label">ISBN</label>
              <input type="text" class="form-control" name="isbn" value="<?=htmlspecialchars($book['isbn'])?>" required>
            </div>

            <!-- Title -->
            <div class="col-md-6">
              <label class="form-label">Title</label>
              <input type="text" class="form-control" name="title" value="<?=htmlspecialchars($book['title'])?>" required>
            </div>

            <!-- Authors -->
            <div class="col-md-12">
              <label class="form-label">Author(s)</label>
              <div id="authors-container">
                <?php foreach($book['authors'] as $idx => $author): ?>
                <div class="input-group mb-2">
                  <input type="text" class="form-control" placeholder="Author name" name="authors[]" value="<?=htmlspecialchars($author)?>" required>
                  <button type="button" class="btn btn-outline-danger" onclick="removeAuthorField(this)"><i class="bi bi-x"></i></button>
                </div>
                <?php endforeach; ?>
                <?php if(empty($book['authors'])): ?>
                <div class="input-group mb-2">
                  <input type="text" class="form-control" placeholder="Author name" name="authors[]" required>
                  <button type="button" class="btn btn-outline-secondary" onclick="addAuthorField()"><i class="bi bi-plus"></i></button>
                </div>
                <?php else: ?>
                <div class="input-group mb-2">
                  <button type="button" class="btn btn-outline-secondary" onclick="addAuthorField()" style="width: 100%;"><i class="bi bi-plus"></i> Add More Authors</button>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Genre -->
            <div class="col-md-6">
              <label class="form-label">Genre</label>
              <select class="form-select" name="genre_id">
                <option value="">-- Select Genre --</option>
                <?php foreach($genres as $g): ?>
                <option value="<?=intval($g['id'])?>" <?=$g['id'] == $book['genre_id'] ? 'selected' : ''?>>
                  <?=htmlspecialchars($g['name'])?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Price -->
            <div class="col-md-6">
              <label class="form-label">Price (₱)</label>
              <input type="number" class="form-control" name="price" step="0.01" min="0" value="<?=number_format($book['price'] ?? 0, 2, '.', '')?>" required>
            </div>

            <!-- Cover Image -->
            <div class="col-md-12">
              <label class="form-label">Cover Image</label>
              <?php if(!empty($book['image'])): ?>
              <div class="mb-3">
                <img src="uploads/<?=htmlspecialchars($book['image'])?>" alt="Book cover" class="img-thumbnail" style="max-height: 250px; object-fit: cover;">
                <p class="text-muted small mt-2">Current: <?=htmlspecialchars($book['image'])?></p>
              </div>
              <?php endif; ?>
              <input type="file" class="form-control" name="image" accept="image/*" id="book-image-input">
              <img id="book-image-preview" src="#" alt="" class="img-thumbnail d-none mt-3" style="max-height: 150px; object-fit: cover;" />
            </div>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
            <a href="books.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Info panel -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5>Book Information</h5>
      </div>
      <div class="card-body">
        <dl class="row small">
          <dt class="col-sm-5">Stock:</dt>
          <dd class="col-sm-7"><?=intval($book['total_copies'])?> total, <?=intval($book['available_copies'])?> available</dd>

          <dt class="col-sm-5">Times Rented:</dt>
          <dd class="col-sm-7"><?=intval($book['times_rented'] ?? 0)?></dd>

          <dt class="col-sm-5">Created:</dt>
          <dd class="col-sm-7"><?=date('M d, Y', strtotime($book['created_at']))?></dd>

          <dt class="col-sm-5">Archived:</dt>
          <dd class="col-sm-7"><?=$book['archived'] ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>'?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<script>
function addAuthorField() {
  const container = document.getElementById('authors-container');
  const div = document.createElement('div');
  div.className = 'input-group mb-2';
  div.innerHTML = `
    <input type="text" class="form-control" placeholder="Author name" name="authors[]" required>
    <button type="button" class="btn btn-outline-danger" onclick="removeAuthorField(this)"><i class="bi bi-x"></i></button>
  `;
  container.appendChild(div);
}

function removeAuthorField(btn) {
  btn.closest('.input-group').remove();
}

// Image preview
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
}
</script>

<?php include __DIR__ . '/templates/footer.php';
