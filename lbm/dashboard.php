<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Library Management - User Home</title>
    <link rel="stylesheet" href="dash.css" />
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Welcome to Your Library</h1>
            <p>Hello, <span id="username">User</span>!</p>
        </div>
    </header>

    <nav class="navbar">
        <ul class="container">
            <li><a href="#">Dashboard</a></li>
            <li><a href="#">Book Issued</a></li>
            <li><a href="#">Wishlist</a></li>
             <li><a href="#">Account</a></li>
            <div class="search-bar">
              <input type="text" placeholder="Search..."  id="searchBar" autocomplete="off">
            </div>
            <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
        </ul>
        
        
    </nav> 
    <div id="searchResult" style="max-width:700px;margin:20px auto;display:none;background:#fff;padding:15px;border-radius:8px;box-shadow:0 0 10px #aaa;"></div>
    
    
    <main class="main-content">
        <div class="container">
            <h2>User Dashboard</h2>

            <!-- Profile Summary -->
            <section class="profile">
                <h3>Profile Information</h3>
                <p><strong>Name:</strong> Aditya Anand</p>
                <p><strong>Member Since:</strong> Jan 2024</p>
                <p><strong>Membership Level:</strong> Premium</p>
            </section>

            <!-- Borrowed Books -->
            <section class="borrowed-books">
                <h3>Borrowed Books</h3>
                <ul>
                    <li><strong>The Alchemist</strong> - Due: Aug 10, 2025</li>
                    <li><strong>1984</strong> - Due: Aug 15, 2025</li>
                    <li><strong>Rich Dad Poor Dad</strong> - Due: Aug 22, 2025</li>
                </ul>
            </section>

            <!-- Notifications -->
            <section class="notifications">
                <h3>Notifications</h3>
                <ul>
                    <li>📢 New book arrivals: "Atomic Habits" now available!</li>
                    <li>⚠️ 1 book is due soon. Return before Aug 10, 2025 to avoid fines.</li>
                    <li>🎉 Summer Reading Challenge starts next week!</li>
                </ul>
            </section>

            <!-- Quick Actions -->
            <section class="actions">
                <h3>Quick Actions</h3>
                <button onclick="alert('Search Catalog')">Search Catalog</button>
                <button onclick="alert('Renew Books')">Renew Books</button>
                <button onclick="alert('View History')">View History</button>
            </section>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2025 Library Management System. All rights reserved.</p>
    </footer>

    <script src="script.js"></script>
  <script>
    document.getElementById('searchBar').addEventListener('keyup', function() {
    const query = this.value.trim();
    const resultBox = document.getElementById('searchResult');

    if (query.length === 0) {
        resultBox.style.display = 'none';
        resultBox.innerHTML = '';
        return;
    }

    fetch('search_books.php?q=' + encodeURIComponent(query))
        .then(response => response.text())
        .then(data => {
            resultBox.style.display = 'block';
            resultBox.innerHTML = data;
        })
        .catch(error => {
            resultBox.innerHTML = '<p>Error fetching results.</p>';
        });
     });
  </script>

</body>
</html>

