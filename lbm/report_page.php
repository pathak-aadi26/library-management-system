<?php
session_start();
if (!isset($_SESSION['mob_no'])) {
    header('Location: login.php');
    exit;
}

require_once 'configure.php';

// +++ TIMEFRAME FILTER LOGIC +++
$timeframe = $_GET['tf'] ?? 'daily';
switch ($timeframe) {
  case 'weekly':
    $startDate = date('Y-m-d', strtotime('-7 days'));
    break;
  case 'monthly':
    $startDate = date('Y-m-d', strtotime('-30 days'));
    break;
  default:
    $timeframe = 'daily';
    $startDate = date('Y-m-d');
}

// Summary counts based on timeframe
$borrowSummary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM issued_books WHERE issue_date >= '$startDate'"));
$fineSummary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(fine_amount),0) AS total FROM fines WHERE status='Paid' AND payment_date >= '$startDate'"));

// Query to fetch all issued books
$allIssuedQuery = "SELECT ib.issue_id, b.title, CONCAT(m.first_name,' ',m.last_name) AS member_name, ib.issue_date, ib.due_date, ib.return_date
                   FROM issued_books ib
                   JOIN book_db b ON ib.book_id = b.book_id
                   JOIN member_db m ON ib.member_id = m.member_id
                   ORDER BY ib.issue_date DESC";
$allIssuedResult = mysqli_query($conn, $allIssuedQuery);
// +++ END TIMEFRAME +++

// ----------------------
// 1. Top Borrowed Books
// ----------------------
$topBooksQuery = "SELECT b.book_id, b.title, b.author_name, COUNT(*) AS borrow_count
                  FROM issued_books ib
                  JOIN book_db b ON ib.book_id = b.book_id
                  GROUP BY b.book_id, b.title, b.author_name
                  ORDER BY borrow_count DESC
                  LIMIT 10";
$topBooksResult = mysqli_query($conn, $topBooksQuery);

// ----------------------
// 2. Members with Most Borrows
// ----------------------
$activeMembersQuery = "SELECT m.member_id, CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                             COUNT(*) AS borrow_count
                       FROM issued_books ib
                       JOIN member_db m ON ib.member_id = m.member_id
                       GROUP BY m.member_id, member_name
                       ORDER BY borrow_count DESC
                       LIMIT 10";
$activeMembersResult = mysqli_query($conn, $activeMembersQuery);

// ----------------------
// 3. Currently Overdue Books
// ----------------------
$overdueQuery = "SELECT ib.issue_id, b.title, CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                        ib.due_date
                 FROM issued_books ib
                 JOIN book_db b ON ib.book_id = b.book_id
                 JOIN member_db m ON ib.member_id = m.member_id
                 WHERE ib.due_date < CURDATE() AND ib.return_date IS NULL
                 ORDER BY ib.due_date ASC";
$overdueResult = mysqli_query($conn, $overdueQuery);

// ----------------------
// 4. Low-Stock Books (less than 3)
// ----------------------
$lowStockQuery = "SELECT book_id, title, total_stock FROM book_db WHERE total_stock < 3 ORDER BY total_stock ASC";
$lowStockResult = mysqli_query($conn, $lowStockQuery);

// +++ NEW REPORT QUERIES +++
// 5. Monthly Borrowing Trend (last 12 months)
$monthlyBorrowQuery = "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS month, COUNT(*) AS borrow_count
                       FROM issued_books
                       GROUP BY month
                       ORDER BY month DESC
                       LIMIT 12";
$monthlyBorrowResult = mysqli_query($conn, $monthlyBorrowQuery);

// 6. Category Popularity
$categoryPopularQuery = "SELECT b.category, COUNT(*) AS borrow_count
                          FROM issued_books ib
                          JOIN book_db b ON ib.book_id = b.book_id
                          GROUP BY b.category
                          ORDER BY borrow_count DESC";
$categoryPopularResult = mysqli_query($conn, $categoryPopularQuery);

// 7. Fine Collection Summary (last 12 months)
$fineCollectionQuery = "SELECT DATE_FORMAT(payment_date,'%Y-%m') AS month, SUM(fine_amount) AS total_collected
                        FROM fines WHERE status='Paid'
                        GROUP BY month
                        ORDER BY month DESC
                        LIMIT 12";
$fineCollectionResult = mysqli_query($conn, $fineCollectionQuery);

// 8. Top Authors
$topAuthorsQuery = "SELECT b.author_name, COUNT(*) AS borrow_count
                    FROM issued_books ib
                    JOIN book_db b ON ib.book_id = b.book_id
                    GROUP BY b.author_name
                    ORDER BY borrow_count DESC
                    LIMIT 10";
