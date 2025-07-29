// Admin Dashboard JavaScript
class AdminDashboard {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.startRealTimeUpdates();
    }

    init() {
        // Initialize charts and components
        this.initializeCharts();
        this.updateTime();
        this.setupNotifications();
    }

    setupEventListeners() {
        // Sidebar toggle for mobile
        const sidebarToggle = document.createElement('button');
        sidebarToggle.className = 'sidebar-toggle';
        sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        
        if (window.innerWidth <= 768) {
            document.querySelector('.dashboard-header').prepend(sidebarToggle);
        }

        // Stat card interactions
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', (e) => this.handleStatCardClick(e));
        });

        // Mini stat interactions
        document.querySelectorAll('.mini-stat').forEach(stat => {
            stat.addEventListener('click', (e) => this.handleMiniStatClick(e));
        });

        // Activity item interactions
        document.querySelectorAll('.activity-item').forEach(item => {
            item.addEventListener('click', (e) => this.handleActivityClick(e));
        });

        // Alert item interactions
        document.querySelectorAll('.alert-item').forEach(item => {
            item.addEventListener('click', (e) => this.handleAlertClick(e));
        });
    }

    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('open');
    }

    updateTime() {
        const timeElement = document.getElementById('currentTime');
        if (!timeElement) return;

        const updateTimeDisplay = () => {
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
            timeElement.textContent = timeString;
        };

        updateTimeDisplay();
        setInterval(updateTimeDisplay, 1000);
    }

    initializeCharts() {
        // Activity Chart
        const activityCtx = document.getElementById('activityChart');
        if (activityCtx) {
            this.activityChart = new Chart(activityCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Books Issued',
                        data: [65, 59, 80, 81, 56, 55],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Books Returned',
                        data: [28, 48, 40, 19, 86, 27],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            this.categoryChart = new Chart(categoryCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Fiction', 'Non-Fiction', 'Science', 'History', 'Technology'],
                    datasets: [{
                        data: [300, 250, 180, 120, 200],
                        backgroundColor: [
                            '#10b981',
                            '#3b82f6',
                            '#f59e0b',
                            '#ef4444',
                            '#8b5cf6'
                        ],
                        borderWidth: 0,
                        hoverBorderWidth: 2,
                        hoverBorderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
    }

    setupNotifications() {
        // Check for overdue books and show notifications
        const overdueCount = document.querySelector('.stat-card.overdue .stat-content h3');
        if (overdueCount && parseInt(overdueCount.textContent) > 0) {
            this.showNotification('Overdue Books Alert', `You have ${overdueCount.textContent} overdue books that require attention.`, 'warning');
        }
    }

    showNotification(title, message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-header">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${title}</span>
                <button onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="notification-body">
                ${message}
            </div>
        `;

        // Add notification styles if not already present
        if (!document.querySelector('#notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                    border-left: 4px solid #60a5fa;
                    z-index: 1000;
                    min-width: 300px;
                    max-width: 400px;
                    animation: slideInRight 0.3s ease;
                }
                .notification-warning { border-left-color: #f59e0b; }
                .notification-error { border-left-color: #ef4444; }
                .notification-success { border-left-color: #10b981; }
                .notification-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 15px 20px;
                    border-bottom: 1px solid #e2e8f0;
                    font-weight: 600;
                }
                .notification-header button {
                    margin-left: auto;
                    background: none;
                    border: none;
                    cursor: pointer;
                    color: #64748b;
                }
                .notification-body {
                    padding: 15px 20px;
                    color: #374151;
                    line-height: 1.5;
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    getNotificationIcon(type) {
        const icons = {
            info: 'info-circle',
            warning: 'exclamation-triangle',
            error: 'times-circle',
            success: 'check-circle'
        };
        return icons[type] || 'info-circle';
    }

    handleStatCardClick(event) {
        const card = event.currentTarget;
        const statType = card.querySelector('.stat-icon').classList[1];
        
        // Add click animation
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
            card.style.transform = 'scale(1)';
        }, 150);

        // Navigate based on stat type
        const routes = {
            books: 'manage_book.php',
            members: 'manage_member.php',
            issued: 'manage_issue.php',
            overdue: 'fine_details.php'
        };

        if (routes[statType]) {
            window.location.href = routes[statType];
        }
    }

    handleMiniStatClick(event) {
        const stat = event.currentTarget;
        const title = stat.querySelector('h4').textContent;
        
        // Add click animation
        stat.style.transform = 'scale(0.95)';
        setTimeout(() => {
            stat.style.transform = 'scale(1)';
        }, 150);

        // Handle different mini stat types
        switch (title) {
            case 'Total Fines':
                window.location.href = 'fine_details.php';
                break;
            case 'Popular Books':
                this.showPopularBooksModal();
                break;
            case "Today's Returns":
                this.showTodaysReturnsModal();
                break;
        }
    }

    handleActivityClick(event) {
        const activity = event.currentTarget;
        activity.style.background = '#f0f9ff';
        setTimeout(() => {
            activity.style.background = '';
        }, 200);
    }

    handleAlertClick(event) {
        const alert = event.currentTarget;
        alert.style.background = '#fef2f2';
        setTimeout(() => {
            alert.style.background = '';
        }, 200);
    }

    showPopularBooksModal() {
        // Create modal for popular books
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Popular Books</h3>
                    <button onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading popular books...</p>
                    </div>
                </div>
            </div>
        `;

        // Add loading spinner styles
        if (!document.querySelector('#loading-styles')) {
            const styles = document.createElement('style');
            styles.id = 'loading-styles';
            styles.textContent = `
                .loading-spinner {
                    text-align: center;
                    padding: 40px;
                    color: #64748b;
                }
                .loading-spinner i {
                    font-size: 24px;
                    margin-bottom: 10px;
                    display: block;
                }
                .popular-books-list {
                    max-height: 400px;
                    overflow-y: auto;
                }
                .popular-book-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .popular-book-item:last-child {
                    border-bottom: none;
                }
                .book-info h4 {
                    margin: 0 0 5px 0;
                    color: #1e293b;
                    font-size: 16px;
                }
                .book-info p {
                    margin: 0;
                    color: #64748b;
                    font-size: 14px;
                }
                .book-stats {
                    text-align: right;
                }
                .book-stats .issue-count {
                    font-weight: 600;
                    color: #10b981;
                    font-size: 18px;
                }
                .book-stats .availability {
                    font-size: 12px;
                    color: #64748b;
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(modal);

        // Fetch popular books data
        fetch('api_dashboard.php?action=popular_books')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updatePopularBooksModal(modal, data.data);
                } else {
                    modal.querySelector('.modal-body').innerHTML = '<p>Error loading popular books.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching popular books:', error);
                modal.querySelector('.modal-body').innerHTML = '<p>Error loading popular books.</p>';
            });
    }

    updatePopularBooksModal(modal, books) {
        const modalBody = modal.querySelector('.modal-body');
        
        if (books.length === 0) {
            modalBody.innerHTML = '<p>No book data available.</p>';
            return;
        }

        let booksHtml = '<div class="popular-books-list">';
        books.forEach(book => {
            booksHtml += `
                <div class="popular-book-item">
                    <div class="book-info">
                        <h4>${book.title}</h4>
                        <p>${book.author} • ${book.category}</p>
                    </div>
                    <div class="book-stats">
                        <div class="issue-count">${book.issue_count}</div>
                        <div class="availability">${book.availability} available</div>
                    </div>
                </div>
            `;
        });
        booksHtml += '</div>';
        
        modalBody.innerHTML = booksHtml;
    }

        // Add modal styles if not already present
        if (!document.querySelector('#modal-styles')) {
            const styles = document.createElement('style');
            styles.id = 'modal-styles';
            styles.textContent = `
                .modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                }
                .modal-content {
                    background: white;
                    border-radius: 12px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                }
                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .modal-header button {
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-size: 18px;
                    color: #64748b;
                }
                .modal-body {
                    padding: 20px;
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(modal);
    }

    showTodaysReturnsModal() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Today's Returns</h3>
                    <button onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading today's returns...</p>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Fetch today's returns data
        fetch('api_dashboard.php?action=stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateTodaysReturnsModal(modal, data.data.todays_returns);
                } else {
                    modal.querySelector('.modal-body').innerHTML = '<p>Error loading today\'s returns.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching today\'s returns:', error);
                modal.querySelector('.modal-body').innerHTML = '<p>Error loading today\'s returns.</p>';
            });
    }

    updateTodaysReturnsModal(modal, returnsCount) {
        const modalBody = modal.querySelector('.modal-body');
        
        if (returnsCount === 0) {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 20px;"></i>
                    <h3 style="color: #10b981; margin-bottom: 10px;">Great!</h3>
                    <p style="color: #64748b;">No books are due for return today.</p>
                </div>
            `;
        } else {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-clock" style="font-size: 48px; color: #f59e0b; margin-bottom: 20px;"></i>
                    <h3 style="color: #f59e0b; margin-bottom: 10px;">${returnsCount} Books Due Today</h3>
                    <p style="color: #64748b;">These books need to be returned today.</p>
                    <div style="margin-top: 20px;">
                        <button onclick="window.location.href='manage_issue.php'" style="
                            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                            color: white;
                            border: none;
                            padding: 10px 20px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                        ">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            `;
        }
    }

    startRealTimeUpdates() {
        // Update statistics every 30 seconds
        setInterval(() => {
            this.updateStatistics();
        }, 30000);
    }

    updateStatistics() {
        // Fetch real-time statistics from API
        fetch('api_dashboard.php?action=stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateDashboardStats(data.data);
                }
            })
            .catch(error => {
                console.error('Error updating statistics:', error);
            });
    }

    updateDashboardStats(stats) {
        // Update stat cards with real data
        const statElements = {
            'total_books': document.querySelector('.stat-card:nth-child(1) .stat-content h3'),
            'total_members': document.querySelector('.stat-card:nth-child(2) .stat-content h3'),
            'issued_books': document.querySelector('.stat-card:nth-child(3) .stat-content h3'),
            'overdue_books': document.querySelector('.stat-card:nth-child(4) .stat-content h3')
        };

        if (statElements.total_books) {
            statElements.total_books.textContent = AdminUtils.formatNumber(stats.total_books);
        }
        if (statElements.total_members) {
            statElements.total_members.textContent = AdminUtils.formatNumber(stats.total_members);
        }
        if (statElements.issued_books) {
            statElements.issued_books.textContent = AdminUtils.formatNumber(stats.issued_books);
        }
        if (statElements.overdue_books) {
            statElements.overdue_books.textContent = AdminUtils.formatNumber(stats.overdue_books);
        }

        // Update total fines in mini stats
        const totalFinesElement = document.querySelector('.mini-stat:nth-child(1) p');
        if (totalFinesElement) {
            totalFinesElement.textContent = AdminUtils.formatCurrency(stats.total_fines);
        }

        // Add subtle animation to show update
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.opacity = '0.8';
            setTimeout(() => {
                card.style.opacity = '1';
            }, 500);
        });
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AdminDashboard();
});

// Add some utility functions
window.AdminUtils = {
    formatNumber: (num) => {
        return new Intl.NumberFormat().format(num);
    },
    
    formatCurrency: (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    },
    
    formatDate: (date) => {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
};