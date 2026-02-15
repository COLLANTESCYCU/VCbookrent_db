<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/InventoryController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
require_once __DIR__ . '/../src/Models/Book.php';

$invCtrl = new InventoryController();
$bookModel = new Book();

Flash::init();

// Handle restock action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_book_id'])) {
    try {
        $bookId = (int)$_POST['restock_book_id'];
        $quantity = (int)($_POST['restock_quantity'] ?? 0);
        $reason = $_POST['restock_reason'] ?? 'Manual restock';

        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than 0');
        }

        $invCtrl->restockBook($bookId, $quantity, $reason, null);
        Flash::add('success', 'Book stock updated successfully ✅');
        header('Location: inventory.php');
        exit;
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
}

// Handle edit stock action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_stock_book_id'])) {
    try {
        $bookId = (int)$_POST['edit_stock_book_id'];
        $newStock = (int)($_POST['edit_stock_new_stock'] ?? 0);
        if ($newStock < 0) {
            throw new Exception('Stock cannot be negative');
        }
        // Directly set stock_count and available_copies
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare('UPDATE books SET stock_count = :stock, total_copies = :stock, available_copies = :stock WHERE id = :id');
        $stmt->execute(['stock' => $newStock, 'id' => $bookId]);
        // Log inventory transaction
        $stmt = $pdo->prepare('INSERT INTO inventory_logs (book_id, action, quantity_change, reason, created_by) VALUES (:bid, :action, :qty, :reason, :uid)');
        $stmt->execute([
            'bid' => $bookId,
            'action' => 'edit_stock',
            'qty' => $newStock,
            'reason' => 'Manual stock edit',
            'uid' => null
        ]);
        Flash::add('success', 'Book stock updated successfully ✅');
        header('Location: inventory.php');
        exit;
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
}

// Handle discontinue (deactivate) action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discontinue_id'])) {
    try {
        $bookId = (int)$_POST['discontinue_id'];
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare('UPDATE books SET archived = 1 WHERE id = :id');
        $stmt->execute(['id' => $bookId]);
        Flash::add('success', 'Book discontinued (deactivated) successfully.');
        header('Location: inventory.php');
        exit;
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
}

// Handle activate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_id'])) {
    try {
        $bookId = (int)$_POST['activate_id'];
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare('UPDATE books SET archived = 0 WHERE id = :id');
        $stmt->execute(['id' => $bookId]);
        Flash::add('success', 'Book activated successfully.');
        header('Location: inventory.php');
        exit;
    } catch (Exception $e) {
        Flash::add('danger', $e->getMessage());
    }
}

$stats = $invCtrl->getStats();
$allBooks = [];
$pdo = Database::getInstance()->pdo();
// Fetch books with genre from genres table
$stmt = $pdo->query('SELECT b.*, COALESCE(g.name, "General") as genre FROM books b LEFT JOIN genres g ON b.genre_id = g.id ORDER BY b.archived DESC, b.title');
$booksRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Add authors to each book
foreach ($booksRaw as &$book) {
    $book['authors'] = $bookModel->getAuthors($book['id']);
    $allBooks[] = $book;
}
$logs = $invCtrl->getTransactionLogs(null, 100);

