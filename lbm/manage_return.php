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

// Handle manual return processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $issue_id = intval($_POST['issue_id']);
    $return_date = date('Y-m-d');

    // Get due date
    $query = "SELECT due_date FROM issued_books WHERE issue_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $due_date = $row['due_date'];

    // Fine calculation
    $days_late = (strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24);
    $days_late = ($days_late > 0) ? $days_late : 0;
    $fine = $days_late * 5;

    // Update return date in issued_books
    $update = $conn->prepare("UPDATE issued_books SET return_date = ?, status = 'Returned' WHERE issue_id = ?");
    $update->bind_param("si", $return_date, $issue_id);
    $update->execute();

    // Insert fine only if there's a delay
    if ($fine > 0) {
        $insert_fine = $conn->prepare("INSERT INTO fines (issue_id, fine_amount, days_late, status, fine_date) VALUES (?, ?, ?, 'Pending', NOW())");
        $insert_fine->bind_param("idi", $issue_id, $fine, $days_late);
        $insert_fine->execute();
    }

    $success_message = "Book returned successfully. Fine: ₹" . number_format($fine, 2);
}

// Handle delete issue record
if (isset($_GET['delete'])) {
    $issue_id = intval($_GET['delete']);
    
    // Delete related fines first
    $conn->query("DELETE FROM fines WHERE issue_id = $issue_id");
    
    // Delete issue record
    $conn->query("DELETE FROM issued_books WHERE issue_id = $issue_id");
    
    header("Location: manage_returns.php");
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$member_filter = $_GET['member'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = "";

if ($status_filter != 'all') {
    if ($status_filter === 'pending') {
        $where_conditions[] = "ib.return_date IS NULL";
    } 
    else {
        $where_conditions[] = "ib.return_date IS NOT NULL";
    }
}

if (!empty($member_filter)) {
    $where_conditions[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
    $search_term = "%$member_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

if (!empty($date_from)) {
    $where_conditions[] = "ib.issue_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "ib.issue_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query to get all issued books with return details
$query = "SELECT ib.issue_id, ib.issue_date, ib.due_date, ib.return_date, 
                 m.member_id, m.first_name, m.last_name, m.mob_no, m.email_id,
                 b.book_id, b.title, b.author_name, b.category,
                 CASE 
                    WHEN ib.return_date IS NULL AND ib.due_date < CURDATE() THEN DATEDIFF(CURDATE(), ib.due_date)
                    WHEN ib.return_date IS NOT NULL AND ib.return_date > ib.due_date THEN DATEDIFF(ib.return_date, ib.due_date)
                    ELSE 0
                 END as days_overdue,
                 CASE 
                    WHEN ib.return_date IS NULL AND ib.due_date < CURDATE() THEN DATEDIFF(CURDATE(), ib.due_date) * 5
                    WHEN ib.return_date IS NOT NULL AND ib.return_date > ib.due_date THEN DATEDIFF(ib.return_date, ib.due_date) * 5
                    ELSE 0
                 END as calculated_fine,
                 f.fine_amount, f.status as fine_status
          FROM issued_books ib
          JOIN member_db m ON ib.member_id = m.member_id
          JOIN book_db b ON ib.book_id = b.book_id
          LEFT JOIN fines f ON ib.issue_id = f.issue_id
          $where_clause
          ORDER BY ib.issue_date DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $returns_result = $stmt->get_result();
} else {
    $returns_result = $conn->query($query);
}

// Get statistics
$total_issues = $conn->query("SELECT COUNT(*) as count FROM issued_books")->fetch_assoc()['count'];
$pending_returns = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL")->fetch_assoc()['count'];
$completed_returns = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NOT NULL")->fetch_assoc()['count'];
$overdue_books = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL AND due_date < CURDATE()")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Returns Management - Library Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="return.css">
    
</head>
<body>
<aside class="sidebar">
    <h2>Library Admin</h2>
    <nav>
        <a href="admin_page.php">Dashboard</a>
        <a href="manage_member.php">Manage Member</a>
        <a href="manage_book.php">Manage Books</a>
        <a href="wishlist_data.php">Book Issue</a>
        <!-- <a href="manage_borrow.php">Borrow Requests</a> -->
        <a href="manage_fine.php">Fine Details</a>
        <a href="manage_issue.php">Issued Details</a>
        <a href="manage_return.php">Returns</a>
        <a href="report_page.php">Reports</a>
    </nav>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
</aside>

<main class="main-content">
    <h1>Returns Management</h1>
    <p>Track and manage book returns and overdue items</p>

    <?php if (isset($success_message)): ?>
        <div class="success-message">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3><?php echo $total_issues; ?></h3>
            <p>Total Issues</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $pending_returns; ?></h3>
            <p>Pending Returns</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $completed_returns; ?></h3>
            <p>Completed Returns</p>
        </div>
        <div class="stat-card" style="background: linear-gradient(45deg, #ffebee, #f8d7da);">
            <h3><?php echo $overdue_books; ?></h3>
            <p>Overdue Books</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-row">
            <label for="status">Filter by Status:</label>
            <select name="status" id="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Returns</option>
                <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
            </select>
            
            <label for="member">Search Member:</label>
            <input type="text" name="member" id="member" placeholder="Enter member name" value="<?php echo htmlspecialchars($member_filter); ?>">
            
            <label for="date_from">From Date:</label>
            <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            
            <label for="date_to">To Date:</label>
            <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            
            <button type="submit">Filter</button>
            <a href="manage_returns.php" style="margin-left: 10px; text-decoration: none; background: #95a5a6; color: white; padding: 8px 15px; border-radius: 4px;">Clear</a>
        </form>
    </div>

    <!-- Returns Table -->
    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th>Issue ID</th>
                <th>Member</th>
                <th>Book Details</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
                <th>Days Overdue</th>
                <th>Fine</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($returns_result && $returns_result->num_rows > 0): ?>
                <?php while ($row = $returns_result->fetch_assoc()): ?>
                    <?php 
                    $is_overdue = ($row['return_date'] === null && $row['days_overdue'] > 0);
                    $row_class = $is_overdue ? 'overdue-row' : '';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo htmlspecialchars($row['issue_id']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($row['email_id']); ?></small><br>
                            <small><?php echo htmlspecialchars($row['mob_no']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                            <small>by <?php echo htmlspecialchars($row['author_name']); ?></small><br>
                            <small>Category: <?php echo htmlspecialchars($row['category']); ?></small>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($row['issue_date'])); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['due_date'])); ?></td>
                        <td><?php echo $row['return_date'] ? date('Y-m-d', strtotime($row['return_date'])) : '-'; ?></td>
                        <td>
                            <?php if ($row['return_date']): ?>
                                <span class="status-returned">Returned</span>
                            <?php elseif ($is_overdue): ?>
                                <span class="status-overdue">Overdue</span>
                            <?php else: ?>
                                <span class="status-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['days_overdue'] > 0): ?>
                                <span style="color: #e74c3c; font-weight: bold;">
                                    <?php echo $row['days_overdue']; ?> days
                                </span>
                            <?php else: ?>
                                <span style="color: #27ae60;">On time</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['fine_amount']): ?>
                                <span class="fine-amount">
                                    ₹<?php echo number_format($row['fine_amount'], 2); ?>
                                    <br><small>(<?php echo $row['fine_status']; ?>)</small>
                                </span>
                            <?php elseif ($row['calculated_fine'] > 0): ?>
                                <span class="fine-amount">
                                    ₹<?php echo number_format($row['calculated_fine'], 2); ?>
                                    <br><small>(Pending)</small>
                                </span>
                            <?php else: ?>
                                <span style="color: #27ae60;">No Fine</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['return_date'] === null): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="issue_id" value="<?php echo $row['issue_id']; ?>">
                                    <button type="submit" name="process_return" class="return-btn" onclick="return confirm('Process return for this book?')">
                                        Process Return
                                    </button>
                                </form>
                                <br><br>
                            <?php endif; ?>
                            <a href="manage_returns.php?delete=<?php echo $row['issue_id']; ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this record? This will also delete associated fines.')">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 30px; color: #7f8c8d;">
                        No return records found matching the selected criteria.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>

