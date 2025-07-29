<?php
session_start();
if (!isset($_SESSION['mob_no'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "member_db";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get real-time statistics
$total_books_query = $conn->query("SELECT SUM(total_stock) as total_books FROM book_db");
$total_books = $total_books_query->fetch_assoc()['total_books'] ?? 0;

$available_books_query = $conn->query("
    SELECT SUM(b.total_stock) - COUNT(ib.book_id) as available_books 
    FROM book_db b 
    LEFT JOIN issued_books ib ON b.book_id = ib.book_id AND ib.return_date IS NULL
");
$available_books = $available_books_query->fetch_assoc()['available_books'] ?? 0;

$total_members_query = $conn->query("SELECT COUNT(*) as total_members FROM member_db WHERE role = 'user'");
$total_members = $total_members_query->fetch_assoc()['total_members'] ?? 0;

$issued_books_query = $conn->query("SELECT COUNT(*) as issued_books FROM issued_books WHERE return_date IS NULL");
$issued_books = $issued_books_query->fetch_assoc()['issued_books'] ?? 0;

$overdue_books_query = $conn->query("SELECT COUNT(*) as overdue_books FROM issued_books WHERE return_date IS NULL AND due_date < CURDATE()");
$overdue_books = $overdue_books_query->fetch_assoc()['overdue_books'] ?? 0;

$pending_fines_query = $conn->query("SELECT COUNT(*) as pending_fines FROM fines WHERE status = 'Pending'");
$pending_fines = $pending_fines_query->fetch_assoc()['pending_fines'] ?? 0;

$total_fine_amount_query = $conn->query("SELECT SUM(fine_amount) as total_fine_amount FROM fines WHERE status = 'Pending'");
$total_fine_amount = $total_fine_amount_query->fetch_assoc()['total_fine_amount'] ?? 0;

// Recent activities
$recent_issues_query = $conn->query("
    SELECT m.first_name, m.last_name, b.title, ib.issue_date, ib.due_date
    FROM issued_books ib 
    JOIN member_db m ON ib.member_id = m.member_id 
    JOIN book_db b ON ib.book_id = b.book_id 
    WHERE ib.return_date IS NULL 
    ORDER BY ib.issue_date DESC 
    LIMIT 5
");

// Books due today
$due_today_query = $conn->query("
    SELECT COUNT(*) as due_today 
    FROM issued_books 
    WHERE return_date IS NULL AND due_date = CURDATE()
");
$due_today = $due_today_query->fetch_assoc()['due_today'] ?? 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Library Management</title>
    <link rel="stylesheet" href="admin.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-reader"></i>
            <h2>Library Admin</h2>
        </div>
        <nav>
            <a href="#" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_member.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Manage Members</span>
            </a>
            <a href="manage_book.php" class="nav-link">
                <i class="fas fa-book"></i>
                <span>Manage Books</span>
            </a>
            <a href="manage_issue.php" class="nav-link">
                <i class="fas fa-hand-holding"></i>
                <span>Issue Books</span>
            </a>
            <a href="manage_return.php" class="nav-link">
                <i class="fas fa-undo"></i>
                <span>Returns</span>
            </a>
            <a href="fine_details.php" class="nav-link">
                <i class="fas fa-dollar-sign"></i>
                <span>Fine Management</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <button class="logout-btn" onclick="location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </aside>

    <main class="main-content">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Overview
                </h1>
                <div class="header-info">
                    <span class="welcome-text">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span>
                    <div class="current-time" id="currentTime"></div>
                </div>
            </div>
            <div class="quick-actions">
                <button class="action-btn" onclick="location.href='manage_issue.php'">
                    <i class="fas fa-plus"></i>
                    Issue Book
                </button>
                <button class="action-btn" onclick="location.href='manage_return.php'">
                    <i class="fas fa-undo"></i>
                    Return Book
                </button>
                <button class="action-btn" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>
        </header>

        <!-- Statistics Cards -->
        <section class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_books); ?></h3>
                    <p>Total Books</p>
                    <span class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        Collection
                    </span>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($available_books); ?></h3>
                    <p>Available Books</p>
                    <span class="stat-trend">
                        <i class="fas fa-check-circle"></i>
                        Ready to issue
                    </span>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_members); ?></h3>
                    <p>Active Members</p>
                    <span class="stat-trend">
                        <i class="fas fa-user-plus"></i>
                        Registered users
                    </span>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($issued_books); ?></h3>
                    <p>Books Issued</p>
                    <span class="stat-trend">
                        <i class="fas fa-clock"></i>
                        Currently out
                    </span>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($overdue_books); ?></h3>
                    <p>Overdue Books</p>
                    <span class="stat-trend">
                        <i class="fas fa-exclamation-circle"></i>
                        Need attention
                    </span>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($due_today); ?></h3>
                    <p>Due Today</p>
                    <span class="stat-trend">
                        <i class="fas fa-calendar-check"></i>
                        Today's returns
                    </span>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>$<?php echo number_format($total_fine_amount, 2); ?></h3>
                    <p>Pending Fines</p>
                    <span class="stat-trend">
                        <i class="fas fa-coins"></i>
                        <?php echo $pending_fines; ?> records
                    </span>
                </div>
            </div>

            <div class="stat-card teal">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_books > 0 ? round(($issued_books / $total_books) * 100, 1) : 0; ?>%</h3>
                    <p>Utilization Rate</p>
                    <span class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        Library usage
                    </span>
                </div>
            </div>
        </section>

        <!-- Charts and Activity Section -->
        <section class="dashboard-grid">
            <div class="chart-container">
                <div class="widget-header">
                    <h3><i class="fas fa-chart-pie"></i> Library Statistics</h3>
                    <button class="btn-refresh" onclick="updateCharts()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <canvas id="libraryChart"></canvas>
            </div>

            <div class="activity-feed">
                <div class="widget-header">
                    <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                    <span class="activity-indicator">
                        <i class="fas fa-circle"></i> Live
                    </span>
                </div>
                <div class="activity-list" id="activityList">
                    <?php while ($activity = $recent_issues_query->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong> borrowed <em><?php echo htmlspecialchars($activity['title']); ?></em></p>
                            <span class="activity-time"><?php echo date('M j, Y', strtotime($activity['issue_date'])); ?> • Due: <?php echo date('M j', strtotime($activity['due_date'])); ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <!-- Notifications Panel -->
        <section class="notifications-panel" id="notificationsPanel" style="display: none;">
            <div class="widget-header">
                <h3><i class="fas fa-bell"></i> Notifications</h3>
                <button class="btn-refresh" onclick="toggleNotifications()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="notifications-list" id="notificationsList">
                <!-- Notifications will be loaded here -->
            </div>
        </section>

        <!-- Quick Access Panel -->
        <section class="quick-access">
            <h3><i class="fas fa-bolt"></i> Quick Access</h3>
            <div class="quick-grid">
                <div class="quick-item" onclick="location.href='manage_book.php'">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Book</span>
                </div>
                <div class="quick-item" onclick="location.href='manage_member.php'">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Member</span>
                </div>
                <div class="quick-item" onclick="location.href='fine_details.php'">
                    <i class="fas fa-eye"></i>
                    <span>View Fines</span>
                </div>
                <div class="quick-item" onclick="generateReport()">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Report</span>
                </div>
                <div class="quick-item" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </div>
            </div>
        </section>
    </main>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
        let libraryChart = null;
        let dashboardData = {};
        
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Initialize chart
        function initializeChart() {
            const ctx = document.getElementById('libraryChart').getContext('2d');
            libraryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Available Books', 'Issued Books', 'Overdue Books'],
                    datasets: [{
                        data: [<?php echo $available_books; ?>, <?php echo $issued_books - $overdue_books; ?>, <?php echo $overdue_books; ?>],
                        backgroundColor: ['#10b981', '#3b82f6', '#ef4444'],
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
        }

        // Fetch real-time dashboard data
        async function fetchDashboardData(action = '') {
            try {
                const url = action ? `dashboard_data.php?action=${action}` : 'dashboard_data.php';
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                showToast('Error fetching data', 'error');
                return null;
            }
        }

        // Update statistics cards
        function updateStatistics(stats) {
            const statElements = {
                'total_books': document.querySelector('.stat-card.primary h3'),
                'available_books': document.querySelector('.stat-card.success h3'),
                'total_members': document.querySelector('.stat-card.info h3'),
                'issued_books': document.querySelector('.stat-card.warning h3'),
                'overdue_books': document.querySelector('.stat-card.danger h3'),
                'due_today': document.querySelector('.stat-card.purple h3'),
                'total_fine_amount': document.querySelector('.stat-card.orange h3'),
                'utilization_rate': document.querySelector('.stat-card.teal h3')
            };

            Object.keys(statElements).forEach(key => {
                if (statElements[key] && stats[key] !== undefined) {
                    if (key === 'total_fine_amount') {
                        statElements[key].textContent = '$' + Number(stats[key]).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    } else if (key === 'utilization_rate') {
                        statElements[key].textContent = stats[key] + '%';
                    } else {
                        statElements[key].textContent = Number(stats[key]).toLocaleString();
                    }
                    
                    // Add animation effect
                    statElements[key].parentElement.parentElement.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        statElements[key].parentElement.parentElement.style.transform = 'scale(1)';
                    }, 200);
                }
            });
        }

        // Update activity feed
        function updateActivityFeed(activities) {
            const activityList = document.getElementById('activityList');
            if (!activityList) return;

            activityList.innerHTML = '';

            activities.forEach(activity => {
                const activityItem = document.createElement('div');
                activityItem.className = 'activity-item';
                
                const timeAgo = getTimeAgo(activity.time);
                const dueInfo = activity.due_date ? ` • Due: ${formatDate(activity.due_date)}` : '';
                
                activityItem.innerHTML = `
                    <div class="activity-icon">
                        <i class="${activity.icon}"></i>
                    </div>
                    <div class="activity-content">
                        <p>${activity.message}</p>
                        <span class="activity-time">${timeAgo}${dueInfo}</span>
                    </div>
                `;
                
                activityList.appendChild(activityItem);
            });
        }

        // Update chart with new data
        function updateChart(chartData) {
            if (libraryChart && chartData.book_status) {
                libraryChart.data.datasets[0].data = chartData.book_status.data;
                libraryChart.update('none');
            }
        }

        // Load and display notifications
        async function loadNotifications() {
            const notifications = await fetchDashboardData('notifications');
            if (notifications) {
                displayNotifications(notifications);
            }
        }

        // Display notifications
        function displayNotifications(notifications) {
            const notificationsList = document.getElementById('notificationsList');
            if (!notificationsList) return;

            notificationsList.innerHTML = '';

            if (notifications.length === 0) {
                notificationsList.innerHTML = '<div class="no-notifications">No new notifications</div>';
                return;
            }

            notifications.forEach(notification => {
                const notificationItem = document.createElement('div');
                notificationItem.className = `notification-item notification-${notification.type}`;
                
                notificationItem.innerHTML = `
                    <div class="notification-icon">
                        <i class="${notification.icon}"></i>
                    </div>
                    <div class="notification-content">
                        <p>${notification.message}</p>
                        <span class="notification-priority priority-${notification.priority}">${notification.priority} priority</span>
                    </div>
                `;
                
                notificationsList.appendChild(notificationItem);
            });
        }

        // Toggle notifications panel
        function toggleNotifications() {
            const panel = document.getElementById('notificationsPanel');
            if (panel.style.display === 'none' || panel.style.display === '') {
                panel.style.display = 'block';
                loadNotifications();
            } else {
                panel.style.display = 'none';
            }
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('toast-show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('toast-show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }

        // Refresh dashboard data
        async function refreshDashboard() {
            showToast('Refreshing dashboard...', 'info');
            
            try {
                const stats = await fetchDashboardData('stats');
                const activities = await fetchDashboardData('activities');
                const chartData = await fetchDashboardData('chart_data');
                
                if (stats) updateStatistics(stats);
                if (activities) updateActivityFeed(activities);
                if (chartData) updateChart(chartData);
                
                showToast('Dashboard updated successfully', 'success');
            } catch (error) {
                showToast('Failed to refresh dashboard', 'error');
            }
        }

        // Update charts
        async function updateCharts() {
            const chartData = await fetchDashboardData('chart_data');
            if (chartData) {
                updateChart(chartData);
                showToast('Charts updated', 'success');
            }
        }

        // Generate report
        function generateReport() {
            showToast('Generating report... This feature will be available soon!', 'info');
        }

        // Utility functions
        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
            return `${Math.floor(diffInSeconds / 86400)} days ago`;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            updateTime();
            setInterval(updateTime, 1000);
            initializeChart();
            loadNotifications();
            
            // Auto-refresh every 30 seconds for real-time data
            setInterval(refreshDashboard, 30000);
            
            // Show initial notification
            setTimeout(() => {
                showToast('Dashboard loaded successfully', 'success');
            }, 1000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        refreshDashboard();
                        break;
                    case 'n':
                        e.preventDefault();
                        toggleNotifications();
                        break;
                }
            }
        });
    </script>
</body>
</html>
