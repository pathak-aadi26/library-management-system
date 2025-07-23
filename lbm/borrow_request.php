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

// Get current member ID
$member_mob = $_SESSION['mob_no'];
$member_query = $conn->prepare("SELECT member_id, first_name, last_name, role FROM member_db WHERE mob_no = ?");
$member_query->bind_param("s", $member_mob);
$member_query->execute();
$member_result = $member_query->get_result();
$member_data = $member_result->fetch_assoc();
$member_id = $member_data['member_id'] ?? 0;

// Allow both users and admins to submit borrow requests

// Handle new borrow request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $book_id = intval($_POST['book_id']);
    $priority = $_POST['priority'] ?? 'Normal';
    $requested_due_date = $_POST['requested_due_date'] ?? null;
    
    // Check if user already has a pending request for this book
    $existing_check = $conn->prepare("SELECT COUNT(*) as count FROM borrow_requests WHERE member_id = ? AND book_id = ? AND status = 'Pending'");
    $existing_check->bind_param("ii", $member_id, $book_id);
    $existing_check->execute();
    $existing_count = $existing_check->get_result()->fetch_assoc()['count'];
    
    if ($existing_count > 0) {
        $error_message = "You already have a pending request for this book.";
    } else {
        // Check if book is available
        $book_check = $conn->prepare("SELECT b.title, b.total_stock, (SELECT COUNT(*) FROM issued_books ib WHERE ib.book_id = b.book_id AND ib.return_date IS NULL) as issued_count FROM books b WHERE b.book_id = ?");
        $book_check->bind_param("i", $book_id);
        $book_check->execute();
        $book_data = $book_check->get_result()->fetch_assoc();
        
        if ($book_data) {
            // Insert the request
            $request_stmt = $conn->prepare("INSERT INTO borrow_requests (member_id, book_id, priority, requested_due_date) VALUES (?, ?, ?, ?)");
            $request_stmt->bind_param("iiss", $member_id, $book_id, $priority, $requested_due_date);
            
            if ($request_stmt->execute()) {
                $success_message = "Your borrow request has been submitted successfully! Request ID: " . $conn->insert_id;
            } else {
                $error_message = "Error submitting request. Please try again.";
            }
        } else {
            $error_message = "Selected book not found.";
        }
    }
}

// Handle cancel request
if (isset($_GET['cancel'])) {
    $request_id = intval($_GET['cancel']);
    $cancel_stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Cancelled' WHERE request_id = ? AND member_id = ? AND status = 'Pending'");
    $cancel_stmt->bind_param("ii", $request_id, $member_id);
    $cancel_stmt->execute();
    header("Location: borrow_request.php");
    exit;
}

// Get available books
$books_query = "SELECT b.book_id, b.title, b.author, b.category, b.total_stock,
                       (SELECT COUNT(*) FROM issued_books ib WHERE ib.book_id = b.book_id AND ib.return_date IS NULL) as issued_count,
                       (SELECT COUNT(*) FROM borrow_requests br WHERE br.book_id = b.book_id AND br.status = 'Pending') as pending_requests
                FROM books b 
                ORDER BY b.title";
$books_result = $conn->query($books_query);

// Get user's requests
$user_requests_query = "SELECT br.request_id, br.request_date, br.status, br.priority, br.admin_notes, br.processed_date, br.requested_due_date,
                               b.title, b.author, b.category,
                               admin.first_name as admin_first_name, admin.last_name as admin_last_name
                        FROM borrow_requests br
                        JOIN books b ON br.book_id = b.book_id
                        LEFT JOIN member_db admin ON br.processed_by = admin.member_id
                        WHERE br.member_id = ?
                        ORDER BY br.request_date DESC";
