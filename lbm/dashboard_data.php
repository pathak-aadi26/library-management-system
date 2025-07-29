<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['mob_no'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "member_db";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$action = $_GET['action'] ?? '';

switch($action) {
    case 'stats':
        getStatistics($conn);
        break;
    case 'activities':
        getRecentActivities($conn);
        break;
    case 'notifications':
        getNotifications($conn);
        break;
    case 'chart_data':
        getChartData($conn);
        break;
    default:
        getAllDashboardData($conn);
        break;
}

function getStatistics($conn) {
    $stats = [];
    
    // Total books
    $total_books_query = $conn->query("SELECT SUM(total_stock) as total_books FROM book_db");
    $stats['total_books'] = $total_books_query->fetch_assoc()['total_books'] ?? 0;
    
    // Available books
    $available_books_query = $conn->query("
        SELECT SUM(b.total_stock) - COUNT(ib.book_id) as available_books 
        FROM book_db b 
        LEFT JOIN issued_books ib ON b.book_id = ib.book_id AND ib.return_date IS NULL
    ");
    $stats['available_books'] = $available_books_query->fetch_assoc()['available_books'] ?? 0;
    
    // Total members
    $total_members_query = $conn->query("SELECT COUNT(*) as total_members FROM member_db WHERE role = 'user'");
    $stats['total_members'] = $total_members_query->fetch_assoc()['total_members'] ?? 0;
    
    // Issued books
    $issued_books_query = $conn->query("SELECT COUNT(*) as issued_books FROM issued_books WHERE return_date IS NULL");
    $stats['issued_books'] = $issued_books_query->fetch_assoc()['issued_books'] ?? 0;
    
    // Overdue books
    $overdue_books_query = $conn->query("SELECT COUNT(*) as overdue_books FROM issued_books WHERE return_date IS NULL AND due_date < CURDATE()");
    $stats['overdue_books'] = $overdue_books_query->fetch_assoc()['overdue_books'] ?? 0;
    
    // Books due today
    $due_today_query = $conn->query("SELECT COUNT(*) as due_today FROM issued_books WHERE return_date IS NULL AND due_date = CURDATE()");
    $stats['due_today'] = $due_today_query->fetch_assoc()['due_today'] ?? 0;
    
    // Pending fines
    $pending_fines_query = $conn->query("SELECT COUNT(*) as pending_fines FROM fines WHERE status = 'Pending'");
    $stats['pending_fines'] = $pending_fines_query->fetch_assoc()['pending_fines'] ?? 0;
    
    // Total fine amount
    $total_fine_amount_query = $conn->query("SELECT SUM(fine_amount) as total_fine_amount FROM fines WHERE status = 'Pending'");
    $stats['total_fine_amount'] = $total_fine_amount_query->fetch_assoc()['total_fine_amount'] ?? 0;
    
    // Utilization rate
    $stats['utilization_rate'] = $stats['total_books'] > 0 ? round(($stats['issued_books'] / $stats['total_books']) * 100, 1) : 0;
    
    echo json_encode($stats);
}

function getRecentActivities($conn) {
    $activities = [];
    
    // Recent book issues
    $recent_issues_query = $conn->query("
        SELECT m.first_name, m.last_name, b.title, ib.issue_date, ib.due_date,
               'issue' as activity_type
        FROM issued_books ib 
        JOIN member_db m ON ib.member_id = m.member_id 
        JOIN book_db b ON ib.book_id = b.book_id 
        WHERE ib.return_date IS NULL 
        ORDER BY ib.issue_date DESC 
        LIMIT 10
    ");
    
    while ($row = $recent_issues_query->fetch_assoc()) {
        $activities[] = [
            'type' => 'issue',
            'icon' => 'fas fa-book',
            'message' => $row['first_name'] . ' ' . $row['last_name'] . ' borrowed "' . $row['title'] . '"',
            'time' => $row['issue_date'],
            'due_date' => $row['due_date']
        ];
    }
    
    // Recent returns (if you have a returns table or track return_date)
    $recent_returns_query = $conn->query("
        SELECT m.first_name, m.last_name, b.title, ib.return_date,
               'return' as activity_type
        FROM issued_books ib 
        JOIN member_db m ON ib.member_id = m.member_id 
        JOIN book_db b ON ib.book_id = b.book_id 
        WHERE ib.return_date IS NOT NULL 
        ORDER BY ib.return_date DESC 
        LIMIT 5
    ");
    
    while ($row = $recent_returns_query->fetch_assoc()) {
        $activities[] = [
            'type' => 'return',
            'icon' => 'fas fa-undo',
            'message' => $row['first_name'] . ' ' . $row['last_name'] . ' returned "' . $row['title'] . '"',
            'time' => $row['return_date'],
            'due_date' => null
        ];
    }
    
    // Sort activities by time (most recent first)
    usort($activities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    echo json_encode(array_slice($activities, 0, 10));
}

function getNotifications($conn) {
    $notifications = [];
    
    // Overdue books
    $overdue_query = $conn->query("
        SELECT COUNT(*) as count FROM issued_books 
        WHERE return_date IS NULL AND due_date < CURDATE()
    ");
    $overdue_count = $overdue_query->fetch_assoc()['count'];
    
    if ($overdue_count > 0) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fas fa-exclamation-triangle',
            'message' => "$overdue_count book(s) are overdue and need immediate attention",
            'priority' => 'high'
        ];
    }
    
    // Books due today
    $due_today_query = $conn->query("
        SELECT COUNT(*) as count FROM issued_books 
        WHERE return_date IS NULL AND due_date = CURDATE()
    ");
    $due_today_count = $due_today_query->fetch_assoc()['count'];
    
    if ($due_today_count > 0) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-calendar-day',
            'message' => "$due_today_count book(s) are due today",
            'priority' => 'medium'
        ];
    }
    
    // Low stock books
    $low_stock_query = $conn->query("
        SELECT COUNT(*) as count FROM book_db 
        WHERE total_stock <= 2
    ");
    $low_stock_count = $low_stock_query->fetch_assoc()['count'];
    
    if ($low_stock_count > 0) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fas fa-boxes',
            'message' => "$low_stock_count book(s) have low stock (≤2 copies)",
            'priority' => 'medium'
        ];
    }
    
    // Pending fines
    $pending_fines_query = $conn->query("
        SELECT COUNT(*) as count, SUM(fine_amount) as total_amount 
        FROM fines WHERE status = 'Pending'
    ");
    $fine_data = $pending_fines_query->fetch_assoc();
    
    if ($fine_data['count'] > 0) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-dollar-sign',
            'message' => $fine_data['count'] . " pending fine(s) totaling $" . number_format($fine_data['total_amount'], 2),
            'priority' => 'low'
        ];
    }
    
    echo json_encode($notifications);
}

function getChartData($conn) {
    $chart_data = [];
    
    // Book status distribution
    $available_books_query = $conn->query("
        SELECT SUM(b.total_stock) - COUNT(ib.book_id) as available_books 
        FROM book_db b 
        LEFT JOIN issued_books ib ON b.book_id = ib.book_id AND ib.return_date IS NULL
    ");
    $available_books = $available_books_query->fetch_assoc()['available_books'] ?? 0;
    
    $issued_books_query = $conn->query("SELECT COUNT(*) as issued_books FROM issued_books WHERE return_date IS NULL");
    $issued_books = $issued_books_query->fetch_assoc()['issued_books'] ?? 0;
    
    $overdue_books_query = $conn->query("SELECT COUNT(*) as overdue_books FROM issued_books WHERE return_date IS NULL AND due_date < CURDATE()");
    $overdue_books = $overdue_books_query->fetch_assoc()['overdue_books'] ?? 0;
    
    $chart_data['book_status'] = [
        'labels' => ['Available Books', 'Issued Books', 'Overdue Books'],
        'data' => [$available_books, $issued_books - $overdue_books, $overdue_books],
        'colors' => ['#10b981', '#3b82f6', '#ef4444']
    ];
    
    // Monthly issue trends (last 6 months)
    $monthly_trends = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        $monthly_query = $conn->query("
            SELECT COUNT(*) as count 
            FROM issued_books 
            WHERE DATE_FORMAT(issue_date, '%Y-%m') = '$month'
        ");
        $count = $monthly_query->fetch_assoc()['count'] ?? 0;
        
        $monthly_trends['labels'][] = $month_name;
        $monthly_trends['data'][] = $count;
    }
    
    $chart_data['monthly_trends'] = $monthly_trends;
    
    echo json_encode($chart_data);
}

function getAllDashboardData($conn) {
    $data = [];
    
    ob_start();
    getStatistics($conn);
    $data['stats'] = json_decode(ob_get_clean(), true);
    
    ob_start();
    getRecentActivities($conn);
    $data['activities'] = json_decode(ob_get_clean(), true);
    
    ob_start();
    getNotifications($conn);
    $data['notifications'] = json_decode(ob_get_clean(), true);
    
    ob_start();
    getChartData($conn);
    $data['chart_data'] = json_decode(ob_get_clean(), true);
    
    echo json_encode($data);
}

$conn->close();
?>