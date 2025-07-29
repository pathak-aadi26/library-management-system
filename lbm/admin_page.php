<?php
session_start();
if (!isset($_SESSION['mob_no'])) {
    header("Location: login.php");
    exit();
}

include 'configure.php';

// Fetch real-time statistics
$total_books = $conn->query("SELECT COUNT(*) as count FROM book_db")->fetch_assoc()['count'];
$total_members = $conn->query("SELECT COUNT(*) as count FROM member_db")->fetch_assoc()['count'];
$issued_books = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_date IS NULL")->fetch_assoc()['count'];
$overdue_books = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE due_date < CURDATE() AND return_date IS NULL")->fetch_assoc()['count'];
$pending_fines = $conn->query("SELECT COUNT(*) as count FROM fines WHERE status = 'Pending'")->fetch_assoc()['count'];
$total_fine_amount = $conn->query("SELECT SUM(fine_amount) as total FROM fines WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;

// Fetch recent activities
$recent_issues = $conn->query("SELECT ib.issue_id, ib.issue_date, b.title, m.first_name, m.last_name 
                               FROM issued_books ib 
                               JOIN book_db b ON ib.book_id = b.book_id 
                               JOIN member_db m ON ib.member_id = m.member_id 
                               ORDER BY ib.issue_date DESC LIMIT 5");

$recent_returns = $conn->query("SELECT ib.return_date, b.title, m.first_name, m.last_name 
                               FROM issued_books ib 
                               JOIN book_db b ON ib.book_id = b.book_id 
                               JOIN member_db m ON ib.member_id = m.member_id 
                               WHERE ib.return_date IS NOT NULL 
                               ORDER BY ib.return_date DESC LIMIT 5");

// Fetch category distribution for chart
$category_stats = $conn->query("SELECT category, COUNT(*) as count FROM book_db GROUP BY category LIMIT 10");

// Fetch monthly issue trends
$monthly_trends = $conn->query("SELECT DATE_FORMAT(issue_date, '%Y-%m') as month, COUNT(*) as count 
                               FROM issued_books 
                               WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                               GROUP BY month ORDER BY month");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Library Management</title>
    <link rel="stylesheet" href="admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-reader"></i>
            <h2>Library Admin</h2>
        </div>
        <nav>
            <a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_member.php"><i class="fas fa-users"></i> Manage Members</a>
            <a href="manage_book.php"><i class="fas fa-book"></i> Manage Books</a>
            <a href="manage_issue.php"><i class="fas fa-hand-holding"></i> Issue Books</a>
            <a href="manage_return.php"><i class="fas fa-undo"></i> Returns</a>
            <a href="fine_details.php"><i class="fas fa-money-bill-wave"></i> Fines</a>
            <a href="#"><i class="fas fa-chart-line"></i> Reports</a>
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
                <div class="search-box">
                    <input type="text" placeholder="Search books, members..." id="searchInput">
                    <i class="fas fa-search"></i>
                </div>
                <div class="notification-bell" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-count">3</span>
                </div>
                <div class="admin-profile">
                    <img src="https://via.placeholder.com/40x40/4a72f5/ffffff?text=<?php echo substr($_SESSION['name'], 0, 1); ?>" alt="Admin">
                    <div class="profile-dropdown">
                        <a href="#"><i class="fas fa-user"></i> Profile</a>
                        <a href="#"><i class="fas fa-cog"></i> Settings</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon books-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_books); ?></h3>
                    <p>Total Books</p>
                    <span class="stat-change positive">+12 this month</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon members-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_members); ?></h3>
                    <p>Registered Members</p>
                    <span class="stat-change positive">+5 this week</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon issued-icon">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($issued_books); ?></h3>
                    <p>Books Issued</p>
                    <span class="stat-change neutral">Current</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon overdue-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($overdue_books); ?></h3>
                    <p>Overdue Books</p>
                    <span class="stat-change negative">Requires attention</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon fines-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($pending_fines); ?></h3>
                    <p>Pending Fines</p>
                    <span class="stat-change">₹<?php echo number_format($total_fine_amount, 2); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon returns-icon">
                    <i class="fas fa-undo"></i>
                </div>
                <div class="stat-content">
                    <h3 id="todayReturns">0</h3>
                    <p>Returns Today</p>
                    <span class="stat-change positive">Updated</span>
                </div>
            </div>
        </section>

        <div class="dashboard-content">
            <div class="charts-section">
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Book Categories</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3><i class="fas fa-chart-line"></i> Monthly Trends</h3>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="activity-section">
                <div class="activity-card">
                    <h3><i class="fas fa-clock"></i> Recent Issues</h3>
                    <div class="activity-list">
                        <?php while($issue = $recent_issues->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon issue-icon">
                                <i class="fas fa-hand-holding"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']); ?></strong> borrowed <strong><?php echo htmlspecialchars($issue['title']); ?></strong></p>
                                <span class="activity-time"><?php echo date('M j, Y', strtotime($issue['issue_date'])); ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="activity-card">
                    <h3><i class="fas fa-undo"></i> Recent Returns</h3>
                    <div class="activity-list">
                        <?php while($return = $recent_returns->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon return-icon">
                                <i class="fas fa-undo"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo htmlspecialchars($return['first_name'] . ' ' . $return['last_name']); ?></strong> returned <strong><?php echo htmlspecialchars($return['title']); ?></strong></p>
                                <span class="activity-time"><?php echo date('M j, Y', strtotime($return['return_date'])); ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="action-buttons">
                <button class="action-btn" onclick="location.href='manage_issue.php'">
                    <i class="fas fa-plus"></i>
                    Issue Book
                </button>
                <button class="action-btn" onclick="location.href='manage_return.php'">
                    <i class="fas fa-undo"></i>
                    Process Return
                </button>
                <button class="action-btn" onclick="location.href='manage_book.php'">
                    <i class="fas fa-book-medical"></i>
                    Add Book
                </button>
                <button class="action-btn" onclick="location.href='manage_member.php'">
                    <i class="fas fa-user-plus"></i>
                    Add Member
                </button>
            </div>
        </div>
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
        
        updateTime();
        setInterval(updateTime, 1000);

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = <?php 
            $categoryData = [];
            while($cat = $category_stats->fetch_assoc()) {
                $categoryData[] = ['label' => $cat['category'], 'value' => $cat['count']];
            }
            echo json_encode($categoryData);
        ?>;
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.label),
                datasets: [{
                    data: categoryData.map(item => item.value),
                    backgroundColor: [
                        '#4a72f5', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4',
                        '#feca57', '#ff9ff3', '#54a0ff', '#5f27cd', '#00d2d3'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = <?php 
            $trendData = [];
            while($trend = $monthly_trends->fetch_assoc()) {
                $trendData[] = ['month' => $trend['month'], 'count' => $trend['count']];
            }
            echo json_encode($trendData);
        ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.month),
                datasets: [{
                    label: 'Books Issued',
                    data: trendData.map(item => item.count),
                    borderColor: '#4a72f5',
                    backgroundColor: 'rgba(74, 114, 245, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Real-time data updates
        function updateDashboardData() {
            fetch('dashboard_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update statistics
                        document.querySelector('.stat-card:nth-child(1) .stat-content h3').textContent = data.data.total_books.toLocaleString();
                        document.querySelector('.stat-card:nth-child(2) .stat-content h3').textContent = data.data.total_members.toLocaleString();
                        document.querySelector('.stat-card:nth-child(3) .stat-content h3').textContent = data.data.issued_books.toLocaleString();
                        document.querySelector('.stat-card:nth-child(4) .stat-content h3').textContent = data.data.overdue_books.toLocaleString();
                        document.querySelector('.stat-card:nth-child(5) .stat-content h3').textContent = data.data.pending_fines.toLocaleString();
                        document.querySelector('.stat-card:nth-child(5) .stat-change').textContent = '₹' + parseFloat(data.data.total_fine_amount).toFixed(2);
                        document.getElementById('todayReturns').textContent = data.data.today_returns.toLocaleString();
                        
                        // Update notification count
                        const notificationCount = document.querySelector('.notification-count');
                        if (data.data.notifications.length > 0) {
                            notificationCount.textContent = data.data.notifications.length;
                            notificationCount.style.display = 'flex';
                        } else {
                            notificationCount.style.display = 'none';
                        }
                        
                        // Show notifications
                        data.data.notifications.forEach(notification => {
                            showNotification(notification.message, notification.type);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching dashboard data:', error);
                });
        }
        
        // Update data every 30 seconds
        setInterval(updateDashboardData, 30000);
        
        // Initial update
        updateDashboardData();

        // Add some interactive animations
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Real-time notifications from API
        function checkForNewNotifications() {
            fetch('dashboard_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.notifications.length > 0) {
                        data.data.notifications.forEach(notification => {
                            showNotification(notification.message, notification.type);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error checking notifications:', error);
                });
        }
        
        // Check for new notifications every minute
        setInterval(checkForNewNotifications, 60000);
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            if (searchTerm.length > 2) {
                // You can implement search functionality here
                console.log('Searching for:', searchTerm);
            }
        });
        
        // Toggle notifications
        function toggleNotifications() {
            const notificationCount = document.querySelector('.notification-count');
            if (notificationCount.style.display !== 'none') {
                notificationCount.style.display = 'none';
                showNotification('Notifications cleared', 'info');
            }
        }
        
        // Add click outside to close dropdowns
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.admin-profile')) {
                document.querySelectorAll('.profile-dropdown').forEach(dropdown => {
                    dropdown.style.opacity = '0';
                    dropdown.style.visibility = 'hidden';
                });
            }
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            if (e.key === 'Escape') {
                document.getElementById('searchInput').blur();
            }
        });
        
        // Add loading states for better UX
        function showLoading(element) {
            element.classList.add('loading');
        }
        
        function hideLoading(element) {
            element.classList.remove('loading');
        }
        
        // Add smooth scrolling for better navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
