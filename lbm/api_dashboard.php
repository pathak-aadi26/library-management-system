<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "member_db";
$conn = mysqli_connect($host, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get request type
$action = $_GET['action'] ?? 'stats';

try {
    switch ($action) {
        case 'stats':
            $response = getDashboardStats($conn);
            break;
        case 'recent_activities':
            $response = getRecentActivities($conn);
            break;
        case 'overdue_alerts':
            $response = getOverdueAlerts($conn);
            break;
        case 'popular_books':
            $response = getPopularBooks($conn);
            break;
        default:
            $response = ['error' => 'Invalid action'];
            http_response_code(400);
    }
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
    http_response_code(500);
}

$conn->close();
echo json_encode($response);

function getDashboardStats($conn) {
    $stats = [];
    
    // Total books
    $result = $conn->query("SELECT COUNT(*) as count FROM book_db");
    $stats['total_books'] = $result->fetch_assoc()['count'];
    
    // Total members
    $result = $conn->query("SELECT COUNT(*) as count FROM member_db");
    $stats['total_members'] = $result->fetch_assoc()['count'];
    
    // Issued books
    $result = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL");
    $stats['issued_books'] = $result->fetch_assoc()['count'];
    
    // Overdue books
    $result = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL AND due_date < CURDATE()");
    $stats['overdue_books'] = $result->fetch_assoc()['count'];
    
    // Total fines
    $result = $conn->query("SELECT SUM(fine_amount) as total FROM fines WHERE status = 'Pending'");
    $stats['total_fines'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Today's returns
    $result = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE due_date = CURDATE() AND return_date IS NULL");
    $stats['todays_returns'] = $result->fetch_assoc()['count'];
    
    // This month's issues
    $result = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE MONTH(issue_date) = MONTH(CURDATE()) AND YEAR(issue_date) = YEAR(CURDATE())");
    $stats['monthly_issues'] = $result->fetch_assoc()['count'];
    
    return [
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getRecentActivities($conn) {
    $activities = [];
    
    $query = "SELECT i.*, m.name as member_name, b.title as book_title 
              FROM issued_books i 
              JOIN member_db m ON i.member_id = m.member_id 
              JOIN book_db b ON i.book_id = b.book_id 
              ORDER BY i.issue_date DESC LIMIT 10";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'id' => $row['issue_id'],
            'member_name' => $row['member_name'],
            'book_title' => $row['book_title'],
            'issue_date' => $row['issue_date'],
            'due_date' => $row['due_date'],
            'return_date' => $row['return_date'],
            'type' => $row['return_date'] ? 'returned' : 'issued'
        ];
    }
    
    return [
        'success' => true,
        'data' => $activities,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getOverdueAlerts($conn) {
    $alerts = [];
    
    $query = "SELECT i.*, m.name as member_name, b.title as book_title, 
              DATEDIFF(CURDATE(), i.due_date) as days_overdue
              FROM issued_books i 
              JOIN member_db m ON i.member_id = m.member_id 
              JOIN book_db b ON i.book_id = b.book_id 
              WHERE i.return_date IS NULL AND i.due_date < CURDATE()
              ORDER BY i.due_date ASC LIMIT 10";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $alerts[] = [
            'id' => $row['issue_id'],
            'member_name' => $row['member_name'],
            'book_title' => $row['book_title'],
            'due_date' => $row['due_date'],
            'days_overdue' => $row['days_overdue'],
            'member_id' => $row['member_id'],
            'book_id' => $row['book_id']
        ];
    }
    
    return [
        'success' => true,
        'data' => $alerts,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getPopularBooks($conn) {
    $books = [];
    
    $query = "SELECT b.book_id, b.title, b.author_name, b.category,
              COUNT(i.issue_id) as issue_count,
              b.total_stock
              FROM book_db b 
              LEFT JOIN issued_books i ON b.book_id = i.book_id 
              GROUP BY b.book_id 
              ORDER BY issue_count DESC 
              LIMIT 10";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $books[] = [
            'id' => $row['book_id'],
            'title' => $row['title'],
            'author' => $row['author_name'],
            'category' => $row['category'],
            'issue_count' => $row['issue_count'],
            'total_stock' => $row['total_stock'],
            'availability' => $row['total_stock'] - $row['issue_count']
        ];
    }
    
    return [
        'success' => true,
        'data' => $books,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
?>