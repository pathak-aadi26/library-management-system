<?php
$conn = new mysqli('localhost', 'root', '', 'member_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
    <title>Issued Details</title>
    <link rel="stylesheet" href="admin.css">
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

<div class="main-content">
    <h1>Issue Details</h1>
    <div class="table-container">
    <table>
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
  </div>
</main>
</body>
</html>