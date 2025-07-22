<?php
$host = "localhost";     // or 127.0.0.1
$username = "root";      // default for XAMPP/WAMP
$password = "";          // leave empty if no password
$database = "member_db"; // your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_id'])) {
    $issue_id = $_POST['issue_id'];
    $return_date = date('Y-m-d');

    // Get due date
    $query = "SELECT due_date FROM issued_books WHERE issue_id = $issue_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $due_date = $row['due_date'];

    // Fine calculation
    $days_late = (strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24);
    $days_late = ($days_late > 0) ? $days_late : 0;
    $fine = $days_late * 5;

    // Update return date in issued_books
    $update = "UPDATE issued_books SET return_date = '$return_date', status = 'Returned' WHERE issue_id = $issue_id";
    mysqli_query($conn, $update);

    // Insert fine only if there's a delay
    if ($fine > 0) {
        $insert_fine = "INSERT INTO fines (issue_id, fine_amount, days_late) VALUES ($issue_id, $fine, $days_late)";
        mysqli_query($conn, $insert_fine);
    }

    echo "<div style='padding: 20px; background: #e0ffe0;'>Book returned successfully. Fine: ₹" . number_format($fine, 2) . "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Return Book</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Return a Book</h2>
    <form method="post" action="">
        <div class="mb-3">
            <label for="issue_id" class="form-label">Select Issued Book</label>
            <select name="issue_id" id="issue_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php
                // Fetch issued books not yet returned
                $sql = "SELECT i.issue_id, m.first_name, m.last_name, b.title 
                        FROM issued_books i
                        JOIN members m ON i.member_id = m.member_id
                        JOIN books b ON i.book_id = b.book_id
                        WHERE i.return_date IS NULL";

                $res = mysqli_query($conn, $sql);
                while ($row = mysqli_fetch_assoc($res)) {
                    echo "<option value='{$row['issue_id']}'>#{$row['issue_id']} - {$row['first_name']} {$row['last_name']} - {$row['title']}</option>";
                }
                ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Return Book</button>
    </form>
</div>
</body>
</html>