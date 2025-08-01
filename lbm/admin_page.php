<?php

session_start();
if (!isset($_SESSION['mob_no'])) {
     
    header("Location: login.php");
    exit();
}
require_once 'configure.php';

// Books statistics
$totalBooks = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM book_db"))['cnt'] ?? 0;
// Registered non-admin users
$totalUsers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM member_db WHERE role='user'"))['cnt'] ?? 0;
// Books currently issued
$pendingIssues = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM issued_books WHERE return_date IS NULL"))['cnt'] ?? 0;
// Books whose due date is today
$dueToday = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM issued_books WHERE due_date = CURDATE() AND return_date IS NULL"))['cnt'] ?? 0;

// Latest 5 book issues
$recentIssues = mysqli_query(
    $conn,
    "SELECT ib.issue_id,
            CONCAT(m.first_name, ' ', m.last_name) AS member_name,
            b.title AS book_title,
            ib.issue_date,
            ib.due_date
     FROM issued_books ib
     JOIN member_db m ON ib.member_id = m.member_id
     JOIN book_db b ON ib.book_id = b.book_id
     ORDER BY ib.issue_date DESC
     LIMIT 5"
 );
 // +++ END NEW SECTION +++

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="admin.css" />
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
    <h1>Welcome, <span id="adminName"><?php echo htmlspecialchars($_SESSION['name']); ?></span></h1>
    <p>Your dashboard</p>

    <section class="cards">
      <div class="card">
        <h2><?= $totalBooks ?></h2>
        <p>Books Available</p>
      </div>
      <div class="card">
        <h2><?= $totalUsers ?></h2>
        <p>Registered Users</p>
      </div>
      <div class="card">
        <h2><?= $pendingIssues ?></h2>
        <p>Book Currently Issued</p>
      </div>
      <div class="card">
        <h2><?= $dueToday ?></h2>
        <p>Books Due Today</p>
      </div>
    </section>

    <!-- Recent Activity Section -->
   <section class="recent">
   <h2>Recent Book Issues</h2>
   <table border="1" cellpadding="10" cellspacing="0">
     <thead>
       <tr>
         <th>Issue ID</th>
         <th>Member Name</th>
         <th>Book Title</th>
         <th>Issue Date</th>
         <th>Due Date</th>
       </tr>
     </thead>
     <tbody>
       <?php while ($row = mysqli_fetch_assoc($recentIssues)): ?>
         <tr>
           <td><?= $row['issue_id'] ?></td>
           <td><?= htmlspecialchars($row['member_name']) ?></td>
           <td><?= htmlspecialchars($row['book_title']) ?></td>
           <td><?= $row['issue_date'] ?></td>
           <td><?= $row['due_date'] ?></td>
         </tr>
       <?php endwhile; ?>
     </tbody>
   </table>
 </section>

  </main>
</body>
</html>
