<?php
include 'configure.php'; // DB connection

$q = $_GET['q'] ?? '';
$q = trim($q);

if ($q === '') {
    echo "<p>No search query provided.</p>";
    exit;
}

$stmt = $conn->prepare("SELECT title, author_name, publisher, year FROM book_db WHERE title LIKE CONCAT('%', ?, '%') LIMIT 10");
$stmt->bind_param("s", $q);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p>No books found for '<strong>" . htmlspecialchars($q) . "</strong>'.</p>";
    exit;
}

echo "<ul style='list-style:none;padding-left:0;'>";
while ($row = $result->fetch_assoc()) {
    echo "<li style='margin-bottom:10px;'>
        <strong>" . htmlspecialchars($row['title']) . "</strong> by " . htmlspecialchars($row['author_name']) . "<br>
        <small>Publisher: " . htmlspecialchars($row['publisher']) . ", Year: " . htmlspecialchars($row['year']) . "</small>
    </li>";
}
echo "</ul>";
?>