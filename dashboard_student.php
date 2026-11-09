<?php
/**
 * Student Portal Dashboard
 * Role: students
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Strict Role Guard
require_role('students');

$currentUser = current_user();
$pdo = getDBConnection();

// Fetch only the account fields needed by the student dashboard.
$userStmt = $pdo->prepare("SELECT id, fullname, username FROM users WHERE id = ?");
$userStmt->execute([$currentUser['id']]);
$user = $userStmt->fetch();

// System Configurations for Students
$maxLoanDays = (int)get_system_config('max_loan_days', 14);
$finePerDay = (float)get_system_config('fine_per_day', 1.50);
$maxBooksPerStudent = (int)get_system_config('max_books_per_student', 5);

// Handle Borrow Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_borrow') {
    $bookId = (int)($_POST['book_id'] ?? 0);

    // 1. Check student's current active loans
    $activeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM book_loans WHERE user_id = ? AND status IN ('borrowed', 'overdue')");
    $activeCountStmt->execute([$user['id']]);
    $activeCount = (int)$activeCountStmt->fetchColumn();

    if ($activeCount >= $maxBooksPerStudent) {
        set_flash('error', "Borrow limit reached! You already have {$activeCount} active book loans. Institutional maximum is {$maxBooksPerStudent}.");
    } else {
        // 2. Check book availability
        $bookStmt = $pdo->prepare("SELECT id, title, available_copies FROM books WHERE id = ?");
        $bookStmt->execute([$bookId]);
        $book = $bookStmt->fetch();

        if (!$book) {
            set_flash('error', 'Book not found.');
        } elseif ($book['available_copies'] <= 0) {
            set_flash('error', "Sorry, all copies of '{$book['title']}' are currently on loan.");
        } else {
            // Create loan
            $borrowDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime("+{$maxLoanDays} days"));

            $pdo->beginTransaction();
            try {
                // Decrement book availability
                $updBook = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
                $updBook->execute([$bookId]);

                // Insert loan
                $insLoan = $pdo->prepare("INSERT INTO book_loans (book_id, user_id, issued_by, borrow_date, due_date, status) VALUES (?, ?, 'Student Self-Service Portal', ?, ?, 'borrowed')");
                $insLoan->execute([$bookId, $user['id'], $borrowDate, $dueDate]);

                $pdo->commit();

                log_audit($user['username'], 'students', 'BOOK_BORROWED', "Student borrowed '{$book['title']}' (Loan due: {$dueDate})");
                set_flash('success', "Success! You have borrowed <strong>" . htmlspecialchars($book['title']) . "</strong>. Please return by <strong>" . date('M d, Y', strtotime($dueDate)) . "</strong> (14-day loan).");
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Failed to process borrow request: ' . $e->getMessage());
            }
        }
    }
    header('Location: ' . BASE_URL . '/dashboard_student.php');
    exit;
}

// Search Filter
$search = trim($_GET['q'] ?? '');
if (!empty($search)) {
    $booksStmt = $pdo->prepare("
        SELECT id, title, author, isbn, category, quantity, available_copies, shelf_location, description
        FROM books WHERE title LIKE ?
        UNION
        SELECT id, title, author, isbn, category, quantity, available_copies, shelf_location, description
        FROM books WHERE author LIKE ?
        UNION
        SELECT id, title, author, isbn, category, quantity, available_copies, shelf_location, description
        FROM books WHERE category LIKE ?
        UNION
        SELECT id, title, author, isbn, category, quantity, available_copies, shelf_location, description
        FROM books WHERE isbn LIKE ?
        ORDER BY title ASC
    ");
    $term = "%{$search}%";
    $booksStmt->execute([$term, $term, $term, $term]);
    $catalogBooks = $booksStmt->fetchAll();
} else {
    $catalogBooks = $pdo->query("
        SELECT id, title, author, isbn, category, quantity, available_copies, shelf_location, description
        FROM books
        ORDER BY title ASC
    ")->fetchAll();
}

// Student's Current Active Loans
$loansStmt = $pdo->prepare("
    SELECT bl.*, b.title, b.author, b.isbn, b.category, b.shelf_location 
    FROM book_loans bl
    JOIN books b ON bl.book_id = b.id
    WHERE bl.user_id = ? AND bl.status IN ('borrowed', 'overdue')
    ORDER BY bl.due_date ASC
");
$loansStmt->execute([$user['id']]);
$activeLoans = $loansStmt->fetchAll();

// Student's Loan History
$historyStmt = $pdo->prepare("
    SELECT bl.*, b.title, b.author, b.isbn 
    FROM book_loans bl
    JOIN books b ON bl.book_id = b.id
    WHERE bl.user_id = ? AND bl.status = 'returned'
    ORDER BY bl.return_date DESC LIMIT 10
");
$historyStmt->execute([$user['id']]);
$historyLoans = $historyStmt->fetchAll();

$pageTitle = 'Student Portal - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Student Profile Greeting Banner -->
    <div class="card-custom mb-4 border-primary">
        <div class="p-4 bg-gradient d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="role-pill role-students">STUDENT ACCOUNT</span>
                    <span class="badge bg-light text-dark border">Student ID #STD-<?= str_pad((string)$user['id'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
                <h3 class="fw-bold mb-1 text-dark">Welcome, <?= htmlspecialchars($user['fullname']) ?>!</h3>
                <p class="text-muted small mb-0">Browse titles, manage your loans, and explore library resources.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-secondary border px-3 py-2">
                    <i class="bi bi-person-check text-success me-1"></i> Active Session
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Active Borrowed Books</span>
                    <h3 class="fw-bold mb-1 text-dark"><?= count($activeLoans) ?> / <?= $maxBooksPerStudent ?></h3>
                    <div class="small text-muted">Max <?= $maxBooksPerStudent ?> simultaneous loans</div>
                </div>
                <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Loan Period Policy</span>
                    <h3 class="fw-bold mb-1 text-dark"><?= $maxLoanDays ?> Days</h3>
                    <div class="small text-muted">Overdue rate: $<?= number_format($finePerDay, 2) ?>/day</div>
                </div>
                <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Two-Factor OTP</span>
                    <h3 class="fw-bold mb-1 text-success">Protected</h3>
                    <div class="small text-muted">2-Step Authentication Verified</div>
                </div>
                <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Loans Section -->
    <div class="card-custom mb-4" id="my-loans-section">
        <div class="card-custom-header">
            <h5 class="fw-bold mb-0"><i class="bi bi-journal-check text-primary me-2"></i>My Currently Borrowed Books</h5>
            <span class="badge bg-primary"><?= count($activeLoans) ?> Active</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Book Title & Author</th>
                        <th>ISBN</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Shelf Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeLoans)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                                You have no active book loans. Browse the catalog below to borrow books!
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($activeLoans as $loan): ?>
                        <?php 
                            $isOverdue = (strtotime($loan['due_date']) < time() || $loan['status'] === 'overdue');
                            $daysLeft = ceil((strtotime($loan['due_date']) - time()) / 86400);
                        ?>
                        <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($loan['title']) ?></strong>
                                <span class="small text-muted">By <?= htmlspecialchars($loan['author']) ?></span>
                            </td>
                            <td><span class="font-monospace small"><?= htmlspecialchars($loan['isbn']) ?></span></td>
                            <td class="small text-muted"><?= date('M d, Y', strtotime($loan['borrow_date'])) ?></td>
                            <td>
                                <strong><?= date('M d, Y', strtotime($loan['due_date'])) ?></strong>
                                <?php if (!$isOverdue): ?>
                                    <span class="badge bg-light text-primary border ms-1"><?= $daysLeft ?> days left</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i> Overdue (Fine applies)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">On Loan</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($loan['shelf_location']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Library Book Catalog Section -->
    <div class="card-custom mb-4" id="catalog-section">
        <div class="card-custom-header">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-search text-primary me-2"></i>Library Catalog & Book Borrowing</h5>
                <p class="text-muted small mb-0">Search books and borrow instantly with self-service checkout.</p>
            </div>
            <!-- Search Form -->
            <form action="<?= BASE_URL ?>/dashboard_student.php" method="GET" class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="q" placeholder="Search by title, author, category..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= BASE_URL ?>/dashboard_student.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="p-4">
            <div class="row g-4">
                <?php if (empty($catalogBooks)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                        No books found matching "<?= htmlspecialchars($search) ?>". Try another search term.
                    </div>
                <?php endif; ?>
                <?php foreach ($catalogBooks as $book): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border rounded-3 shadow-sm hover-shadow">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-light text-primary border"><?= htmlspecialchars($book['category']) ?></span>
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <?= $book['available_copies'] ?> / <?= $book['quantity'] ?> Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">All Borrowed</span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($book['title']) ?></h6>
                                <p class="small text-muted mb-2">By <?= htmlspecialchars($book['author']) ?></p>
                                <p class="small text-secondary mb-3 flex-grow-1" style="font-size:0.835rem;">
                                    <?= htmlspecialchars(substr($book['description'] ?? '', 0, 110)) ?>...
                                </p>
                                <div class="d-flex align-items-center justify-content-between border-top pt-2 small text-muted">
                                    <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($book['shelf_location']) ?></span>
                                    <span class="font-monospace" style="font-size:0.75rem;">ISBN: <?= htmlspecialchars($book['isbn']) ?></span>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 pt-0 pb-3">
                                <form action="<?= BASE_URL ?>/dashboard_student.php" method="POST">
                                    <input type="hidden" name="action" value="request_borrow">
                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-1" <?= ($book['available_copies'] <= 0) ? 'disabled' : '' ?>>
                                        <i class="bi bi-box-arrow-down"></i> <?= ($book['available_copies'] > 0) ? 'Borrow This Book' : 'Unavailable' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
