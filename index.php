<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Study Group Platform | AHMED ALI</title>
    <style>
        /* Basic Styling */
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
            text-align: center;
        }
        header {
            background-color: var(--primary);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
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
            margin-top: 1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        .hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 8rem 2rem;
        }
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .hero p {
            font-size: 1.2rem;
            margin: 0 auto 2rem;
        }
        section {
            padding: 4rem 2rem;
        }
        .section-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-title {
            margin-bottom: 3rem;
            font-size: 2rem;
            color: var(--dark);
        }
        .course-grid, .group-grid, .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .course-card, .group-card, .testimonial-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .course-card:hover, .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .course-img, .group-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        footer {
            background-color: var(--primary);
            color: white;
            padding: 2rem;
            margin-top: 2rem;
        }
        .testimonial-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            display: block;
        }
        .testimonial-name {
            font-weight: bold;
            margin-top: 1rem;
        }
        .testimonial-role {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .groups {
            background-color: var(--light);
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Virtual Study Group Platform</div>
        <nav>
            <a href="#courses">Courses</a>
            <a href="#groups">Study Groups</a>
            <a href="about.php">About</a>
            <a href="login.php" class="btn">Login </a>
            <a href="register.php" class="btn">Register</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Learn Anytime, Anywhere</h1>
            <p>Join virtual study groups, collaborate with peers, and enhance your learning experience with our interactive platform.</p>
            <a href="login.php" class="btn">Get Started</a>
        </div>
    </section>

    <section class="courses" id="courses">
        <div class="section-content">
            <h2 class="section-title">Popular Courses</h2>
            <div class="course-grid">
                <div class="course-card">
                    <img src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Programming" class="course-img">
                    <h3>Introduction to Programming</h3>
                    <p>Learn the fundamentals of coding with Python. Perfect for beginners starting their coding journey.</p>
                    <a href="login.php" class="btn">Join Course</a>
                </div>
                <div class="course-card">
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Data Science" class="course-img">
                    <h3>Data Science Essentials</h3>
                    <p>Master data analysis and visualization techniques using Python and popular libraries.</p>
                    <a href="login.php" class="btn">Join Course</a>
                </div>
                <div class="course-card">
                    <img src="https://images.unsplash.com/photo-1547658719-da2b51169166?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Web Development" class="course-img">
                    <h3>Web Development Bootcamp</h3>
                    <p>Build responsive websites from scratch using HTML, CSS, and JavaScript.</p>
                    <a href="login.php" class="btn">Join Course</a>
                </div>
            </div>
        </div>
    </section>

    <section class="groups" id="groups">
        <div class="section-content">
            <h2 class="section-title">Active Study Groups</h2>
            <div class="group-grid">
                <div class="group-card">
                    <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Study Group" class="group-img">
                    <h3>Python Study Group</h3>
                    <p>Weekly meetups to discuss Python concepts and work on projects together.</p>
                    <p><strong>Next Session:</strong> Wed, 3:00 PM</p>
                    <a href="login.php" class="btn">Join Group</a>
                </div>
                <div class="group-card">
                    <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Study Group" class="group-img">
                    <h3>Web Dev Collaboration</h3>
                    <p>Group for web developers to share resources and get feedback on projects.</p>
                    <p><strong>Next Session:</strong> Fri, 5:00 PM</p>
                    <a href="login.php" class="btn">Join Group</a>
                </div>
                <div class="group-card">
                    <img src="https://images.unsplash.com/photo-1503676260728-1c00da094a0b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Study Group" class="group-img">
                    <h3>Data Science Study</h3>
                    <p>Discuss machine learning algorithms and work on datasets together.</p>
                    <p><strong>Next Session:</strong> Tue, 4:30 PM</p>
                    <a href="login.php" class="btn">Join Group</a>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonials">
        <div class="section-content">
            <h2 class="section-title">What Our Users Say</h2>
            <div class="testimonial-grid">
                <div class="testimonial-card">
                    <img src="https://randomuser.me/api/portraits/women/43.jpg" alt="User" class="testimonial-img">
                    <p>"This platform transformed how I study. The group sessions keep me motivated and accountable."</p>
                    <div class="testimonial-name">Sarah Johnson</div>
                    <div class="testimonial-role">Computer Science Student</div>
                </div>
                <div class="testimonial-card">
                    <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User" class="testimonial-img">
                    <p>"I've made great connections and improved my grades through the study groups. Highly recommended!"</p>
                    <div class="testimonial-name">Michael Chen</div>
                    <div class="testimonial-role">Engineering Student</div>
                </div>
                <div class="testimonial-card">
                    <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="User" class="testimonial-img">
                    <p>"The resource sharing feature saved me hours of searching for quality study materials."</p>
                    <div class="testimonial-name">Emma Rodriguez</div>
                    <div class="testimonial-role">Data Science Student</div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        &copy; Virtual Study Group Platform <span id="year"></span> | Created by AHMED ALI
    </footer>

    <script>
        // Auto-update copyright year
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</body>
</html>