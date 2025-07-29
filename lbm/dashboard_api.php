<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['mob_no'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'configure.php';

try {
    // Get real-time statistics
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
    $result = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE due_date < CURDATE() AND return_date IS NULL");
    $stats['overdue_books'] = $result->fetch_assoc()['count'];
    
    // Pending fines
    $result = $conn->query("SELECT COUNT(*) as count FROM fines WHERE status = 'Pending'");
    $stats['pending_fines'] = $result->fetch_assoc()['count'];
    
    // Total fine amount
    $result = $conn->query("SELECT SUM(fine_amount) as total FROM fines WHERE status = 'Pending'");
    $stats['total_fine_amount'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Today's returns
    $result = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date = CURDATE()");
    $stats['today_returns'] = $result->fetch_assoc()['count'];
    
    // Recent activities
    $recent_issues = $conn->query("SELECT ib.issue_id, ib.issue_date, b.title, m.first_name, m.last_name 
                                   FROM issued_books ib 
                                   JOIN book_db b ON ib.book_id = b.book_id 
                                   JOIN member_db m ON ib.member_id = m.member_id 
                                   ORDER BY ib.issue_date DESC LIMIT 5");
    
    $recent_activities = [];
    while($issue = $recent_issues->fetch_assoc()) {
        $recent_activities[] = [
            'type' => 'issue',
            'message' => $issue['first_name'] . ' ' . $issue['last_name'] . ' borrowed ' . $issue['title'],
            'date' => $issue['issue_date'],
            'icon' => 'hand-holding'
        ];
    }
    
    $recent_returns = $conn->query("SELECT ib.return_date, b.title, m.first_name, m.last_name 
                                   FROM issued_books ib 
                                   JOIN book_db b ON ib.book_id = b.book_id 
                                   JOIN member_db m ON ib.member_id = m.member_id 
                                   WHERE ib.return_date IS NOT NULL 
                                   ORDER BY ib.return_date DESC LIMIT 5");
    
    while($return = $recent_returns->fetch_assoc()) {
        $recent_activities[] = [
            'type' => 'return',
            'message' => $return['first_name'] . ' ' . $return['last_name'] . ' returned ' . $return['title'],
            'date' => $return['return_date'],
            'icon' => 'undo'
        ];
    }
    
    // Sort activities by date
    usort($recent_activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    $stats['recent_activities'] = array_slice($recent_activities, 0, 10);
    
    // Category distribution
    $category_stats = $conn->query("SELECT category, COUNT(*) as count FROM book_db GROUP BY category LIMIT 10");
    $categories = [];
    while($cat = $category_stats->fetch_assoc()) {
        $categories[] = [
            'label' => $cat['category'],
            'value' => $cat['count']
        ];
    }
    $stats['categories'] = $categories;
    
    // Monthly trends
    $monthly_trends = $conn->query("SELECT DATE_FORMAT(issue_date, '%Y-%m') as month, COUNT(*) as count 
                                   FROM issued_books 
                                   WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                                   GROUP BY month ORDER BY month");
    $trends = [];
    while($trend = $monthly_trends->fetch_assoc()) {
        $trends[] = [
            'month' => $trend['month'],
            'count' => $trend['count']
        ];
    }
    $stats['trends'] = $trends;
    
    // Notifications
    $notifications = [];
    
    // Check for overdue books
    if ($stats['overdue_books'] > 0) {
        $notifications[] = [
            'type' => 'warning',
            'message' => $stats['overdue_books'] . ' books are overdue',
            'icon' => 'exclamation-triangle'
        ];
    }
    
    // Check for pending fines
    if ($stats['pending_fines'] > 0) {
        $notifications[] = [
            'type' => 'info',
            'message' => '₹' . number_format($stats['total_fine_amount'], 2) . ' in pending fines',
            'icon' => 'money-bill-wave'
        ];
    }
    
    // Check for today's returns
    if ($stats['today_returns'] > 0) {
        $notifications[] = [
            'type' => 'success',
            'message' => $stats['today_returns'] . ' books returned today',
            'icon' => 'check-circle'
        ];
    }
    
    $stats['notifications'] = $notifications;
    $stats['timestamp'] = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>