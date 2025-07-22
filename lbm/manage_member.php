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

$members = $conn->query("SELECT member_id, first_name, last_name, address, mob_no, email_id, role FROM member_db");

// Add member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $fname = $_POST['first_name'];
    $lname = $_POST['last_name'];
    $address = $_POST['address'];
    $mob_no = $_POST['mob_no'];
    $email = $_POST['email_id'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO member_db (first_name, last_name, address, mob_no, email_id, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $fname, $lname, $address, $mob_no, $email, $password, $role);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_member.php");
    exit;
}


// Handle Edit Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_member'])) {
    $id = intval($_POST['member_id']);
    $fname = $_POST['first_name'];
    $lname = $_POST['last_name'];
    $address = $_POST['address'];
    $mob_no = $_POST['mob_no'];
    $email = $_POST['email_id'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE member_db SET first_name=?, last_name=?, address=?, mob_no=?, email_id=?, role=? WHERE member_id=?");
    $stmt->bind_param("ssssssi", $fname, $lname, $address, $mob_no, $email, $role, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_member.php");
    exit;
}
// For editing
$editData = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editResult = $conn->query("SELECT * FROM member_db WHERE member_id = $editId LIMIT 1");
    $editData = $editResult->fetch_assoc();
}

// Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM member_db WHERE member_id = $id");
    header("Location: manage_member.php");
    exit;
}
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
        <a href="fine_details.php">Fine Details</a>
        <a href="#">Returns</a>
        <a href="#">More</a>
        
    </nav>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
</aside>

<main class="main-content">
    <h1>Manage Members</h1>

    <h2><?= $editData ? "Edit Member" : "Add New Member" ?></h2>
    <form method="POST" style="margin-bottom: 30px;">
        <input type="hidden" name="member_id" value="<?= $editData['member_id'] ?? '' ?>">
        <input type="text" name="first_name" placeholder="First Name" required value="<?= $editData['first_name'] ?? '' ?>">
        <input type="text" name="last_name" placeholder="Last Name" required value="<?= $editData['last_name'] ?? '' ?>">
        <input type="text" name="address" placeholder="Address" required value="<?= $editData['address'] ?? '' ?>">
        <input type="text" name="mob_no" placeholder="Mobile No" required value="<?= $editData['mob_no'] ?? '' ?>">
        <input type="email" name="email_id" placeholder="Email" required value="<?= $editData['email_id'] ?? '' ?>">
        <?php if (!$editData): ?>
            <input type="password" name="password" placeholder="Password" required>
        <?php endif; ?>
        <select name="role" required>
            <option value="admin" <?= ($editData['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="user" <?= ($editData['role'] ?? '') == 'user' ? 'selected' : '' ?>>User</option>
        </select>
        <button type="submit" name="<?= $editData ? 'edit_member' : 'add_member' ?>">
            <?= $editData ? "Update Member" : "Add Member" ?>
        </button>
    </form>

    <h2>All Members</h2>
    <table border="1" cellpadding="10" cellspacing="0">
        <thead>
        <tr>
            <th>ID</th><th>First</th><th>Last</th><th>Address</th><th>Mobile</th><th>Email</th><th>Role</th><th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $members->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['member_id']) ?></td>
                <td><?= htmlspecialchars($row['first_name']) ?></td>
                <td><?= htmlspecialchars($row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['address']) ?></td>
                <td><?= htmlspecialchars($row['mob_no']) ?></td>
                <td><?= htmlspecialchars($row['email_id']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td>
                    <a href="manage_member.php?edit=<?= $row['member_id'] ?>">Edit</a> |
                    <a href="manage_member.php?delete=<?= $row['member_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</main>
</body>
</html>