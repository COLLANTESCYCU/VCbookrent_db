<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/Rental.php';

// RBAC removed: No authentication required

$userModel = new User();
$rentalModel = new Rental();

// Get transaction history
$transactions = $userModel->getTransactionHistory($user['id']);

// Get rental details for context
$rentals = $rentalModel->getRentalsForUser($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - VCBookRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .transaction-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .transaction-rent {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .transaction-return {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        .transaction-penalty {
            background-color: #ffebee;
            color: #c62828;
        }
        .transaction-card {
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .summary-stat {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 12px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>

    <div class="container my-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="mb-4">📜 Transaction History</h2>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-stat"><?= count(array_filter($rentals, fn($r) => $r['status'] === 'active')) ?></div>
                    <div class="summary-label">Active Rentals</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-stat"><?= count($rentals) ?></div>
                    <div class="summary-label">Total Rentals</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-stat"><?= count(array_filter($rentals, fn($r) => $r['status'] === 'overdue')) ?></div>
                    <div class="summary-label">Overdue Rentals</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-stat"><?= count(array_filter($transactions, fn($t) => $t['transaction_type'] === 'penalty')) ?></div>
                    <div class="summary-label">Penalties</div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Transaction Records</h5>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="alert alert-info">No transactions yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td><small><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></small></td>
                                        <td>
                                            <span class="transaction-badge transaction-<?= $t['transaction_type'] ?>">
                                                <?= ucfirst($t['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($t['description']) ?></td>
                                        <td>
                                            <?php if ($t['amount'] > 0): ?>
                                                <strong>₱<?= number_format($t['amount'], 2) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($t['related_id']): ?>
                                                <small class="text-muted">#<?= $t['related_id'] ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Rentals Section -->
        <div class="card mt-5">
            <div class="card-header bg-light">
                <h5 class="mb-0">Active Rentals</h5>
            </div>
            <div class="card-body">
                <?php
                $activeRentals = array_filter($rentals, fn($r) => $r['status'] === 'active');
                if (empty($activeRentals)):
                ?>
                    <div class="alert alert-info">No active rentals.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Book</th>
                                    <th>Rented On</th>
                                    <th>Due Date</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeRentals as $r): 
                                    $daysLeft = max(0, (int)((strtotime($r['due_date']) - time()) / 86400));
                                    $isOverdue = $daysLeft < 0;
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['book_title']) ?></td>
                                        <td><small><?= date('M d, Y', strtotime($r['rent_date'])) ?></small></td>
                                        <td><small><?= date('M d, Y', strtotime($r['due_date'])) ?></small></td>
                                        <td>
                                            <?php if ($isOverdue): ?>
                                                <span class="badge bg-danger"><?= abs($daysLeft) ?> days overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $daysLeft ?> days</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                <?= $isOverdue ? 'OVERDUE' : 'ACTIVE' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.php'; ?>
</body>
</html>