$user_requests_stmt = $conn->prepare($user_requests_query);
$user_requests_stmt->bind_param("i", $member_id);
$user_requests_stmt->execute();
$user_requests_result = $user_requests_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Borrow Request - Library System</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .welcome-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .request-form {
            background: rgba(255, 255, 255, 0.9);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .submit-btn {
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .submit-btn:hover {
            background: #219a52;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .book-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .book-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
        }
        
        .book-card.selected {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }
        
        .book-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .book-author {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .book-category {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .book-availability {
            font-size: 14px;
            margin-top: 10px;
        }
        
        .available {
            color: #27ae60;
            font-weight: bold;
        }
        
        .limited {
            color: #f39c12;
            font-weight: bold;
        }
        
        .unavailable {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .requests-table {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-cancelled {
            background-color: #f8f9fa;
            color: #6c757d;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .cancel-btn {
            background: #dc3545;
            color: white;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .cancel-btn:hover {
            background: #c82333;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body style="display: block;">
    <div style="position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 1000;">
        <?php if ($member_data['role'] === 'admin'): ?>
            <a href="admin_page.php" class="logout-btn" style="background: #9b59b6;">🔧 Admin Panel</a>
        <?php endif; ?>
        <a href="user_dashboard.php" class="logout-btn" style="background: #3498db;">📚 Dashboard</a>
        <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>
    
    <div class="main-content">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']); ?>!</h1>
            <p>Submit a request to borrow books from our library collection.</p>
        </div>

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

        <!-- Request Form -->
        <div class="request-form">
            <h2>Submit New Borrow Request</h2>
            <form method="POST" id="borrowForm">
                <div class="form-group">
                    <label for="book_id">Select a Book:</label>
                    <select name="book_id" id="book_id" required>
                        <option value="">-- Select a Book --</option>
                        <?php while ($book = $books_result->fetch_assoc()): ?>
                            <?php 
                            $available_copies = $book['total_stock'] - $book['issued_count'];
                            $status = $available_copies > 0 ? "Available ($available_copies copies)" : "Not Available";
                            ?>
                            <option value="<?php echo $book['book_id']; ?>" <?php echo $available_copies <= 0 ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($book['title']); ?> - <?php echo htmlspecialchars($book['author']); ?> (<?php echo $status; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="priority">Request Priority:</label>
                    <select name="priority" id="priority">
                        <option value="Normal">Normal</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="requested_due_date">Preferred Due Date (Optional):</label>
                    <input type="date" name="requested_due_date" id="requested_due_date" min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>

                <button type="submit" name="submit_request" class="submit-btn">Submit Request</button>
            </form>
        </div>

        <!-- Available Books Grid -->
        <h2>Available Books</h2>
        <div class="books-grid">
            <?php 
            // Reset the result pointer
            $books_result->data_seek(0);
            while ($book = $books_result->fetch_assoc()): 
                $available_copies = $book['total_stock'] - $book['issued_count'];
                $availability_class = $available_copies > 2 ? 'available' : ($available_copies > 0 ? 'limited' : 'unavailable');
                $availability_text = $available_copies > 0 ? "Available ($available_copies copies)" : "Not Available";
                
                if ($book['pending_requests'] > 0) {
                    $availability_text .= " ({$book['pending_requests']} pending requests)";
                }
            ?>
            <div class="book-card" onclick="selectBook(<?php echo $book['book_id']; ?>)" data-book-id="<?php echo $book['book_id']; ?>">
                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                <div class="book-category"><?php echo htmlspecialchars($book['category']); ?></div>
                <div class="book-availability">
                    <span class="<?php echo $availability_class; ?>"><?php echo $availability_text; ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- User's Requests -->
        <h2>Your Borrow Requests</h2>
        <div class="requests-table">
            <table>
                <thead>
                <tr style="background: #34495e; color: white;">
                    <th style="padding: 12px;">Request ID</th>
                    <th style="padding: 12px;">Book</th>
                    <th style="padding: 12px;">Request Date</th>
                    <th style="padding: 12px;">Priority</th>
                    <th style="padding: 12px;">Status</th>
                    <th style="padding: 12px;">Requested Due</th>
                    <th style="padding: 12px;">Admin Notes</th>
                    <th style="padding: 12px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($user_requests_result && $user_requests_result->num_rows > 0): ?>
                    <?php while ($request = $user_requests_result->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #ecf0f1;">
                            <td style="padding: 10px;"><?php echo $request['request_id']; ?></td>
                            <td style="padding: 10px;">
                                <strong><?php echo htmlspecialchars($request['title']); ?></strong><br>
                                <small>by <?php echo htmlspecialchars($request['author']); ?></small>
                            </td>
                            <td style="padding: 10px;"><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                            <td style="padding: 10px;"><?php echo $request['priority']; ?></td>
                            <td style="padding: 10px;">
                                <span class="status-<?php echo strtolower($request['status']); ?>">
                                    <?php echo $request['status']; ?>
                                </span>
                                <?php if ($request['processed_date']): ?>
                                    <br><small>on <?php echo date('Y-m-d', strtotime($request['processed_date'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;"><?php echo $request['requested_due_date'] ? date('Y-m-d', strtotime($request['requested_due_date'])) : '-'; ?></td>
                            <td style="padding: 10px;">
                                <?php if ($request['admin_notes']): ?>
                                    <small><?php echo htmlspecialchars($request['admin_notes']); ?></small>
                                    <?php if ($request['admin_first_name']): ?>
                                        <br><small>by <?php echo htmlspecialchars($request['admin_first_name']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small style="color: #999;">-</small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ($request['status'] === 'Pending'): ?>
                                    <a href="borrow_request.php?cancel=<?php echo $request['request_id']; ?>" 
                                       class="cancel-btn" 
                                       onclick="return confirm('Are you sure you want to cancel this request?')">
                                        Cancel
                                    </a>
                                <?php else: ?>
                                    <small style="color: #999;">-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #7f8c8d;">
                            No borrow requests found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function selectBook(bookId) {
            // Remove previous selection
            document.querySelectorAll('.book-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            document.querySelector(`[data-book-id="${bookId}"]`).classList.add('selected');
            
            // Update the select dropdown
            document.getElementById('book_id').value = bookId;
        }

        // Auto-set minimum date for due date field
        document.addEventListener('DOMContentLoaded', function() {
            const dueDateField = document.getElementById('requested_due_date');
            const today = new Date();
            const minDate = new Date(today.getTime() + (7 * 24 * 60 * 60 * 1000)); // 7 days from now
            dueDateField.min = minDate.toISOString().split('T')[0];
        });
    </script>
</body>
</html>