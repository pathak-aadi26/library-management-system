<?php
$conn = new mysqli('localhost', 'root', '', 'member_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission for issuing a book
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_book'])) {
    $member_id = $_POST['member_id'];
    $book_id = $_POST['book_id'];
    $issue_date = date('Y-m-d'); // today
    $due_date = date('Y-m-d', strtotime('+14 days')); // due after 2 weeks

    // Check if the book has stock available
    $stock_check = $conn->prepare("SELECT total_stock FROM book_db WHERE book_id = ?");
    $stock_check->bind_param("i", $book_id);
    $stock_check->execute();
    $stock_check->bind_result($total_stock);
    $stock_check->fetch();
    $stock_check->close();

    // Count how many copies of this book are currently issued and not returned
    $issued_check = $conn->prepare("SELECT COUNT(*) FROM issued_books WHERE book_id = ? AND return_date IS NULL");
    $issued_check->bind_param("i", $book_id);
    $issued_check->execute();
    $issued_check->bind_result($issued_count);
    $issued_check->fetch();
    $issued_check->close();

    if ($issued_count < $total_stock) {
        // Insert issue record
        $stmt = $conn->prepare("INSERT INTO issued_books (member_id, book_id, issue_date, due_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $member_id, $book_id, $issue_date, $due_date);

        if ($stmt->execute()) {
            $message = "Book issued successfully!";
        } else {
            $message = "Error issuing book.";
        }
        $stmt->close();
    } else {
        $message = "No stock available for this book.";
    }
}

// Fetch members for dropdown
$members_result = $conn->query("SELECT member_id, first_name, last_name FROM member_db ORDER BY first_name");

// Fetch books for dropdown
$books_result = $conn->query("SELECT book_id, title FROM book_db ORDER BY title");

// Fetch issued records
$sql = "SELECT ib.issue_id, 
       CONCAT(m.first_name, ' ', m.last_name) AS member_name,
       b.title AS book_title,
       ib.issue_date, ib.due_date, ib.return_date
     FROM issued_books ib
       JOIN member_db m ON ib.member_id = m.member_id
       JOIN book_db b ON ib.book_id = b.book_id
      ORDER BY ib.issue_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Members</title>
    <link rel="stylesheet" href="admin.css">
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
        <a href="#">Fine Details</a>
        <a href="#">Returns</a>
        <a href="#">More</a>
        
    </nav>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
</aside>

<div class="main-content">
  <h1>Issue a Book</h1>

  <?php if (!empty($message)): ?>
  <div class="message"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form class="issue-form" method="POST" action="">
    <select name="member_id" required>
      <option value="">Select Member</option>
      <?php while ($member = $members_result->fetch_assoc()): ?>
        <option value="<?= $member['member_id'] ?>">
          <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <select name="book_id" required>
      <option value="">Select Book</option>
      <?php while ($book = $books_result->fetch_assoc()): ?>
        <option value="<?= $book['book_id'] ?>">
          <?= htmlspecialchars($book['title']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <button type="submit" name="issue_book">Issue Book</button>
  </form>

    <h2>Issue Details</h2>
    <table border="1" cellpadding="10" cellspacing="0">
    <thead>
      <tr>
        <th>Issue ID</th><th>Member Name</th><th>Book Title</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $row['issue_id'] ?></td>
            <td><?= htmlspecialchars($row['member_name']) ?></td>
            <td><?= htmlspecialchars($row['book_title']) ?></td>
            <td><?= $row['issue_date'] ?></td>
            <td><?= $row['due_date'] ?></td>
            <td><?= $row['return_date'] ?? '—' ?></td>
            <td><?= $row['return_date'] ? 'Returned' : 'Not Returned' ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7">No issued records found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>
</body>
</html>