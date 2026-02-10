<?php
require_once __DIR__ . '/../src/Controllers/BookController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
Flash::init();
$ctrl = new BookController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['archive_id'])) {
            $ctrl->archive((int)$_POST['archive_id']);
            Flash::add('success','Book archived ✅');
        } else {
            $ctrl->add($_POST, $_FILES['image'] ?? null);
            Flash::add('success','Book added ✅');
        }
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
    header('Location: books.php'); exit;
}
$books = $ctrl->search('', false);
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
            <div class="col-md-4"><input class="form-control" placeholder="Title" name="title" required></div>
            <div class="col-md-4"><input class="form-control" placeholder="Author" name="author" required></div>
            <div class="col-md-3"><input class="form-control" placeholder="Copies" name="total_copies" type="number" value="1" min="1" required></div>
            <div class="col-md-9">
              <label class="form-label">Cover image (optional)</label>
              <input class="form-control" type="file" name="image" accept="image/*" id="book-image-input">
              <img id="book-image-preview" src="#" alt="" class="img-preview d-none" />
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
<table class="table table-hover">
  <thead><tr><th>Cover</th><th>Title</th><th>Author</th><th>Available</th><th>Times Rented</th><th style="width:120px">Actions</th></tr></thead>
  <tbody>
  <?php foreach($books as $b):?>
  <tr>
    <td style="width:80px">
      <?php if(!empty($b['image'])): ?>
        <img src="/bookrent_db/public/uploads/<?=htmlspecialchars($b['image'])?>" alt="" style="height:50px; object-fit:cover;" />
      <?php else: ?>
        <div style="width:48px;height:50px;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#9aa">📚</div>
      <?php endif; ?>
    </td>
    <td><?=htmlspecialchars($b['title'])?></td>
    <td><?=htmlspecialchars($b['author'])?></td>
    <td><?=intval($b['available_copies'])?></td>
    <td><?=intval($b['times_rented'])?></td>
    <td>
      <a href="edit_book.php?id=<?=intval($b['id'])?>" class="btn btn-sm btn-outline-secondary btn-sm-icon me-1" title="Edit"><i class="bi bi-pencil"></i></a>
      <form method="POST" action="books.php" style="display:inline" onsubmit="return confirm('Archive this book?')">
        <input type="hidden" name="archive_id" value="<?=intval($b['id'])?>">
        <button class="btn btn-sm btn-outline-danger btn-sm-icon" type="submit" title="Archive"><i class="bi bi-archive"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
<?php include __DIR__ . '/templates/footer.php'; ?>