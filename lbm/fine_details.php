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

// Handle fine payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fine'])) {
    $fine_id = intval($_POST['fine_id']);
    $stmt = $conn->prepare("UPDATE fines SET status = 'Paid', payment_date = NOW() WHERE fine_id = ?");
    $stmt->bind_param("i", $fine_id);
    $stmt->execute();
    $stmt->close();
    header("Location: fine_details.php");
    exit;
}

// Handle fine deletion
if (isset($_GET['delete'])) {
    $fine_id = intval($_GET['delete']);
    $conn->query("DELETE FROM fines WHERE fine_id = $fine_id");
    header("Location: fine_details.php");
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$member_filter = $_GET['member'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = "";

if ($status_filter !== 'all') {
    $where_conditions[] = "f.status = ?";
    $params[] = $status_filter;
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

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query to get all fines with member and book information
$query = "SELECT f.fine_id, f.issue_id, f.fine_amount, f.days_late, f.status, f.fine_date, f.payment_date,
                 m.member_id, m.first_name, m.last_name, m.mob_no, m.email_id,
                 b.book_id, b.title, b.author,
                 ib.issue_date, ib.due_date, ib.return_date
          FROM fines f
          JOIN issued_books ib ON f.issue_id = ib.issue_id
          JOIN member_db m ON ib.member_id = m.member_id
          JOIN books b ON ib.book_id = b.book_id
          $where_clause
          ORDER BY f.fine_date DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $fines_result = $stmt->get_result();
} else {
    $fines_result = $conn->query($query);
}

// Get statistics
$total_fines = $conn->query("SELECT COUNT(*) as count FROM fines")->fetch_assoc()['count'];
$pending_fines = $conn->query("SELECT COUNT(*) as count FROM fines WHERE status = 'Pending'")->fetch_assoc()['count'];
$paid_fines = $conn->query("SELECT COUNT(*) as count FROM fines WHERE status = 'Paid'")->fetch_assoc()['count'];
$total_amount = $conn->query("SELECT SUM(fine_amount) as total FROM fines WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fine Details - Library Admin</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #2c3e50;
        }
        
        .stat-card p {
            margin: 0;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .fine-amount {
            font-weight: bold;
            color: #e74c3c;
        }
        
        .pay-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .pay-btn:hover {
            background: #219a52;
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .delete-btn:hover {
            background: #c0392b;
        }
        
        .table-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #34495e;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <h2>Library Admin</h2>
    <nav>
        <a href="admin_page.php">Dashboard</a>
        <a href="manage_member.php">Manage Member</a>
        <a href="manage_book.php">Manage Books</a>
        <a href="manage_issue.php">Issued Details</a>
        <a href="#">Borrow Requests</a>
        <a href="fine_details.php" style="background: #8ba3f1;">Fine Details</a>
        <a href="manage_returns.php">Returns</a>
        <a href="#">More</a>
    </nav>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
</aside>

<main class="main-content">
    <h1>Fine Details</h1>
    <p>Manage and track library fines</p>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3><?php echo $total_fines; ?></h3>
            <p>Total Fines</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $pending_fines; ?></h3>
            <p>Pending Fines</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $paid_fines; ?></h3>
            <p>Paid Fines</p>
        </div>
        <div class="stat-card">
            <h3>₹<?php echo number_format($total_amount, 2); ?></h3>
            <p>Outstanding Amount</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-row">
            <label for="status">Filter by Status:</label>
            <select name="status" id="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
            
            <label for="member">Search Member:</label>
            <input type="text" name="member" id="member" placeholder="Enter member name" value="<?php echo htmlspecialchars($member_filter); ?>">
            
            <button type="submit">Filter</button>
            <a href="fine_details.php" style="margin-left: 10px; text-decoration: none; background: #95a5a6; color: white; padding: 8px 15px; border-radius: 4px;">Clear</a>
        </form>
    </div>

    <!-- Fines Table -->
    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th>Fine ID</th>
                <th>Member</th>
                <th>Book Title</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Days Late</th>
                <th>Fine Amount</th>
                <th>Status</th>
                <th>Fine Date</th>
                <th>Payment Date</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($fines_result && $fines_result->num_rows > 0): ?>
                <?php while ($row = $fines_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['fine_id']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($row['email_id']); ?></small><br>
                            <small><?php echo htmlspecialchars($row['mob_no']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                            <small>by <?php echo htmlspecialchars($row['author']); ?></small>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($row['issue_date'])); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['due_date'])); ?></td>
                        <td><?php echo $row['return_date'] ? date('Y-m-d', strtotime($row['return_date'])) : 'Not Returned'; ?></td>
                        <td><?php echo $row['days_late']; ?> days</td>
                        <td class="fine-amount">₹<?php echo number_format($row['fine_amount'], 2); ?></td>
                        <td>
                            <span class="status-<?php echo strtolower($row['status']); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($row['fine_date'])); ?></td>
                        <td><?php echo $row['payment_date'] ? date('Y-m-d', strtotime($row['payment_date'])) : '-'; ?></td>
                        <td>
                            <?php if ($row['status'] === 'Pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="fine_id" value="<?php echo $row['fine_id']; ?>">
                                    <button type="submit" name="pay_fine" class="pay-btn" onclick="return confirm('Mark this fine as paid?')">
                                        Mark Paid
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="fine_details.php?delete=<?php echo $row['fine_id']; ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this fine record?')">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 30px; color: #7f8c8d;">
                        No fine records found matching the selected criteria.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>