require_once __DIR__ . '/templates/header.php';
?>

    <div class="container-fluid my-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="mb-2">📦 Stock Management</h2>
                <p class="text-muted">Manage inventory and track stock levels of existing books</p>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i>
            <strong>Stock Management Only:</strong> This section is for managing copies/stocks of existing books. 
            To add new books to the system, use the <a href="books.php" class="alert-link">Books section</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <?= Flash::render() ?>

        <!-- Stats Cards -->
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="inventory-card card-out-of-stock">
                    <div class="stat-number"><?= $stats['out_of_stock'] ?></div>
                    <div class="stat-label">Out of Stock</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="inventory-card card-low-stock">
                    <div class="stat-number"><?= $stats['low_stock'] ?></div>
                    <div class="stat-label">Low Stock</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="inventory-card card-ok-stock">
                    <div class="stat-number"><?= $stats['ok_stock'] ?></div>
                    <div class="stat-label">Good Stock</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="inventory-card card-total">
                    <div class="stat-number">₱<?= number_format($stats['total_value'], 0) ?></div>
                    <div class="stat-label">Total Value</div>
                </div>
            </div>
        </div>

        <!-- Books Inventory Table -->
        <div class="card mb-5">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Book Stock Status</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Genre</th>
                                <th>Author(s)</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <?php foreach ($allBooks as $book): ?>
                                <tr<?= $book['archived'] ? ' style="background-color: #e9ecef; color: #888;"' : '' ?>>
                                    <td>
                                        <strong><?= htmlspecialchars($book['title']) ?></strong>
                                        <br>
                                        <small class="text-muted">ISBN: <?= htmlspecialchars($book['isbn']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($book['genre'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($book['authors'])): ?>
                                            <?= htmlspecialchars(implode(', ', $book['authors'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>₱<?= isset($book['price']) && $book['price'] !== null ? number_format($book['price'], 2) : '0.00' ?></td>
                                    <td>
                                        <strong><?= isset($book['stock_count']) ? $book['stock_count'] : '0' ?></strong>
                                        <br>
                                        <small class="text-muted">Min: <?= isset($book['restock_min_level']) ? $book['restock_min_level'] : '-' ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = isset($book['stock_status']) && $book['stock_status'] !== null ? $book['stock_status'] : 'ok_stock';
                                        ?>
                                        <span class="stock-status-badge status-<?= htmlspecialchars($status) ?>">
                                            <?= ucfirst(str_replace('_', ' ', (string)$status)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-row flex-wrap gap-2 align-items-center">
                                            <?php if (!$book['archived']): ?>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#restockModal" onclick="setRestockBook(<?= $book['id'] ?>, '<?= htmlspecialchars($book['title']) ?>')">
                                                    <i class="bi bi-arrow-repeat"></i> Add Stock
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editStockModal" onclick="setEditStockBook(<?= $book['id'] ?>, '<?= htmlspecialchars($book['title']) ?>', <?= (int)$book['stock_count'] ?>)"><i class="bi bi-pencil"></i> Edit</button>
                                                <form method="POST" action="inventory.php" style="display:inline" onsubmit="return confirm('Discontinue this book?')">
                                                    <input type="hidden" name="discontinue_id" value="<?=intval($book['id'])?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Discontinue"><i class="bi bi-x-circle"></i> Discontinue</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="inventory.php" style="display:inline">
                                                    <input type="hidden" name="activate_id" value="<?=intval($book['id'])?>">
                                                    <button class="btn btn-sm btn-success" type="submit" title="Activate"><i class="bi bi-arrow-counterclockwise"></i> Activate</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventory Transaction Logs -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Stock Transaction History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info">No inventory transactions yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Book</th>
                                    <th>Action</th>
                                    <th>Quantity</th>
                                    <th>Reason</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): 
                                    // Determine badge color based on action type
                                    $badgeClass = 'bg-info';
                                    switch($log['action']) {
                                        case 'restock':
                                            $badgeClass = 'bg-success';
                                            break;
                                        case 'edit_stock':
                                            $badgeClass = 'bg-warning';
                                            break;
                                        case 'discontinue':
                                            $badgeClass = 'bg-danger';
                                            break;
                                        case 'activate':
                                            $badgeClass = 'bg-info';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><small><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></small></td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['title']) ?></strong>
                                            <br>
                                            <small class="text-muted">ISBN: <?= htmlspecialchars($log['isbn']) ?></small>
                                        </td>
                                        <td><span class="badge <?=$badgeClass?>"><?= ucfirst(str_replace('_', ' ', $log['action'])) ?></span></td>
                                        <td><strong><?= intval($log['quantity_change']) > 0 ? '+' : '' ?><?= $log['quantity_change'] ?></strong></td>
                                        <td><?= htmlspecialchars($log['reason'] ?? '-') ?></td>
                                        <td><small><?= htmlspecialchars($log['restocked_by'] ?? 'System') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Restock Modal -->
    <div class="modal fade" id="restockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add Stock / Copies</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="restock_book_id" id="restock_book_id">
                        <div class="mb-3">
                            <label class="form-label">Book Title</label>
                            <input type="text" class="form-control" id="restock_book_title" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Number of Copies to Add *</label>
                            <input type="number" class="form-control" name="restock_quantity" min="1" required placeholder="Enter quantity">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Restocking</label>
                            <select class="form-select" name="restock_reason">
                                <option value="Manual restock">Manual restock</option>
                                <option value="Returned stock">Returned stock</option>
                                <option value="Purchase order">Purchase order</option>
                                <option value="Inventory correction">Inventory correction</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Stock Modal -->
    <div class="modal fade" id="editStockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title">Edit Stock Amount</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_stock_book_id" id="edit_stock_book_id">
                        <div class="mb-3">
                            <label class="form-label">Book Title</label>
                            <input type="text" class="form-control" id="edit_stock_book_title" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Set New Stock Amount *</label>
                            <input type="number" class="form-control" name="edit_stock_new_stock" id="edit_stock_new_stock" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function setRestockBook(bookId, bookTitle) {
            document.getElementById('restock_book_id').value = bookId;
            document.getElementById('restock_book_title').value = bookTitle;
        }

        function setEditStockBook(bookId, bookTitle, stock) {
            document.getElementById('edit_stock_book_id').value = bookId;
            document.getElementById('edit_stock_book_title').value = bookTitle;
            document.getElementById('edit_stock_new_stock').value = stock;
        }
    </script>

    <?php include 'templates/footer.php'; ?>