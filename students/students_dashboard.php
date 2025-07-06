<?php
session_start();
// Include database connection
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get current user data from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found, log them out
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$current_user = $result->fetch_assoc();
$stmt->close();

// Check if user is a student, if not redirect to appropriate dashboard
if ($current_user['role'] !== 'students') {
    if ($current_user['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: ../login.php");
    }
    exit();
}

// Determine account status message
$status_message = '';
$status_class = '';
$is_restricted = false;

if ($current_user['status'] === 'Banned') {
    $status_message = "Your account has been blocked by admin. Please contact support for more information.";
    $status_class = "danger";
    $is_restricted = true;
} elseif ($current_user['status'] === 'Pending') {
    $status_message = "Your account is pending approval from admin. You'll have full access once approved.";
    $status_class = "warning";
    $is_restricted = true;
} elseif (!$current_user['is_active']) {
    $status_message = "Your account is currently inactive. Please contact admin to reactivate.";
    $status_class = "warning";
    $is_restricted = true;
} else {
    $status_message = "Your account is active and you have full access to all features.";
    $status_class = "success";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #f7f7fb;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            flex-shrink: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar h4 {
            font-weight: bold;
            margin-bottom: 30px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 0;
            display: flex;
            align-items: center;
        }

        .sidebar a:hover:not(.disabled-link) {
            background-color: #3f3d99;
            padding-left: 10px;
            transition: 0.3s;
        }

        .sidebar i {
            margin-right: 10px;
        }

        .main-content {
            flex-grow: 1;
            padding: 40px;
        }

        .dashboard-tiles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }

        .tile {
            background-color: #2c3e50;
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            transition: 0.3s;
            text-decoration: none;
        }

        .tile:hover:not(.disabled-tile) {
            background-color: #3f3d99;
            cursor: pointer;
        }

        .tile i {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .logout-btn {
            margin-top: auto;
            background-color: #dc3545;
            border: none;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }

        .logout-btn:hover {
            background-color: #bb2d3b;
        }
        
        .status-alert {
            margin-bottom: 30px;
        }
        
        .disabled-content {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 50px;
            display: inline-block;
        }
        .Pending {
            background-color: #ffc107;
            color: #000;
        }
        .Active {
            background-color: #28a745;
            color: #fff;
        }
        .Banned {
            background-color: #dc3545;
            color: #fff;
        }
        
        .disabled-link {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .disabled-tile {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4><i class="fas fa-graduation-cap me-2"></i> Virtual Study Group Platform</h4>
        <p><i class="fas fa-user-circle"></i> <?= htmlspecialchars($current_user['name']) ?></p>
        <p>
            <i class="fas fa-info-circle"></i> Status: 
            <span class="status-badge <?= htmlspecialchars($current_user['status']) ?>">
                <?= htmlspecialchars($current_user['status']) ?>
                <?php if ($current_user['status'] === 'Active' && !$current_user['is_active']): ?>
                    (Inactive)
                <?php endif; ?>
            </span>
        </p>
        <hr>
        <a href="students_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="studnets_join_groups.php" class="<?= $is_restricted ? 'disabled-link' : '' ?>">
            <i class="fas fa-user-plus"></i> Join Groups
        </a>
        <a href="student_view_allgroups.php" class="<?= $is_restricted ? 'disabled-link' : '' ?>">
            <i class="fas fa-users"></i> View Groups
        </a>
        <a href="student_group_resources.php" class="<?= $is_restricted ? 'disabled-link' : '' ?>">
            <i class="fas fa-book"></i> Resources
        </a>
        <a href="students_notifications_schedule.php" class="<?= $is_restricted ? 'disabled-link' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Notifications Schedule
        </a>
        <a href="Students_manage_profile.php"><i class="fas fa-user-cog"></i> Manage Profile</a>
        <a href="../logout.php" class="logout-btn mt-4 text-white"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Dashboard -->
    <div class="main-content">
        <h2 class="mb-4">Dashboard</h2>
        
        <!-- Status Alert -->
        <div class="alert alert-<?= $status_class ?> status-alert" role="alert">
            <i class="fas <?= 
                $current_user['status'] === 'Banned' ? 'fa-ban' : 
                ($current_user['status'] === 'Pending' ? 'fa-clock' : 
                (!$current_user['is_active'] ? 'fa-exclamation-triangle' : 'fa-check-circle')) 
            ?>"></i> 
            <?= $status_message ?>
        </div>
        
        <?php if ($is_restricted): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You can still update your profile information.
            </div>
        <?php endif; ?>
        
        <?php if ($is_restricted): ?>
            <div class="disabled-content">
                <div class="dashboard-tiles">
                    <div class="tile disabled-tile">
                        <i class="fas fa-user-plus"></i><br>Join Groups
                    </div>
                    <div class="tile disabled-tile">
                        <i class="fas fa-users"></i><br>View Groups
                    </div>
                    <div class="tile disabled-tile">
                        <i class="fas fa-book"></i><br>Resources
                    </div>
                    <div class="tile disabled-tile">
                        <i class="fas fa-calendar-alt"></i><br>Schedule
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Active User Content -->
            <div class="dashboard-tiles">
                <a href="groups.php?action=join" class="tile">
                    <i class="fas fa-user-plus"></i><br>Join Groups
                </a>
                <a href="groups.php" class="tile">
                    <i class="fas fa-users"></i><br>View Groups
                </a>
                <a href="student_group_resources.php" class="tile">
                    <i class="fas fa-book"></i><br>Resources
                </a>
                <a href="students_notifications_schedule.php" class="tile">
                    <i class="fas fa-calendar-alt"></i><br> Notifications Schedule
                </a>
            </div>
            
            <!-- Quick Stats Section for Active Users -->
            <div class="mt-5">
                <h4><i class="fas fa-chart-line"></i> Quick Stats</h4>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Groups Joined</h5>
                                <p class="card-text display-6">
                                    <?php
                                    // Get groups count
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM group_members WHERE user_id = ?");
                                    $stmt->bind_param("i", $current_user['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    echo $result->fetch_row()[0];
                                    $stmt->close();
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Upcoming Sessions</h5>
                                <p class="card-text display-6">
                                    <?php
                                    // Get upcoming sessions count
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM study_sessions 
                                        WHERE group_id IN (
                                            SELECT group_id FROM group_members WHERE user_id = ?
                                        ) AND session_datetime >= NOW()");
                                    $stmt->bind_param("i", $current_user['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    echo $result->fetch_row()[0];
                                    $stmt->close();
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body">
                                <h5 class="card-title">New Messages</h5>
                                <p class="card-text display-6">
                                    <?php
                                    // Get count of unread messages in groups the user belongs to
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM group_messages gm
                                                        JOIN group_members gmem ON gm.group_id = gmem.group_id
                                                        WHERE gmem.user_id = ? AND gm.is_read = 0");
                                    $stmt->bind_param("i", $current_user['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    echo $result->fetch_row()[0] ?? 0;
                                    $stmt->close();
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>