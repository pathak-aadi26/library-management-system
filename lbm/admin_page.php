<?php

session_start();
if (!isset($_SESSION['mob_no'])) {
     
    header("Location: login.php");
    exit();
}

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
      <a href="#">Dashboard</a>
      <a href="manage_member.php">Manage Member</a>
      <a href="manage_book.php">Manage Books</a>
      <a href="manage_issue.php">Issued Details</a>
      <a href="#">Borrow Requests</a>
              <a href="fine_details.php">Fine Details</a>
              <a href="manage_returns.php">Returns</a>
      <a href="#">More</a>
    </nav>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
  </aside>

  <main class="main-content">
    <h1>Welcome, <span id="adminName"><?php echo htmlspecialchars($_SESSION['name']); ?></span></h1>
    <p>Your dashboard</p>

    <section class="cards">
      <div class="card">
        <h2>1200</h2>
        <p>Books Available</p>
      </div>
      <div class="card">
        <h2>350</h2>
        <p>Registered Users</p>
      </div>
      <div class="card">
        <h2>45</h2>
        <p>Pending Borrow Requests</p>
      </div>
      <div class="card">
        <h2>23</h2>
        <p>Books Due Today</p>
      </div>
    </section>
  </main>
</body>
</html>
