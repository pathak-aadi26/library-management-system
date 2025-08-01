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

// Get current admin ID
$admin_mob = $_SESSION['mob_no'];
$admin_query = $conn->prepare("SELECT member_id FROM member_db WHERE mob_no = ?");
$admin_query->bind_param("s", $admin_mob);
$admin_query->execute();
$admin_result = $admin_query->get_result();
$admin_data = $admin_result->fetch_assoc();
$admin_id = $admin_data['member_id'] ?? 0;

// Handle request approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $request_id = intval($_POST['request_id']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    $auto_issue = isset($_POST['auto_issue']);
    
    // Get request details
    $request_query = $conn->prepare("SELECT br.*, b.title, b.total_stock FROM borrow_requests br JOIN books b ON br.book_id = b.book_id WHERE request_id = ?");
    $request_query->bind_param("i", $request_id);
    $request_query->execute();
    $request_data = $request_query->get_result()->fetch_assoc();
    
    if ($request_data) {
        // Check stock availability
        $issued_count_query = $conn->prepare("SELECT COUNT(*) as count FROM issued_books WHERE book_id = ? AND return_date IS NULL");
        $issued_count_query->bind_param("i", $request_data['book_id']);
        $issued_count_query->execute();
        $issued_count = $issued_count_query->get_result()->fetch_assoc()['count'];
        
        if ($issued_count < $request_data['total_stock']) {
            // Approve the request
            $approve_stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Approved', admin_notes = ?, processed_by = ?, processed_date = NOW() WHERE request_id = ?");
            $approve_stmt->bind_param("sii", $admin_notes, $admin_id, $request_id);
            $approve_stmt->execute();
            
            // Auto-issue if requested
            if ($auto_issue) {
                $issue_date = date('Y-m-d');
                $due_date = $request_data['requested_due_date'] ?? date('Y-m-d', strtotime('+14 days'));
                
                $issue_stmt = $conn->prepare("INSERT INTO issued_books (member_id, book_id, issue_date, due_date) VALUES (?, ?, ?, ?)");
                $issue_stmt->bind_param("iiss", $request_data['member_id'], $request_data['book_id'], $issue_date, $due_date);
                $issue_stmt->execute();
                
                $success_message = "Request approved and book issued successfully!";
            } else {
                $success_message = "Request approved successfully!";
            }
        } else {
            $error_message = "Cannot approve: No stock available for this book.";
        }
    }
}

// Handle request rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $request_id = intval($_POST['request_id']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    $reject_stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Rejected', admin_notes = ?, processed_by = ?, processed_date = NOW() WHERE request_id = ?");
    $reject_stmt->bind_param("sii", $admin_notes, $admin_id, $request_id);
    $reject_stmt->execute();
    
    $success_message = "Request rejected successfully!";
}

