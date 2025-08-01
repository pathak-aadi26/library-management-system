<?php
session_start();
if (!isset($_SESSION['mob_no'])) {
    header('Location: login.php');
    exit;
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "member_db";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$member_mob = $_SESSION['mob_no'];
$member_query = $conn->prepare("SELECT member_id, first_name, last_name, email_id, role FROM member_db WHERE mob_no = ?");
$member_query->bind_param("s", $member_mob);
$member_query->execute();
$member_result = $member_query->get_result();
$member_data = $member_result->fetch_assoc();
$member_id = $member_data['member_id'] ?? 0;

// Redirect admin users to admin dashboard
if ($member_data['role'] === 'admin') {
    header('Location: admin_page.php');
    exit;
}

//  wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_wishlist'])) {
    $book_id = intval($_POST['book_id']);
    
    // Create wishlist table
    $conn->query("CREATE TABLE IF NOT EXISTS wishlist (
        wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        book_id INT NOT NULL,
        added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES member_db(member_id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
        UNIQUE KEY unique_wishlist (member_id, book_id)
    )");
    
    // Check wishlist
    $wishlist_check = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE member_id = ? AND book_id = ?");
    $wishlist_check->bind_param("ii", $member_id, $book_id);
    $wishlist_check->execute();
    $wishlist_result = $wishlist_check->get_result();
    
    if ($wishlist_result->num_rows > 0) {
        // Remove
        $remove_stmt = $conn->prepare("DELETE FROM wishlist WHERE member_id = ? AND book_id = ?");
        $remove_stmt->bind_param("ii", $member_id, $book_id);
        $remove_stmt->execute();
        $wishlist_message = "Book removed from wishlist!";
    } else {
        // Add
        $add_stmt = $conn->prepare("INSERT INTO wishlist (member_id, book_id, added_date) VALUES (?, ?, NOW())");
        $add_stmt->bind_param("ii", $member_id, $book_id);
        $add_stmt->execute();
        $wishlist_message = "Book added to wishlist!";
    }
}

// search
$search_query = $_GET['search'] ?? '';
$search_filter = $_GET['filter'] ?? 'all';
$search_conditions = [];
$search_params = [];
$search_param_types = "";

if (!empty($search_query)) {
    switch ($search_filter) {
        case 'title':
            $search_conditions[] = "b.title LIKE ?";
            $search_params[] = "%$search_query%";
            $search_param_types .= "s";
            break;
        case 'author':
            $search_conditions[] = "b.author_name LIKE ?";
            $search_params[] = "%$search_query%";
            $search_param_types .= "s";
            break;
        case 'category':
            $search_conditions[] = "b.category LIKE ?";
            $search_params[] = "%$search_query%";
            $search_param_types .= "s";
            break;
        default:
            $search_conditions[] = "(b.title LIKE ? OR b.author_name LIKE ? OR b.category LIKE ?)";
            $search_params[] = "%$search_query%";
            $search_params[] = "%$search_query%";
            $search_params[] = "%$search_query%";
            $search_param_types .= "sss";
            break;
    }
}

$search_where_clause = !empty($search_conditions) ? "WHERE " . implode(" AND ", $search_conditions) : "";

//search function
$books_query = "SELECT b.book_id, b.title, b.author_name, b.category, b.year, b.total_stock,
                       (SELECT COUNT(*) FROM issued_books ib WHERE ib.book_id = b.book_id AND ib.return_date IS NULL) as issued_count,
                       (SELECT COUNT(*) FROM wishlist w WHERE w.book_id = b.book_id AND w.member_id = ?) as in_wishlist,
                       (SELECT COUNT(*) FROM borrow_requests br WHERE br.book_id = b.book_id AND br.member_id = ? AND br.status = 'Pending') as pending_request
                FROM book_db b 
                $search_where_clause
                ORDER BY b.title";

$all_search_params = array_merge([$member_id, $member_id], $search_params);
$all_param_types = "ii" . $search_param_types;

$books_stmt = $conn->prepare($books_query);
$books_stmt->bind_param($all_param_types, ...$all_search_params);
$books_stmt->execute();
$books_result = $books_stmt->get_result();

// issued books
$issued_query = "SELECT ib.issue_id, ib.issue_date, ib.due_date, ib.return_date, ib.status,
                        b.book_id, b.title, b.author_name, b.category,
                        CASE 
                            WHEN ib.return_date IS NULL AND ib.due_date < CURDATE() THEN DATEDIFF(CURDATE(), ib.due_date)
                            ELSE 0
                        END as days_overdue,
                        f.fine_amount, f.status as fine_status
                 FROM issued_books ib
                 JOIN book_db b ON ib.book_id = b.book_id
                 LEFT JOIN fines f ON ib.issue_id = f.issue_id
                 WHERE ib.member_id = ?
                 ORDER BY ib.issue_date DESC";

$issued_stmt = $conn->prepare($issued_query);
$issued_stmt->bind_param("i", $member_id);
$issued_stmt->execute();
$issued_result = $issued_stmt->get_result();

// wishlist
$wishlist_query = "SELECT w.wishlist_id, w.added_date,
                          b.book_id, b.title, b.author_name, b.category, b.total_stock,
                          (SELECT COUNT(*) FROM issued_books ib WHERE ib.book_id = b.book_id AND ib.return_date IS NULL) as issued_count
                   FROM wishlist w
                   JOIN book_db b ON w.book_id = b.book_id
                   WHERE w.member_id = ?
                   ORDER BY w.added_date DESC";

$wishlist_stmt = $conn->prepare($wishlist_query);
$wishlist_stmt->bind_param("i", $member_id);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();

// Get statistics
$total_issued = $conn->prepare("SELECT COUNT(*) as count FROM issued_books WHERE member_id = ?");
$total_issued->bind_param("i", $member_id);
$total_issued->execute();
$total_issued_count = $total_issued->get_result()->fetch_assoc()['count'];

$currently_issued = $conn->prepare("SELECT COUNT(*) as count FROM issued_books WHERE member_id = ? AND return_date IS NULL");
$currently_issued->bind_param("i", $member_id);
$currently_issued->execute();
$currently_issued_count = $currently_issued->get_result()->fetch_assoc()['count'];

$overdue_books = $conn->prepare("SELECT COUNT(*) as count FROM issued_books WHERE member_id = ? AND return_date IS NULL AND due_date < CURDATE()");
$overdue_books->bind_param("i", $member_id);
$overdue_books->execute();
$overdue_count = $overdue_books->get_result()->fetch_assoc()['count'];

$pending_fines = $conn->prepare("SELECT COALESCE(SUM(f.fine_amount), 0) as total FROM fines f JOIN issued_books ib ON f.issue_id = ib.issue_id WHERE ib.member_id = ? AND f.status = 'Pending'");
$pending_fines->bind_param("i", $member_id);
$pending_fines->execute();
$pending_fines_amount = $pending_fines->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Library System</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="header">
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']); ?>! 👋</h1>
            <p>Explore our library</p>
        </div>
        <div class="header-actions">
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($wishlist_message)): ?>
            <div class="success-message">
                <?php echo $wishlist_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card issued">
                <h3><?php echo $total_issued_count; ?></h3>
                <p>📚 Total Books Borrowed</p>
            </div>
            <div class="stat-card current">
                <h3><?php echo $currently_issued_count; ?></h3>
                <p>📖 Currently Reading</p>
            </div>
            <div class="stat-card overdue">
                <h3><?php echo $overdue_count; ?></h3>
                <p>⏰ Overdue Books</p>
            </div>
            <div class="stat-card fines">
                <h3>₹<?php echo number_format($pending_fines_amount, 2); ?></h3>
                <p>💰 Pending Fines</p>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <h2>🔍 Search Books</h2>
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search for books by title, author, or category..." value="<?php echo htmlspecialchars($search_query); ?>">
                <select name="filter">
                    <option value="all" <?php echo $search_filter === 'all' ? 'selected' : ''; ?>>All Fields</option>
                    <option value="title" <?php echo $search_filter === 'title' ? 'selected' : ''; ?>>Title</option>
                    <option value="author" <?php echo $search_filter === 'author' ? 'selected' : ''; ?>>Author</option>
                    <option value="category" <?php echo $search_filter === 'category' ? 'selected' : ''; ?>>Category</option>
                </select>
                <button type="submit" class="search-btn">Search</button>
                <a href="user_dashboard.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Books Section -->
        <div class="section">
            <h2>📚 <?php echo !empty($search_query) ? 'Search Results for "' . htmlspecialchars($search_query) . '"' : 'Available Books'; ?></h2>
            <?php if ($books_result && $books_result->num_rows > 0): ?>
                <div class="books-grid">
                    <?php while ($book = $books_result->fetch_assoc()): ?>
                        <?php 
                        $available_copies = $book['total_stock'] - $book['issued_count'];
                        $availability_class = $available_copies > 2 ? 'available' : ($available_copies > 0 ? 'limited' : 'unavailable');
                        $availability_text = $available_copies > 0 ? "Available ($available_copies copies)" : "Not Available";
                        ?>
                        <div class="book-card">
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="book-author">by <?php echo htmlspecialchars($book['author_name']); ?></div>
                            <div class="book-category"><?php echo htmlspecialchars($book['category']); ?></div>
                            <div class="book-details">
                                <?php if ($book['year']): ?>
                                    Published: <?php echo $book['year']; ?><br>
                                <?php endif; ?>
                            </div>
                            <div class="book-availability">
                                <span class="<?php echo $availability_class; ?>"><?php echo $availability_text; ?></span>
                            </div>
                            <div class="book-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                    <button type="submit" name="toggle_wishlist" class="btn <?php echo $book['in_wishlist'] ? 'btn-danger' : 'btn-primary'; ?>">
                                        <?php echo $book['in_wishlist'] ? '💔 Remove from Wishlist' : '❤️ Add to Wishlist'; ?>
                                    </button>
                                </form>
                            </div> 
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <?php if (!empty($search_query)): ?>
                        No books found matching your search criteria. Try different keywords or filters.
                    <?php else: ?>
                        📚 No books available at the moment.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Currently Issued Books -->
        <div class="section">
            <h2>📖 Your Current Books</h2>
            <?php if ($issued_result->num_rows > 0): ?>
                <table>
                    <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Fine</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($issued = $issued_result->fetch_assoc()): 
                        $is_overdue = ($issued['return_date'] === null && $issued['days_overdue'] > 0);
                        $row_class = $is_overdue ? 'overdue-row' : '';
                    ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><strong><?php echo htmlspecialchars($issued['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($issued['author_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($issued['issue_date'])); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($issued['due_date'])); ?></td>
                            <td>
                                <?php if ($issued['return_date']): ?>
                                    <span class="status-returned">Returned</span>
                                    <br><small>on <?php echo date('Y-m-d', strtotime($issued['return_date'])); ?></small>
                                <?php elseif ($is_overdue): ?>
                                    <span class="status-issued">Overdue</span>
                                    <br><small><?php echo $issued['days_overdue']; ?> days late</small>
                                <?php else: ?>
                                    <span class="status-issued">Issued</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($issued['fine_amount']): ?>
                                    <span style="color: #e74c3c; font-weight: bold;">
                                        ₹<?php echo number_format($issued['fine_amount'], 2); ?>
                                    </span>
                                    <br><small>(<?php echo $issued['fine_status']; ?>)</small>
                                <?php else: ?>
                                    <span style="color: #27ae60;">No Fine</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    📚 You haven't borrowed any books yet. <a href="#search">Search for books</a> to get started!
                </div>
            <?php endif; ?>
        </div>

        <!-- Wishlist -->
        <div class="section">
            <h2>❤️ Your Wishlist</h2>
            <?php if ($wishlist_result->num_rows > 0): ?>
                <div class="books-grid">
                    <?php while ($wishlist_book = $wishlist_result->fetch_assoc()): ?>
                        <?php 
                        $available_copies = $wishlist_book['total_stock'] - $wishlist_book['issued_count'];
                        $availability_class = $available_copies > 2 ? 'available' : ($available_copies > 0 ? 'limited' : 'unavailable');
                        $availability_text = $available_copies > 0 ? "Available ($available_copies copies)" : "Not Available";
                        ?>
                        <div class="book-card">
                            <div class="book-title"><?php echo htmlspecialchars($wishlist_book['title']); ?></div>
                            <div class="book-author">by <?php echo htmlspecialchars($wishlist_book['author_name']); ?></div>
                            <div class="book-category"><?php echo htmlspecialchars($wishlist_book['category']); ?></div>
                            <div class="book-details">
                                Added to wishlist: <?php echo date('Y-m-d', strtotime($wishlist_book['added_date'])); ?>
                            </div>
                            <div class="book-availability">
                                <span class="<?php echo $availability_class; ?>"><?php echo $availability_text; ?></span>
                            </div>
                            <div class="book-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $wishlist_book['book_id']; ?>">
                                    <button type="submit" name="toggle_wishlist" class="btn btn-danger">
                                        💔 Remove from Wishlist
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    💔 Your wishlist is empty. <a href="#search">Search for books</a> and add them to your wishlist!
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>

