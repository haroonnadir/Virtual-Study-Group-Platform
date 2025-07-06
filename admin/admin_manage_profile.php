<?php
session_start();
include '../db_connect.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get the logged-in student's ID
$student_id = $_SESSION['user_id'];

// Fetch student data using prepared statement
$sql = "SELECT id, name, cnic, email, phone, age, address, town, region, postcode, country FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows == 1) {
    $student = $result->fetch_assoc();
} else {
    error_log("Database error: " . $conn->error);
    die("Student not found or database error.");
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token";
    }
    
    // Validate and sanitize inputs
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $age = intval($_POST['age']);
    $address = trim($_POST['address']);
    $town = trim($_POST['town']);
    $region = trim($_POST['region']);
    $postcode = trim($_POST['postcode']);
    $country = trim($_POST['country']);
    
    // Basic validation
    if (empty($name)) $errors[] = "Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (!preg_match('/^[0-9]{10,15}$/', $phone)) $errors[] = "Invalid phone number format";
    if ($age < 13 || $age > 100) $errors[] = "Age must be between 13-100";
    
    // If no errors, update the profile
    if (empty($errors)) {
        $update_sql = "UPDATE users SET 
                      name = ?,
                      email = ?,
                      phone = ?,
                      age = ?,
                      address = ?,
                      town = ?,
                      region = ?,
                      postcode = ?,
                      country = ?
                      WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssisssssi", 
            $name, $email, $phone, $age, $address, 
            $town, $region, $postcode, $country, $student_id);
        
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh student data
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
        } else {
            $errors[] = "Error updating profile: " . $conn->error;
        }
    }
}

// Highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage My Profile | Virtual Study Group Platform</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            height: 100%;
            padding: 20px;
        }
        .sidebar h4 {
            font-weight: bold;
            margin-bottom: 30px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 0;
            display: block;
        }
        .sidebar a:hover {
            background-color: #3f3d99;
            padding-left: 10px;
            transition: 0.3s;
        }
        .sidebar i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .profile-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        .profile-section-title {
            color: #2c3e50;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .detail-item {
            display: flex;
            margin-bottom: 15px;
        }
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        .detail-value {
            flex: 1;
        }
        .form-label {
            font-weight: bold;
        }
        .logout-btn {
            background-color:rgb(206, 46, 46);
            color: white;
            text-decoration: none;
            padding: 10px 0;
            display: block;
            margin-top: 20px;
        }
        .logout-btn:hover {
            color: #ff6b6b;
        }
        .read-only-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4><i class="fas fa-graduation-cap me-2"></i> Virtual Study Group</h4>
        <p><i class="fas fa-user-circle"></i> <?= htmlspecialchars($student['name']) ?></p>
        <hr>
        <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="groups.php?action=join"><i class="fas fa-user-plus"></i> Join Groups</a>
        <a href="groups.php"><i class="fas fa-users"></i> View Groups</a>
        <a href="resources.php"><i class="fas fa-book"></i> Resources</a>
        <a href="communication.php"><i class="fas fa-comments"></i> Communication</a>
        <a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule</a>
        <a href="admin_manage_profile.php" class="active"><i class="fas fa-user-cog"></i> Manage Profile</a>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <div>
                <a href="students_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="admin_manage_profile.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="profile-card">
                <h2 class="profile-section-title"><i class="fas fa-id-card"></i> Personal Information</h2>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= htmlspecialchars($student['name']) ?>" required maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($student['email']) ?>" required maxlength="100">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?= htmlspecialchars($student['phone']) ?>" pattern="[0-9]{10,15}" 
                               title="10-15 digit phone number">
                    </div>
                    <div class="col-md-6">
                        <label for="age" class="form-label">Age</label>
                        <input type="number" class="form-control" id="age" name="age" 
                               value="<?= htmlspecialchars($student['age']) ?>" min="13" max="100">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">CNIC (Not editable)</label>
                    <div class="read-only-info">
                        <?= htmlspecialchars($student['cnic']) ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-card">
                <h2 class="profile-section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h2>
                
                <div class="mb-3">
                    <label for="address" class="form-label">Street Address</label>
                    <input type="text" class="form-control" id="address" name="address" 
                           value="<?= htmlspecialchars($student['address']) ?>" maxlength="100">
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="town" class="form-label">Town/City</label>
                        <input type="text" class="form-control" id="town" name="town" 
                               value="<?= htmlspecialchars($student['town']) ?>" maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label for="region" class="form-label">Region/State</label>
                        <input type="text" class="form-control" id="region" name="region" 
                               value="<?= htmlspecialchars($student['region']) ?>" maxlength="50">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="postcode" class="form-label">Postal Code</label>
                        <input type="text" class="form-control" id="postcode" name="postcode" 
                               value="<?= htmlspecialchars($student['postcode']) ?>" maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" class="form-control" id="country" name="country" 
                               value="<?= htmlspecialchars($student['country']) ?>" maxlength="50">
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>