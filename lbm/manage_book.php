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

$book = $conn->query("SELECT book_id, title , category , publisher , year , edition , total_stock ,author_name FROM book_db");

// Add book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $publisher = $_POST['publisher'];
    $year = $_POST['year'];
    $edition = $_POST['edition'];
    $stock = $_POST['total_stock'];
    $author = $_POST['author_name'];

    $stmt = $conn->prepare("INSERT INTO book_db (title, category, publisher, year, edition, total_stock , author_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $title, $category, $publisher, $year, $edition, $stock, $author);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_book.php");
    exit;
}


// Handle Edit Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book'])) {
    $id = intval($_POST['book_id']);
    $title = $_POST['title'];
    $category = $_POST['category'];
    $publisher = $_POST['publisher'];
    $year = $_POST['year'];
    $edition = $_POST['edition'];
    $stock = $_POST['total_stock'];
    $author = $_POST['author_name'];

    $stmt = $conn->prepare("UPDATE book_db SET title=?, category=?, publisher=?, year=?, edition=?, total_stock=?, author_name=? WHERE book_id=?");
    $stmt->bind_param("sssssssi", $title, $category, $publisher, $year , $edition, $stock, $author, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_book.php");
    exit;
}
// For editing
$editData = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editResult = $conn->query("SELECT * FROM book_db WHERE book_id = $editId LIMIT 1");
    $editData = $editResult->fetch_assoc();
}

// Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM book_db WHERE book_id = $id");
    header("Location: manage_book.php");
    exit;
}
?>


<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Book</title>
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

<main class="main-content">
    <h1>Manage Book</h1>

    <h2><?= $editData ? "Edit Available Book" : "Add New Book" ?></h2>
    <form method="POST" style="margin-bottom: 30px;">
        <input type="hidden" name="book_id" value="<?= $editData['book_id'] ?? '' ?>">
        <input type="text" name="title" placeholder="Book Title" required value="<?= $editData['title'] ?? '' ?>">
        <input type="text" name="category" placeholder="Category" required value="<?= $editData['category'] ?? '' ?>">
         <input type="text" name="author_name" placeholder="Author Name" required value="<?= $editData['author_name'] ?? '' ?>">
        <input type="text" name="publisher" placeholder="Publisher" required value="<?= $editData['publisher'] ?? '' ?>">
        <input type="text" name="year" placeholder="Published Year" required value="<?= $editData['year'] ?? '' ?>">
        <input type="text" name="edition" placeholder="Edition" required value="<?= $editData['edition'] ?? '' ?>">
        <input type="text" name="total_stock" placeholder="Total Stock" required value="<?= $editData['total_stock'] ?? '' ?>">
        
        <button type="submit" name="<?= $editData ? 'edit_book' : 'add_book' ?>">
            <?= $editData ? "Update Book" : "Add Book" ?>
        </button>
    </form>

    <h2>All Book</h2>
    <table border="1" cellpadding="10" cellspacing="0">
        <thead>
        <tr>
            <th>Book Id</th><th>Book Title</th><th>Category</th><th>Author Name</th><th>Publisher</th><th>Published Year</th><th>Edition</th><th>Stock Available</th><th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $book->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['book_id']) ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['author_name']) ?></td>
                <td><?= htmlspecialchars($row['publisher']) ?></td>
                <td><?= htmlspecialchars($row['year']) ?></td>
                <td><?= htmlspecialchars($row['edition']) ?></td>
                <td><?= htmlspecialchars($row['total_stock']) ?></td>
                <td>
                    <a href="manage_book.php?edit=<?= $row['book_id'] ?>">Edit</a> |
                    <a href="manage_book.php?delete=<?= $row['book_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</main>
</body>
</html>