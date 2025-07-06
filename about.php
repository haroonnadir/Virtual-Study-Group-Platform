<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About Us | Virtual Study Group Platform</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.6;
            background-color: #f5f5f5;
        }
        .main {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background-color: var(--primary);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-weight: bold;
            font-size: 1.5rem;
        }
        nav a {
            color: white;
            text-decoration: none;
            margin-left: 1.5rem;
            transition: all 0.3s ease;
        }
        nav a:hover {
            color: var(--secondary);
        }
        .btn {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .about-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .main-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            text-align: center;
        }
        .student-details {
            margin-bottom: 2rem;
            width: 100%;
        }
        .user-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1.5rem;
            border: 5px solid var(--light);
        }
        .user-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-credential h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        .user-credential p {
            margin: 0.3rem 0;
            color: #666;
        }
        .campus-info {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            width: 100%;
            max-width: 500px;
        }
        .campus-info p {
            margin: 0.5rem 0;
            text-align: left;
            padding-left: 20%;
        }
        .campus, .city {
            font-weight: bold;
            color: var(--dark);
        }
        footer {
            background-color: var(--primary);
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <div class="main">
        <header>
            <div class="logo">Virtual Study Group Platform</div>
            <nav>
                <a href="index.php">Home page</a>
                <a href="contact.php">Contact</a>
                <a href="about.php">About</a>
                <a href="login.php" class="btn">Login</a>
                <a href="register.php" class="btn">Register</a>
            </nav>
        </header>

        <main>
            <div class="about-container">
                <div class="main-card">
                    <div class="student-details">
                        <div class="user-img">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=634&q=80" alt="Student Image" />
                        </div>
                        <div class="user-credential">
                            <h4>AHMED ALI</h4>
                            <p>BSCS</p>
                            <p>BC210428185</p>
                            <p class="std-id">Virtual Study Group Platform Developer</p>
                        </div>
                    </div>
                    <div class="campus-info">
                        <p><span class="campus">Campus Name:</span> Shadbagh Campus, Lahore</p>
                        <p><span class="city">City:</span> Lahore</p>
                    </div>
                </div>
            </div>
        </main>

        <footer>
            &copy; Virtual Study Group Platform <span id="year"></span> | Created by AHMED ALI
        </footer>
    </div>

    <script>
        // Auto-update copyright year
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</body>
</html>