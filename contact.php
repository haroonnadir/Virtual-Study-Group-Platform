<?php
// Configuration
define('RECIPIENT_EMAIL', 'yourEmail@gmail.com');
define('EMAIL_SUBJECT', 'New Contact Form Submission');
define('MIN_MESSAGE_LENGTH', 10);
define('MAX_MESSAGE_LENGTH', 1000);

// Initialize variables
$name = $email = $message = '';
$errors = [];
$success = false;

// Process form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        // Sanitize inputs
        $name = trim(filter_var($_POST['name'] ?? '', FILTER_SANITIZE_STRING));
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $message = trim(filter_var($_POST['message'] ?? '', FILTER_SANITIZE_STRING));
        $honeypot = trim($_POST['website'] ?? '');

        // Honeypot validation
        if (!empty($honeypot)) {
            // Bot detected - just pretend it worked
            $success = true;
        } else {
            // Validate inputs
            if (empty($name)) {
                $errors[] = "Name is required";
            } elseif (strlen($name) < 2) {
                $errors[] = "Name must be at least 2 characters";
            }

            if (empty($email)) {
                $errors[] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            }

            if (empty($message)) {
                $errors[] = "Message is required";
            } elseif (strlen($message) < MIN_MESSAGE_LENGTH) {
                $errors[] = "Message must be at least " . MIN_MESSAGE_LENGTH . " characters";
            } elseif (strlen($message) > MAX_MESSAGE_LENGTH) {
                $errors[] = "Message cannot exceed " . MAX_MESSAGE_LENGTH . " characters";
            }

            if (empty($errors)) {
                // Prepare email
                $email_content = "Name: $name\n";
                $email_content .= "Email: $email\n\n";
                $email_content .= "Message:\n$message\n";

                $headers = [
                    'From' => "$name <$email>",
                    'Reply-To' => $email,
                    'X-Mailer' => 'PHP/' . phpversion(),
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'MIME-Version' => '1.0'
                ];

                // Format headers
                $formatted_headers = '';
                foreach ($headers as $key => $value) {
                    $formatted_headers .= "$key: $value\r\n";
                }

                // Send email
                if (mail(RECIPIENT_EMAIL, EMAIL_SUBJECT, $email_content, $formatted_headers)) {
                    $success = true;
                    // Clear form fields
                    $name = $email = $message = '';
                } else {
                    $errors[] = "Oops! Something went wrong and we couldn't send your message.";
                }
            }
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
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
        .full {
            width: 90%;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            color: white;
            border: 15px solid white;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .full h3 {
            font-size: 2rem;
            margin: 0 0 2rem 0;
            color: black;
        }
        .lt, {
            padding: 1rem;
        }
        .form-control {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            color: white;
            border: 1px solid #555;
            border-radius: 4px;
        }
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        .btn-primary {
            background-color: black;
            color: white;
            border: 2px solid white;
            padding: 0.8rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #333;
        }
        .list-item {
            margin-bottom: 1.5rem;
            list-style-type: none;
            text-align: left;
            padding-left: 2rem;
            position: relative;
        }
        .list-item i {
            position: absolute;
            left: 0;
            top: 0.2rem;
            color: orange;
        }
        .list-item span {
            margin-left: 1.5rem;
            display: inline-block;
        }
        .list-item a {
            color: white;
            text-decoration: none;
        }
        .list-item a:hover {
            text-decoration: underline;
            color: var(--secondary);
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .honeypot {
            position: absolute;
            left: -9999px;
        }
      /* Footer Styles */
        footer {
            background-color: var(--primary);
            color: white;
            padding: 1.5rem;
            text-align: center;
            margin-top: 3rem;
            position: relative;
            width: 100%;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }

        footer::before {
            content: '';
            display: block;
            height: 2px;
            background: linear-gradient(90deg, transparent, orange, transparent);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
        }

        #year {
            font-weight: bold;
            color: orange;
        }

        /* Ensure footer stays at bottom */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        section {
            flex: 1;
        }
      
        /* Responsive Layout */
        @media (min-width: 768px) {
            .full {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
            }
            .lt {
                width: 60%;
            }
            .rt {
                width: 35%;
            }
        }
    </style>
</head>
<body>
    <section id="last">
                    <header>
                <div class="logo">Virtual Study Group Platform</div>
                <nav>
                    <a href="index.php">Home Page</a>
                    <a href="about.php">About</a>
                    <a href="contact.php">Contact</a>
                    <a href="login.php" class="btn">Login </a>
                    <a href="register.php" class="btn">Register</a>
                </nav>
            </header>
        <!-- heading -->
        <div class="full">
            <h3>Drop a Message</h3>
            <div class="lt">
                <!-- form starting -->
                <form class="form-horizontal" method="post" action="">
                    <?php if ($success): ?>
                        <div class="alert alert-success">Thank you for your message! We'll get back to you soon.</div>
                    <?php elseif (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Honeypot field -->
                    <div class="honeypot">
                        <label>Leave this field empty</label>
                        <input type="text" name="website">
                    </div>

                    <div class="form-group">
                        <div class="col-sm-12">
                            <!-- name -->
                            <input type="text" class="form-control" id="name" placeholder="NAME" name="name" 
                                   value="<?php echo htmlspecialchars($name); ?>" required minlength="2" maxlength="100" />
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-12">
                            <!-- email -->
                            <input type="email" class="form-control" id="email" placeholder="EMAIL" name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>" required />
                        </div>
                    </div>

                    <!-- message -->
                    <textarea class="form-control" rows="10" placeholder="MESSAGE" name="message" 
                              required minlength="<?php echo MIN_MESSAGE_LENGTH; ?>" 
                              maxlength="<?php echo MAX_MESSAGE_LENGTH; ?>"><?php echo htmlspecialchars($message); ?></textarea>

                    <button class="btn btn-primary send-button" id="submit" type="submit" value="SEND">
                        <i class="fa fa-paper-plane"></i>
                        <span class="send-text">SEND</span>
                    </button>
                </form>
                <!-- end of form -->
            </div>
        </div>
    </section>
    <footer>
        <div class="container">
            &copy; Virtual Study Group Platform <span id="year"></span> | Created by AHMED ALI
        </div>
    </footer>

    <script>
        // Auto-update copyright year
        document.getElementById('year').textContent = new Date().getFullYear();
        
        // Client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const message = document.querySelector('textarea').value.trim();
            
            if (name.length < 2) {
                alert('Name must be at least 2 characters');
                e.preventDefault();
                return false;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address');
                e.preventDefault();
                return false;
            }
            
            if (message.length < <?php echo MIN_MESSAGE_LENGTH; ?>) {
                alert('Message must be at least <?php echo MIN_MESSAGE_LENGTH; ?> characters');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>