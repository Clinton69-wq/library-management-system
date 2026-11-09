<?php
/**
 * Librarian Circulation Desk Dashboard
 * Role: libarian
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Strict Role Guard
require_role('libarian');

$currentUser = current_user();
$pdo = getDBConnection();

// Fetch latest librarian profile
$userStmt = $pdo->prepare("SELECT id, fullname, username FROM users WHERE id = ?");
$userStmt->execute([$currentUser['id']]);
$user = $userStmt->fetch();

$finePerDay = (float)get_system_config('fine_per_day', 1.50);
$maxLoanDays = (int)get_system_config('max_loan_days', 14);

// -------------------------------------------------------------
// POST ACTIONS: ISSUE BOOK & PROCESS RETURN
// -------------------------------------------------------------

// 1. Issue Book to Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_book') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $bookId    = (int)($_POST['book_id'] ?? 0);
    $dueDate   = trim($_POST['due_date'] ?? '');

    if (!$studentId || !$bookId || empty($dueDate)) {
        set_flash('error', 'Please select both a student and a book, and provide a due date.');
    } else {
        // Verify book availability
        $bStmt = $pdo->prepare("SELECT id, title, available_copies FROM books WHERE id = ?");
        $bStmt->execute([$bookId]);
        $book = $bStmt->fetch();

        if (!$book || $book['available_copies'] <= 0) {
            set_flash('error', 'The selected book has no available copies.');
        } else {
            $pdo->beginTransaction();
            try {
                // Decrement book availability
                $updB = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
                $updB->execute([$bookId]);

                // Insert loan
                $ins = $pdo->prepare("INSERT INTO book_loans (book_id, user_id, issued_by, borrow_date, due_date, status) VALUES (?, ?, ?, CURDATE(), ?, 'borrowed')");
                $ins->execute([$bookId, $studentId, $user['fullname'], $dueDate]);

                $pdo->commit();

                // Get student name for audit
                $sName = $pdo->query("SELECT fullname FROM users WHERE id = {$studentId}")->fetchColumn();
                log_audit($user['username'], 'libarian', 'BOOK_ISSUED', "Issued '{$book['title']}' to student {$sName}");

                set_flash('success', "Book <strong>" . htmlspecialchars($book['title']) . "</strong> issued to <strong>" . htmlspecialchars($sName) . "</strong> successfully! Due on " . date('M d, Y', strtotime($dueDate)));
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Failed to issue book: ' . $e->getMessage());
            }
        }
    }
    header('Location: ' . BASE_URL . '/dashboard_librarian.php');
    exit;
}

// 2. Process Book Return & Calculate Fines
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_book') {
    $loanId = (int)($_POST['loan_id'] ?? 0);

    $loanStmt = $pdo->prepare("
        SELECT bl.*, b.title 
        FROM book_loans bl 
        JOIN books b ON bl.book_id = b.id 
        WHERE bl.id = ?
    ");
    $loanStmt->execute([$loanId]);
    $loan = $loanStmt->fetch();

    if (!$loan) {
        set_flash('error', 'Loan record not found.');
    } else {
        $today = date('Y-m-d');
        $dueDate = $loan['due_date'];
        $fineAmount = 0.00;
        $overdueDays = 0;

        if (strtotime($today) > strtotime($dueDate)) {
            $overdueDays = ceil((strtotime($today) - strtotime($dueDate)) / 86400);
            $fineAmount = round($overdueDays * $finePerDay, 2);
        }

        $pdo->beginTransaction();
        try {
            // Restore copy
            $updCopy = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
            $updCopy->execute([$loan['book_id']]);

            // Update loan record
            $updLoan = $pdo->prepare("UPDATE book_loans SET status = 'returned', return_date = CURDATE(), fine_amount = ? WHERE id = ?");
            $updLoan->execute([$fineAmount, $loanId]);

            $pdo->commit();

            log_audit($user['username'], 'libarian', 'BOOK_RETURNED', "Processed return for '{$loan['title']}'. Overdue days: {$overdueDays}, Fine: \${$fineAmount}");

            $fineMsg = ($fineAmount > 0) ? " (Overdue by {$overdueDays} days, assessed fine: <strong>\${$fineAmount}</strong>)" : " (Returned on time, no fines).";
            set_flash('success', "Book <strong>" . htmlspecialchars($loan['title']) . "</strong> has been checked back into the library inventory!" . $fineMsg);
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Failed to return book: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/dashboard_librarian.php');
    exit;
}

// -------------------------------------------------------------
// QUERIES
// -------------------------------------------------------------

// Loans filter
$statusFilter = $_GET['status'] ?? 'all';
$query = "
    SELECT bl.*, b.title, b.author, b.isbn, u.fullname as student_name, u.email as student_email 
    FROM book_loans bl
    JOIN books b ON bl.book_id = b.id
    JOIN users u ON bl.user_id = u.id
";

if ($statusFilter === 'active') {
    $query .= " WHERE bl.status = 'borrowed'";
} elseif ($statusFilter === 'overdue') {
    $query .= " WHERE bl.status = 'overdue' OR (bl.status = 'borrowed' AND bl.due_date < CURDATE())";
} elseif ($statusFilter === 'returned') {
    $query .= " WHERE bl.status = 'returned'";
}
$query .= " ORDER BY bl.id DESC";

$loansList = $pdo->query($query)->fetchAll();

// KPIs
$totalLoans = $pdo->query("SELECT COUNT(*) FROM book_loans")->fetchColumn();
$activeCount = $pdo->query("SELECT COUNT(*) FROM book_loans WHERE status = 'borrowed'")->fetchColumn();
$overdueCount = $pdo->query("SELECT COUNT(*) FROM book_loans WHERE status = 'overdue' OR (status = 'borrowed' AND due_date < CURDATE())")->fetchColumn();
$totalFines = $pdo->query("SELECT COALESCE(SUM(fine_amount), 0) FROM book_loans")->fetchColumn();

// Dropdowns for quick issue
$studentsList = $pdo->query("SELECT id, fullname, username FROM users WHERE role = 'students' ORDER BY fullname ASC")->fetchAll();
$availableBooksList = $pdo->query("SELECT id, title, author, available_copies FROM books WHERE available_copies > 0 ORDER BY title ASC")->fetchAll();

$pageTitle = 'Circulation Desk - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Header Banner -->
    <div class="card-custom mb-4 border-warning">
        <div class="p-4 bg-gradient d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="role-pill role-libarian">LIBRARIAN DESK</span>
                    <span class="badge bg-light text-dark border">Circulation Operations</span>
                </div>
                <h3 class="fw-bold mb-1 text-dark">Librarian Circulation Station</h3>
                <p class="text-muted small mb-0">Officer on Duty: <strong><?= htmlspecialchars($user['fullname']) ?></strong> &bull; Manage student loans, check-ins, and overdue fines.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-secondary border px-3 py-2">
                    <i class="bi bi-clock-history text-primary me-1"></i> Shift Active
                </span>
            </div>
        </div>
    </div>

    <!-- KPIs Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Loans Circulated</span>
                    <h3 class="fw-bold mb-1 text-dark"><?= $totalLoans ?></h3>
                    <div class="small text-muted">All-time circulation count</div>
                </div>
                <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Active Loans</span>
                    <h3 class="fw-bold mb-1 text-dark"><?= $activeCount ?></h3>
                    <div class="small text-muted">Currently in student hands</div>
                </div>
                <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-journal-arrow-up"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Overdue Loans</span>
                    <h3 class="fw-bold mb-1 text-danger"><?= $overdueCount ?></h3>
                    <div class="small text-muted">Rate: $<?= number_format($finePerDay, 2) ?>/day</div>
                </div>
                <div class="kpi-icon-box bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Overdue Fines</span>
                    <h3 class="fw-bold mb-1 text-dark">$<?= number_format($totalFines, 2) ?></h3>
                    <div class="small text-muted">Calculated fee registry</div>
                </div>
                <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Issue Loan Desk -->
    <div class="card-custom mb-4" id="circulation-desk">
        <div class="card-custom-header bg-light">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-plus-circle text-primary me-2"></i>Circulation Desk: Quick Issue Book</h5>
        </div>
        <div class="p-4">
            <form action="<?= BASE_URL ?>/dashboard_librarian.php" method="POST">
                <input type="hidden" name="action" value="issue_book">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Student</label>
                        <select class="form-select" name="student_id" required>
                            <option value="" disabled selected>-- Choose Student Account --</option>
                            <?php foreach ($studentsList as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['fullname']) ?> (@<?= htmlspecialchars($st['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Select Book to Issue</label>
                        <select class="form-select" name="book_id" required>
                            <option value="" disabled selected>-- Choose Available Book --</option>
                            <?php foreach ($availableBooksList as $bk): ?>
                                <option value="<?= $bk['id'] ?>">
                                    <?= htmlspecialchars($bk['title']) ?> (<?= $bk['available_copies'] ?> copies left) &bull; <?= htmlspecialchars($bk['author']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Due Date (Default <?= $maxLoanDays ?> Days)</label>
                        <input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime("+{$maxLoanDays} days")) ?>" required>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right"></i> Issue Book to Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Circulation Loans Table -->
    <div class="card-custom" id="loans-record">
        <div class="card-custom-header">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-table text-primary me-2"></i>Circulation Loan Records & Returns</h5>
                <p class="text-muted small mb-0">Track due dates, process returns, and enforce overdue fines.</p>
            </div>
            <!-- Status Filter -->
            <div class="btn-group btn-group-sm">
                <a href="<?= BASE_URL ?>/dashboard_librarian.php?status=all" class="btn btn-<?= ($statusFilter === 'all') ? 'primary' : 'outline-secondary' ?>">All Loans</a>
                <a href="<?= BASE_URL ?>/dashboard_librarian.php?status=active" class="btn btn-<?= ($statusFilter === 'active') ? 'primary' : 'outline-secondary' ?>">Active</a>
                <a href="<?= BASE_URL ?>/dashboard_librarian.php?status=overdue" class="btn btn-<?= ($statusFilter === 'overdue') ? 'primary' : 'outline-secondary' ?>">Overdue</a>
                <a href="<?= BASE_URL ?>/dashboard_librarian.php?status=returned" class="btn btn-<?= ($statusFilter === 'returned') ? 'primary' : 'outline-secondary' ?>">Returned</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Loan ID</th>
                        <th>Student Details</th>
                        <th>Book Information</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Fine Accrued</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($loansList)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No circulation loans found matching this filter.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($loansList as $ln): ?>
                        <?php 
                            $isOverdue = ($ln['status'] === 'overdue' || ($ln['status'] === 'borrowed' && strtotime($ln['due_date']) < time()));
                        ?>
                        <tr class="<?= ($isOverdue && $ln['status'] !== 'returned') ? 'table-danger' : '' ?>">
                            <td>#<?= $ln['id'] ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($ln['student_name']) ?></strong>
                                <span class="small text-muted"><?= htmlspecialchars($ln['student_email']) ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($ln['title']) ?></span>
                                <span class="small text-muted">ISBN: <?= htmlspecialchars($ln['isbn']) ?></span>
                            </td>
                            <td class="small text-muted"><?= date('M d, Y', strtotime($ln['borrow_date'])) ?></td>
                            <td>
                                <strong class="<?= $isOverdue ? 'text-danger' : 'text-dark' ?>"><?= date('M d, Y', strtotime($ln['due_date'])) ?></strong>
                            </td>
                            <td>
                                <?php if ($ln['status'] === 'returned'): ?>
                                    <span class="badge bg-secondary">Returned on <?= date('M d', strtotime($ln['return_date'])) ?></span>
                                <?php elseif ($isOverdue): ?>
                                    <span class="badge bg-danger"><i class="bi bi-clock-history me-1"></i> Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-success">On Loan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ln['fine_amount'] > 0): ?>
                                    <span class="badge bg-danger">$<?= number_format($ln['fine_amount'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ln['status'] !== 'returned'): ?>
                                    <form action="<?= BASE_URL ?>/dashboard_librarian.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="return_book">
                                        <input type="hidden" name="loan_id" value="<?= $ln['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2 d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-box-arrow-in-left"></i> Check In / Return
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="bi bi-check-lg text-success"></i> Archived</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