$topAuthorsResult = mysqli_query($conn, $topAuthorsQuery);
// +++ END NEW QUERIES +++
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reports - Library Admin</title>
  <link rel="stylesheet" href="admin.css" />
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
      <a href="fine_details.php">Fine Details</a>
      <a href="report_page.php">Reports</a>
      <a href="#">Returns</a>
      <a href="#">More</a>
    </nav>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
  </aside>

  <main class="main-content">
    <h1>Reports</h1>

    <!-- Timeframe Filter & Summary -->
    <section class="report-section">
      <form method="get" class="timeframe-form">
        <label for="tf">Timeframe: </label>
        <select name="tf" id="tf" onchange="this.form.submit()">
          <option value="daily" <?= $timeframe=='daily'?'selected':'' ?>>Daily</option>
          <option value="weekly" <?= $timeframe=='weekly'?'selected':'' ?>>Weekly</option>
          <option value="monthly" <?= $timeframe=='monthly'?'selected':'' ?>>Monthly</option>
        </select>
      </form>

      <div class="summary-cards">
        <div class="card"><h2><?= $borrowSummary['cnt'] ?></h2><p>Total Borrows (<?= ucfirst($timeframe) ?>)</p></div>
        <div class="card"><h2><?= number_format($fineSummary['total'],2) ?></h2><p>Fines Collected (₹)</p></div>
      </div>

      <!-- Report selector -->
      <label for="reportSelector" style="margin-top:20px; display:inline-block;">Select Report:</label>
      <select id="reportSelector" onchange="showReport()" style="padding:6px 10px; margin-left:10px;">
        <option value="topBooks">Top Borrowed Books</option>
        <option value="activeMembers">Active Members</option>
        <option value="overdue">Overdue Books</option>
        <option value="lowStock">Low Stock Books</option>
        <option value="monthlyTrend">Monthly Borrowing Trend</option>
        <option value="categoryPopularity">Category Popularity</option>
        <option value="fineSummary">Fine Collection Summary</option>
        <option value="topAuthors">Top Authors</option>
        <option value="allIssued">All Issued Books</option>
      </select>
    </section>

    <!-- Top Borrowed Books -->
    <section class="report-section rp" id="topBooks">
      <h2>Top Borrowed Books</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Rank</th><th>Title</th><th>Author</th><th>Times Borrowed</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 1; if (mysqli_num_rows($topBooksResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($topBooksResult)): ?>
              <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['author_name']) ?></td>
                <td><?= $row['borrow_count'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
              <tr><td colspan="4">No data found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Active Members -->
    <section class="report-section rp" id="activeMembers" style="display:none;">
      <h2>Members With Most Borrows</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Rank</th><th>Member Name</th><th>Times Borrowed</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 1; if (mysqli_num_rows($activeMembersResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($activeMembersResult)): ?>
              <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($row['member_name']) ?></td>
                <td><?= $row['borrow_count'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
              <tr><td colspan="3">No data found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Overdue Books -->
    <section class="report-section rp" id="overdue" style="display:none;">
      <h2>Currently Overdue Books</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Issue ID</th><th>Book Title</th><th>Borrower</th><th>Due Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($overdueResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($overdueResult)): ?>
              <tr>
                <td><?= $row['issue_id'] ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['member_name']) ?></td>
                <td><?= $row['due_date'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
              <tr><td colspan="4">No overdue books at the moment.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Low Stock Books -->
    <section class="report-section rp" id="lowStock" style="display:none;">
      <h2>Low Stock Books (Less than 3)</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Book ID</th><th>Title</th><th>Stock Left</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($lowStockResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($lowStockResult)): ?>
              <tr>
                <td><?= $row['book_id'] ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= $row['total_stock'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="3">All books have sufficient stock.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Monthly Borrowing Trend -->
    <section class="report-section rp" id="monthlyTrend" style="display:none;">
      <h2>Monthly Borrowing Trend (Last 12 Months)</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Month</th><th>Total Borrows</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($monthlyBorrowResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($monthlyBorrowResult)): ?>
              <tr>
                <td><?= $row['month'] ?></td>
                <td><?= $row['borrow_count'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="2">No borrowing data available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Category Popularity -->
    <section class="report-section rp" id="categoryPopularity" style="display:none;">
      <h2>Category Popularity</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Category</th><th>Total Borrows</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($categoryPopularResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($categoryPopularResult)): ?>
              <tr>
                <td><?= htmlspecialchars($row['category'] ?: 'Uncategorized') ?></td>
                <td><?= $row['borrow_count'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="2">No data available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Fine Collection Summary -->
    <section class="report-section rp" id="fineSummary" style="display:none;">
      <h2>Fine Collection Summary (Last 12 Months)</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Month</th><th>Total Collected (₹)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($fineCollectionResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($fineCollectionResult)): ?>
              <tr>
                <td><?= $row['month'] ?></td>
                <td><?= number_format($row['total_collected'], 2) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="2">No fine collection data.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Top Authors -->
    <section class="report-section rp" id="topAuthors" style="display:none;">
      <h2>Top Authors by Borrow Count</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Rank</th><th>Author</th><th>Total Borrows</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 1; if (mysqli_num_rows($topAuthorsResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($topAuthorsResult)): ?>
              <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($row['author_name']) ?></td>
                <td><?= $row['borrow_count'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="3">No data available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- All Issued Books -->
    <section class="report-section rp" id="allIssued" style="display:none;">
      <h2>All Issued Books</h2>
      <table border="1" cellpadding="10" cellspacing="0">
        <thead>
          <tr>
            <th>Issue ID</th><th>Book Title</th><th>Member Name</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($allIssuedResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($allIssuedResult)): ?>
              <tr>
                <td><?= $row['issue_id'] ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['member_name']) ?></td>
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
    </section>

    <!-- END NEW REPORTS -->

  </main>
  <script>
    function showReport(){
      const v=document.getElementById('reportSelector').value;
      document.querySelectorAll('.rp').forEach(s=>{
        s.style.display = (s.id===v)?'block':'none';
      });
    }
    // initialise on load
    document.addEventListener('DOMContentLoaded',showReport);
  </script>
</body>
</html>