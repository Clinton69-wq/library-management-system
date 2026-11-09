<?php
/**
 * Books Manager & Catalog Specialist Dashboard
 * Role: books
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Strict Role Guard
require_role('books');

$currentUser = current_user();
$pdo = getDBConnection();

// Fetch latest books specialist profile
$userStmt = $pdo->prepare("SELECT id, fullname, username FROM users WHERE id = ?");
$userStmt->execute([$currentUser['id']]);
$user = $userStmt->fetch();

// -------------------------------------------------------------
// POST ACTIONS: ADD BOOK, UPDATE STOCK, DELETE BOOK
// -------------------------------------------------------------

// 1. Add New Book Title
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_book') {
    $title         = trim($_POST['title'] ?? '');
    $author        = trim($_POST['author'] ?? '');
    $isbn          = trim($_POST['isbn'] ?? '');
    $category      = trim($_POST['category'] ?? '');
    $quantity      = max(1, (int)($_POST['quantity'] ?? 1));
    $shelfLocation = trim($_POST['shelf_location'] ?? 'Section A-1');
    $description   = trim($_POST['description'] ?? '');

    if (empty($title) || empty($author) || empty($isbn) || empty($category)) {
        set_flash('error', 'Please fill in all mandatory book information (Title, Author, ISBN, Category).');
    } else {
        // Check ISBN uniqueness
        $isbnStmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
        $isbnStmt->execute([$isbn]);
        if ($isbnStmt->fetch()) {
            set_flash('error', "A book with ISBN '{$isbn}' already exists in the catalog.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, category, quantity, available_copies, shelf_location, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $author, $isbn, $category, $quantity, $quantity, $shelfLocation, $description]);
            $bookId = $pdo->lastInsertId();

            log_audit($user['username'], 'books', 'BOOK_ADDED', "Added new catalog book #{$bookId}: '{$title}' ({$quantity} copies)");
            set_flash('success', "Book <strong>" . htmlspecialchars($title) . "</strong> cataloged successfully with {$quantity} copies!");
        }
    }
    header('Location: ' . BASE_URL . '/dashboard_books.php');
    exit;
}

// 2. Update Book Stock & Shelf Location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_book') {
    $bookId        = (int)($_POST['book_id'] ?? 0);
    $quantity      = max(1, (int)($_POST['quantity'] ?? 1));
    $shelfLocation = trim($_POST['shelf_location'] ?? '');
    $category      = trim($_POST['category'] ?? '');

    $bookStmt = $pdo->prepare("SELECT id, title, quantity, available_copies FROM books WHERE id = ?");
    $bookStmt->execute([$bookId]);
    $b = $bookStmt->fetch();
    if ($b) {
        // Adjust available copies proportionately
        $diff = $quantity - $b['quantity'];
        $newAvailable = max(0, $b['available_copies'] + $diff);

        $upd = $pdo->prepare("UPDATE books SET quantity = ?, available_copies = ?, shelf_location = ?, category = ? WHERE id = ?");
        $upd->execute([$quantity, $newAvailable, $shelfLocation, $category, $bookId]);

        log_audit($user['username'], 'books', 'BOOK_STOCK_UPDATED', "Updated stock for book #{$bookId} to {$quantity} copies");
        set_flash('success', "Book <strong>" . htmlspecialchars($b['title']) . "</strong> updated successfully!");
    }
    header('Location: ' . BASE_URL . '/dashboard_books.php');
    exit;
}

// 3. Delete Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_book') {
    $bookId = (int)($_POST['book_id'] ?? 0);
    $b = $pdo->query("SELECT title FROM books WHERE id = {$bookId}")->fetch();
    if ($b) {
        $del = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $del->execute([$bookId]);
        log_audit($user['username'], 'books', 'BOOK_DELETED', "Removed book #{$bookId} ('{$b['title']}') from catalog");
        set_flash('success', "Book '{$b['title']}' removed from catalog.");
    }
    header('Location: ' . BASE_URL . '/dashboard_books.php');
    exit;
}

// -------------------------------------------------------------
// QUERIES
// -------------------------------------------------------------

// Books list
$booksList = $pdo->query("
    SELECT id, title, author, isbn, category, quantity, available_copies, shelf_location
    FROM books
    ORDER BY id DESC
")->fetchAll();

// KPIs
$totalTitles = count($booksList);
$totalStock = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM books")->fetchColumn();
$totalOnShelves = $pdo->query("SELECT COALESCE(SUM(available_copies), 0) FROM books")->fetchColumn();
$totalInCirculation = max(0, $totalStock - $totalOnShelves);
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM books WHERE available_copies = 0")->fetchColumn();

// Unique categories
$categories = $pdo->query("SELECT DISTINCT category FROM books ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Books Inventory Specialist - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Header Banner -->
    <div class="card-custom mb-4" style="border-color: #c084fc;">
        <div class="p-4 bg-gradient d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="role-pill role-books">BOOKS MANAGER</span>
                    <span class="badge bg-light text-dark border">Catalog & Inventory Desk</span>
                </div>
                <h3 class="fw-bold mb-1 text-dark">Books Repository & Catalog Desk</h3>
                <p class="text-muted small mb-0">Specialist: <strong><?= htmlspecialchars($user['fullname']) ?></strong> &bull; Manage ISBN records, physical inventory stock, and library shelf placements.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-secondary border px-3 py-2">
                    <i class="bi bi-journal-album text-primary me-1"></i> Catalog Active
                </span>
            </div>
        </div>
    </div>

    <!-- KPIs Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Cataloged Titles</span>
                    <h3 class="fw-bold mb-1 text-dark"><?= $totalTitles ?></h3>
                    <div class="small text-muted">Across <?= count($categories) ?> categories</div>
                </div>
                <div class="kpi-icon-box bg-purple bg-opacity-10" style="background:#f3e8ff; color:#7e22ce;">
                    <i class="bi bi-collection-fill"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Physical Copies</span>
                    <h3 class="fw-bold mb-1 text-dark"><?= $totalStock ?></h3>
                    <div class="small text-muted">All copies in system registry</div>
                </div>
                <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Books in Circulation</span>
                    <h3 class="fw-bold mb-1 text-warning"><?= $totalInCirculation ?></h3>
                    <div class="small text-muted"><?= $totalOnShelves ?> on physical shelves</div>
                </div>
                <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Stock Depleted Titles</span>
                    <h3 class="fw-bold mb-1 text-danger"><?= $lowStockCount ?></h3>
                    <div class="small text-muted">Zero copies currently on shelf</div>
                </div>
                <div class="kpi-icon-box bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-slash-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Book Inventory Table & Controls -->
    <div class="card-custom" id="inventory-list">
        <div class="card-custom-header">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-bookshelf text-primary me-2"></i>Master Book Registry & Inventory</h5>
                <p class="text-muted small mb-0">Control book quantities, shelf locations, and categories.</p>
            </div>
            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#newBookModal">
                <i class="bi bi-plus-circle"></i> Add New Book
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title & Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Total Stock</th>
                        <th>Available on Shelf</th>
                        <th>Shelf Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($booksList as $b): ?>
                        <tr>
                            <td>#<?= $b['id'] ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($b['title']) ?></strong>
                                <span class="small text-muted">By <?= htmlspecialchars($b['author']) ?></span>
                            </td>
                            <td><span class="font-monospace small"><?= htmlspecialchars($b['isbn']) ?></span></td>
                            <td><span class="badge bg-light text-primary border"><?= htmlspecialchars($b['category']) ?></span></td>
                            <td><strong><?= $b['quantity'] ?></strong></td>
                            <td>
                                <?php if ($b['available_copies'] > 0): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                        <?= $b['available_copies'] ?> copies
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">0 on shelf</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($b['shelf_location']) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <!-- Edit Book Trigger -->
                                    <button class="btn btn-sm btn-outline-secondary py-1 px-2" data-bs-toggle="modal" data-bs-target="#editBookModal<?= $b['id'] ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>

                                    <!-- Delete Book Form -->
                                    <form action="<?= BASE_URL ?>/dashboard_books.php" method="POST" onsubmit="return confirm('Are you sure you want to remove <?= htmlspecialchars($b['title']) ?>?');">
                                        <input type="hidden" name="action" value="delete_book">
                                        <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- MODAL: EDIT BOOK -->
                                <div class="modal fade" id="editBookModal<?= $b['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="<?= BASE_URL ?>/dashboard_books.php" method="POST">
                                                <input type="hidden" name="action" value="update_book">
                                                <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Edit Book #<?= $b['id'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Book Title</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($b['title']) ?>" readonly disabled>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Total Stock Quantity</label>
                                                            <input type="number" class="form-control" name="quantity" value="<?= $b['quantity'] ?>" min="1" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Category</label>
                                                            <input type="text" class="form-control" name="category" value="<?= htmlspecialchars($b['category']) ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Shelf Location</label>
                                                        <input type="text" class="form-control" name="shelf_location" value="<?= htmlspecialchars($b['shelf_location']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary btn-sm">Update Book</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: ADD NEW BOOK -->
<div class="modal fade" id="newBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/dashboard_books.php" method="POST">
                <input type="hidden" name="action" value="add_book">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-book-half text-primary me-2"></i>Add New Book Title to Registry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Book Title</label>
                            <input type="text" class="form-control" name="title" placeholder="e.g. Design Patterns: Elements of Reusable Object-Oriented Software" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ISBN Code</label>
                            <input type="text" class="form-control font-monospace" name="isbn" placeholder="978-0201633610" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Author(s)</label>
                            <input type="text" class="form-control" name="author" placeholder="Erich Gamma, Richard Helm, Ralph Johnson, John Vlissides" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" placeholder="Software Engineering" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Initial Copies Quantity</label>
                            <input type="number" class="form-control" name="quantity" value="5" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shelf Location</label>
                            <input type="text" class="form-control" name="shelf_location" placeholder="Floor 2, Shelf CS-102" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Book Description / Abstract</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief summary of book contents..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                        <i class="bi bi-check-lg"></i> Register Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
