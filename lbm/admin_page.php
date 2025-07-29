<?php
session_start();
if (!isset($_SESSION['mob_no'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "member_db";
$conn = mysqli_connect($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch real-time statistics
$total_books = $conn->query("SELECT COUNT(*) as count FROM book_db")->fetch_assoc()['count'];
$total_members = $conn->query("SELECT COUNT(*) as count FROM member_db")->fetch_assoc()['count'];
$issued_books = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL")->fetch_assoc()['count'];
$overdue_books = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL AND due_date < CURDATE()")->fetch_assoc()['count'];
$total_fines = $conn->query("SELECT SUM(fine_amount) as total FROM fines WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;

// Recent activities
$recent_issues = $conn->query("SELECT i.*, m.name as member_name, b.title as book_title 
                               FROM issued_books i 
                               JOIN member_db m ON i.member_id = m.member_id 
                               JOIN book_db b ON i.book_id = b.book_id 
                               ORDER BY i.issue_date DESC LIMIT 5");

$popular_books = $conn->query("SELECT b.title, COUNT(i.issue_id) as issue_count 
                               FROM book_db b 
                               LEFT JOIN issued_books i ON b.book_id = i.book_id 
                               GROUP BY b.book_id 
                               ORDER BY issue_count DESC 
                               LIMIT 5");

$overdue_list = $conn->query("SELECT i.*, m.name as member_name, b.title as book_title, 
                              DATEDIFF(CURDATE(), i.due_date) as days_overdue
                              FROM issued_books i 
                              JOIN member_db m ON i.member_id = m.member_id 
                              JOIN book_db b ON i.book_id = b.book_id 
                              WHERE i.return_date IS NULL AND i.due_date < CURDATE()
                              ORDER BY i.due_date ASC LIMIT 5");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Library Management System</title>
    <link rel="stylesheet" href="admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="admin.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-reader"></i>
            <h2>Library Admin</h2>
        </div>
        <nav>
            <a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_member.php"><i class="fas fa-users"></i> Manage Member</a>
            <a href="manage_book.php"><i class="fas fa-book"></i> Manage Books</a>
            <a href="manage_issue.php"><i class="fas fa-exchange-alt"></i> Issued Details</a>
            <a href="#"><i class="fas fa-clock"></i> Borrow Requests</a>
            <a href="fine_details.php"><i class="fas fa-money-bill-wave"></i> Fine Details</a>
            <a href="#"><i class="fas fa-undo"></i> Returns</a>
            <a href="#"><i class="fas fa-cog"></i> Settings</a>
        </nav>
        <button class="logout-btn" onclick="location.href='logout.php'">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </aside>

    <main class="main-content">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Welcome back, <span class="admin-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span></h1>
                <p class="current-time" id="currentTime"></p>
            </div>
            <div class="header-actions">
                <button class="action-btn" onclick="location.href='manage_book.php'">
                    <i class="fas fa-plus"></i> Add Book
                </button>
                <button class="action-btn" onclick="location.href='manage_member.php'">
                    <i class="fas fa-user-plus"></i> Add Member
                </button>
            </div>
        </header>

        <!-- Statistics Cards -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon books">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_books); ?></h3>
                    <p>Total Books</p>
                    <span class="stat-change positive">+12 this month</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon members">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_members); ?></h3>
                    <p>Registered Members</p>
                    <span class="stat-change positive">+5 this week</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon issued">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($issued_books); ?></h3>
                    <p>Books Issued</p>
                    <span class="stat-change neutral">Currently active</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon overdue">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($overdue_books); ?></h3>
                    <p>Overdue Books</p>
                    <span class="stat-change negative">Requires attention</span>
                </div>
            </div>
        </section>

        <!-- Charts and Analytics -->
        <section class="analytics-section">
            <div class="chart-container">
                <div class="chart-card">
                    <h3><i class="fas fa-chart-line"></i> Monthly Activity</h3>
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3><i class="fas fa-chart-pie"></i> Book Categories</h3>
                    <canvas id="categoryChart" width="400" height="200"></canvas>
                </div>
            </div>
        </section>

        <!-- Recent Activities and Quick Actions -->
        <section class="dashboard-grid">
            <div class="recent-activities">
                <h3><i class="fas fa-history"></i> Recent Activities</h3>
                <div class="activity-list">
                    <?php while ($activity = $recent_issues->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong><?php echo htmlspecialchars($activity['member_name']); ?></strong> borrowed <strong><?php echo htmlspecialchars($activity['book_title']); ?></strong></p>
                            <span class="activity-time"><?php echo date('M j, Y', strtotime($activity['issue_date'])); ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="overdue-alerts">
                <h3><i class="fas fa-exclamation-circle"></i> Overdue Alerts</h3>
                <div class="alert-list">
                    <?php while ($overdue = $overdue_list->fetch_assoc()): ?>
                    <div class="alert-item">
                        <div class="alert-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="alert-content">
                            <p><strong><?php echo htmlspecialchars($overdue['member_name']); ?></strong> - <?php echo htmlspecialchars($overdue['book_title']); ?></p>
                            <span class="alert-time"><?php echo $overdue['days_overdue']; ?> days overdue</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <!-- Quick Stats -->
        <section class="quick-stats">
            <div class="stat-row">
                <div class="mini-stat">
                    <i class="fas fa-money-bill-wave"></i>
                    <div>
                        <h4>Total Fines</h4>
                        <p>$<?php echo number_format($total_fines, 2); ?></p>
                    </div>
                </div>
                
                <div class="mini-stat">
                    <i class="fas fa-star"></i>
                    <div>
                        <h4>Popular Books</h4>
                        <p>View Top 5</p>
                    </div>
                </div>
                
                <div class="mini-stat">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <h4>Today's Returns</h4>
                        <p>Check Schedule</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        setInterval(updateTime, 1000);
        updateTime();

        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Books Issued',
                    data: [65, 59, 80, 81, 56, 55],
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Books Returned',
                    data: [28, 48, 40, 19, 86, 27],
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Fiction', 'Non-Fiction', 'Science', 'History', 'Technology'],
                datasets: [{
                    data: [300, 250, 180, 120, 200],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Add some interactivity
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
            });
        });
    </script>
</body>
</html>