// Handle delete request
if (isset($_GET['delete'])) {
    $request_id = intval($_GET['delete']);
    $conn->query("DELETE FROM borrow_requests WHERE request_id = $request_id");
    header("Location: manage_borrow_requests.php");
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$member_filter = $_GET['member'] ?? '';
$book_filter = $_GET['book'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = "";

if ($status_filter !== 'all') {
    $where_conditions[] = "br.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "br.priority = ?";
    $params[] = $priority_filter;
    $param_types .= "s";
}

if (!empty($member_filter)) {
    $where_conditions[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
    $search_term = "%$member_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

if (!empty($book_filter)) {
    $where_conditions[] = "b.title LIKE ?";
    $book_search_term = "%$book_filter%";
    $params[] = $book_search_term;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query to get all borrow requests
$query = "SELECT br.request_id, br.request_date, br.status, br.admin_notes, br.processed_date, br.priority, br.requested_due_date,
                 m.member_id, m.first_name, m.last_name, m.mob_no, m.email_id,
                 b.book_id, b.title, b.author_name, b.category, b.total_stock,
                 admin.first_name as admin_first_name, admin.last_name as admin_last_name,
                 (SELECT COUNT(*) FROM issued_books ib WHERE ib.book_id = b.book_id AND ib.return_date IS NULL) as issued_count
          FROM borrow_requests br
          JOIN member_db m ON br.member_id = m.member_id
          JOIN book_db b ON br.book_id = b.book_id
          LEFT JOIN member_db admin ON br.processed_by = admin.member_id
          $where_clause
          ORDER BY 
            CASE br.priority 
                WHEN 'Urgent' THEN 1 
                WHEN 'High' THEN 2 
                WHEN 'Normal' THEN 3 
            END,
            br.request_date DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $requests_result = $stmt->get_result();
} else {
    $requests_result = $conn->query($query);
}

// Get statistics
$total_requests = $conn->query("SELECT COUNT(*) as count FROM borrow_requests")->fetch_assoc()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE status = 'Pending'")->fetch_assoc()['count'];
$approved_requests = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE status = 'Approved'")->fetch_assoc()['count'];
$urgent_requests = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE priority = 'Urgent' AND status = 'Pending'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Borrow Requests - Library Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="borrow.css">
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
    <h1>Borrow Requests Management</h1>
    <p>Review and process member book borrow requests</p>

    <?php if (isset($success_message)): ?>
        <div class="success-message">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3><?php echo $total_requests; ?></h3>
            <p>Total Requests</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $pending_requests; ?></h3>
            <p>Pending Requests</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $approved_requests; ?></h3>
            <p>Approved Requests</p>
        </div>
        <div class="stat-card" style="background: linear-gradient(45deg, #ffebee, #fff5f5);">
            <h3><?php echo $urgent_requests; ?></h3>
            <p>Urgent Pending</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-row">
            <label for="status">Status:</label>
            <select name="status" id="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            
            <label for="priority">Priority:</label>
            <select name="priority" id="priority">
                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="Urgent" <?php echo $priority_filter === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                <option value="Normal" <?php echo $priority_filter === 'Normal' ? 'selected' : ''; ?>>Normal</option>
            </select>
            
            <label for="member">Member:</label>
            <input type="text" name="member" id="member" placeholder="Search member" value="<?php echo htmlspecialchars($member_filter); ?>">
            
            <label for="book">Book:</label>
            <input type="text" name="book" id="book" placeholder="Search book" value="<?php echo htmlspecialchars($book_filter); ?>">
            
            <button type="submit">Filter</button>
            <a href="manage_borrow_requests.php" style="margin-left: 10px; text-decoration: none; background: #95a5a6; color: white; padding: 8px 15px; border-radius: 4px;">Clear</a>
        </form>
    </div>

    <!-- Requests Table -->
    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th>Request ID</th>
                <th>Member</th>
                <th>Book Details</th>
                <th>Request Date</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Stock Info</th>
                <th>Requested Due</th>
                <th>Admin Notes</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                <?php while ($row = $requests_result->fetch_assoc()): ?>
                    <?php 
                    $is_urgent = $row['priority'] === 'Urgent';
                    $row_class = $is_urgent ? 'urgent-row' : '';
                    $stock_available = $row['issued_count'] < $row['total_stock'];
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo htmlspecialchars($row['request_id']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($row['email_id']); ?></small><br>
                            <small><?php echo htmlspecialchars($row['mob_no']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                            <small>by <?php echo htmlspecialchars($row['author']); ?></small><br>
                            <small>Category: <?php echo htmlspecialchars($row['category']); ?></small>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['request_date'])); ?></td>
                        <td>
                            <span class="priority-<?php echo strtolower($row['priority']); ?>">
                                <?php echo $row['priority']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-<?php echo strtolower($row['status']); ?>">
                                <?php echo $row['status']; ?>
                            </span>
                            <?php if ($row['processed_date']): ?>
                                <br><small>on <?php echo date('Y-m-d', strtotime($row['processed_date'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="stock-info">
                            Available: <span class="<?php echo $stock_available ? 'available' : 'unavailable'; ?>">
                                <?php echo ($row['total_stock'] - $row['issued_count']); ?>/<?php echo $row['total_stock']; ?>
                            </span>
                        </td>
                        <td><?php echo $row['requested_due_date'] ? date('Y-m-d', strtotime($row['requested_due_date'])) : '-'; ?></td>
                        <td>
                            <?php if ($row['admin_notes']): ?>
                                <small><?php echo htmlspecialchars($row['admin_notes']); ?></small><br>
                                <small>by <?php echo htmlspecialchars($row['admin_first_name'] . ' ' . $row['admin_last_name']); ?></small>
                            <?php else: ?>
                                <small style="color: #999;">No notes</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'Pending'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                    <input type="text" name="admin_notes" placeholder="Admin notes" class="notes-input"><br><br>
                                    <label style="font-size: 12px;">
                                        <input type="checkbox" name="auto_issue" <?php echo $stock_available ? '' : 'disabled'; ?>>
                                        Auto-issue book
                                    </label><br><br>
                                    <button type="submit" name="approve_request" class="approve-btn" <?php echo $stock_available ? '' : 'disabled'; ?>>
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                    <input type="text" name="admin_notes" placeholder="Rejection reason" class="notes-input"><br><br>
                                    <button type="submit" name="reject_request" class="reject-btn">
                                        Reject
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="manage_borrow_requests.php?delete=<?php echo $row['request_id']; ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this request?')">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 30px; color: #7f8c8d;">
                        No borrow requests found matching the selected criteria.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>