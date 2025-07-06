<?php
session_start();
include 'db_connect.php'; // Database connection


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Initialize error array
    $errors = [];
    
    // Validate and sanitize inputs
    $fields = [
        'name' => FILTER_SANITIZE_STRING,
        'cnic' => FILTER_SANITIZE_STRING,
        'email' => FILTER_SANITIZE_EMAIL,
        'password' => FILTER_UNSAFE_RAW,
        'confirm_password' => FILTER_UNSAFE_RAW,
        'phone' => FILTER_SANITIZE_STRING,
        'age' => [
            'filter' => FILTER_VALIDATE_INT,
            'options' => ['min_range' => 13, 'max_range' => 100]
        ],
        'address' => FILTER_SANITIZE_STRING,
        'town' => FILTER_SANITIZE_STRING,
        'region' => FILTER_SANITIZE_STRING,
        'postcode' => FILTER_SANITIZE_STRING,
        'country' => FILTER_SANITIZE_STRING
    ];
    
    $input = filter_input_array(INPUT_POST, $fields);
    
    // Validate required fields
    foreach ($input as $key => $value) {
        if (empty($value) && $key !== 'town' && $key !== 'region') {
            $errors[] = ucfirst($key) . " is required.";
        }
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Validate password strength
    if (strlen($input['password']) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    // Check passwords match
    if ($input['password'] !== $input['confirm_password']) {
        $errors[] = "Passwords do not match.";
    }
    
    // Validate CNIC format (assuming Pakistani CNIC)
    if (!preg_match('/^[0-9]{5}-[0-9]{7}-[0-9]$/', $input['cnic'])) {
        $errors[] = "CNIC must be in the format 12345-1234567-1";
    }
    
    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $input['email']);
    $checkEmail->execute();
    $checkEmail->store_result();
    
    if ($checkEmail->num_rows > 0) {
        $errors[] = "Email is already registered.";
    }
    $checkEmail->close();
    
    // Check if CNIC already exists
    $checkCNIC = $conn->prepare("SELECT id FROM users WHERE cnic = ?");
    $checkCNIC->bind_param("s", $input['cnic']);
    $checkCNIC->execute();
    $checkCNIC->store_result();
    
    if ($checkCNIC->num_rows > 0) {
        $errors[] = "CNIC is already registered.";
    }
    $checkCNIC->close();
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($input['password'], PASSWORD_BCRYPT);
        
        // Insert user
        $insert = $conn->prepare("INSERT INTO users (name, cnic, email, password, phone, age, address, town, region, postcode, country, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("sssssisssss", 
            $input['name'],
            $input['cnic'],
            $input['email'],
            $hashed_password,
            $input['phone'],
            $input['age'],
            $input['address'],
            $input['town'],
            $input['region'],
            $input['postcode'],
            $input['country']
        );
        
        if ($insert->execute()) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $insert->close();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Virtual Study Group Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .registration-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            border-bottom: 2px solid #1abc9c;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength span {
            display: block;
            height: 100%;
            width: 0%;
            background: transparent;
            transition: width 0.3s, background 0.3s;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        
        .btn-register {
            background-color: #1abc9c;
            border-color: #1abc9c;
            padding: 0.5rem 2rem;
            font-weight: 600;
        }
        
        .btn-register:hover {
            background-color:rgb(24, 156, 189);
            border-color:rgb(18, 89, 170);
        }
        
        footer {
            background-color: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .registration-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Virtual Study Group Platform</div>
        <nav>
            <a href="index.php">Home</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="login.php" class="btn">Login</a>
        </nav>
    </header>
    
    <div class="container">
        <div class="registration-container">
            <h2 class="text-center mb-4">Create Your Study Group Account</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form id="registrationForm" action="register.php" method="POST" novalidate>
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Personal Information</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($input['name'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter your full name.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cnic" class="form-label">CNIC (Format: 12345-1234567-1) *</label>
                            <input type="text" class="form-control" id="cnic" name="cnic" 
                                   value="<?php echo htmlspecialchars($input['cnic'] ?? ''); ?>" 
                                   pattern="[0-9]{5}-[0-9]{7}-[0-9]{1}" required>
                            <div class="invalid-feedback">Please enter a valid CNIC in the format 12345-1234567-1.</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($input['email'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($input['phone'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter your phone number.</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="age" class="form-label">Age *</label>
                            <input type="number" class="form-control" id="age" name="age" 
                                   value="<?php echo htmlspecialchars($input['age'] ?? ''); ?>" min="13" max="100" required>
                            <div class="invalid-feedback">Please enter your age (must be between 13-100).</div>
                        </div>
                    </div>
                </div>
                
                <!-- Account Security Section -->
                <div class="form-section">
                    <h3 class="section-title">Account Security</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="password-strength">
                                <span id="passwordStrength"></span>
                            </div>
                            <small class="text-muted">Minimum 8 characters with at least one letter and one number</small>
                            <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div id="passwordMatch" class="error-message"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Address Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Address Information</h3>
                    <div class="mb-3">
                        <label for="address" class="form-label">Street Address *</label>
                        <input type="text" class="form-control" id="address" name="address" 
                               value="<?php echo htmlspecialchars($input['address'] ?? ''); ?>" required>
                        <div class="invalid-feedback">Please enter your address.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="town" class="form-label">Town/City</label>
                            <input type="text" class="form-control" id="town" name="town" 
                                   value="<?php echo htmlspecialchars($input['town'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="region" class="form-label">Region/State</label>
                            <input type="text" class="form-control" id="region" name="region" 
                                   value="<?php echo htmlspecialchars($input['region'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="postcode" class="form-label">Postal Code</label>
                            <input type="text" class="form-control" id="postcode" name="postcode" 
                                   value="<?php echo htmlspecialchars($input['postcode'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="country" class="form-label">Country *</label>
                            <input type="text" class="form-control" id="country" name="country" 
                                   value="<?php echo htmlspecialchars($input['country'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter your country.</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a> *
                    </label>
                    <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-register">Create Account</button>
                </div>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Log in here</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <footer>
        &copy; Virtual Study Group Platform <span id="year"></span> | Created by AHMED ALI
    </footer>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Character type checks
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength bar
            let width = (strength / 5) * 100;
            strengthBar.style.width = width + '%';
            
            // Update color
            if (strength <= 2) {
                strengthBar.style.backgroundColor = '#dc3545'; // Red
            } else if (strength <= 4) {
                strengthBar.style.backgroundColor = '#ffc107'; // Yellow
            } else {
                strengthBar.style.backgroundColor = '#28a745'; // Green
            }
        });
        
        // Password match check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchMessage = document.getElementById('passwordMatch');
            
            if (confirmPassword && password !== confirmPassword) {
                matchMessage.textContent = 'Passwords do not match';
            } else {
                matchMessage.textContent = '';
            }
        });
        
        // Form validation
        (function() {
            'use strict';
            
            const form = document.getElementById('registrationForm');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        })();
        // Auto-update copyright year
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</body>
</html>