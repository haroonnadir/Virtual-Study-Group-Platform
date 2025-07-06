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

// Check if user is a student
if ($current_user['role'] !== 'students') {
    header("Location: ../login.php");
    exit();
}

// Get all upcoming study sessions for groups the student is in
$upcoming_sessions = [];
$stmt = $conn->prepare("SELECT ss.*, sg.name AS group_name 
                       FROM study_sessions ss
                       JOIN study_groups sg ON ss.group_id = sg.id
                       WHERE ss.group_id IN (
                           SELECT group_id FROM group_members WHERE user_id = ?
                       )
                       AND ss.session_datetime >= NOW()
                       ORDER BY ss.session_datetime ASC");
$stmt->bind_param("i", $current_user['id']);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_sessions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get reminder status for each session
foreach ($upcoming_sessions as &$session) {
    $stmt = $conn->prepare("SELECT reminder_sent FROM session_reminders 
                           WHERE session_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $session['id'], $current_user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $reminder = $result->fetch_assoc();
    $session['reminder_status'] = $reminder ? $reminder['reminder_sent'] : 0;
    $stmt->close();
}
unset($session); // Break the reference

// Handle reminder toggle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_reminder'])) {
    $session_id = $_POST['session_id'];
    
    // Check if the user is in this session's group
    $stmt = $conn->prepare("SELECT 1 FROM group_members 
                           WHERE user_id = ? AND group_id IN (
                               SELECT group_id FROM study_sessions WHERE id = ?
                           )");
    $stmt->bind_param("ii", $current_user['id'], $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Check if reminder exists
        $stmt = $conn->prepare("SELECT id FROM session_reminders 
                               WHERE session_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $session_id, $current_user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing reminder
            $stmt = $conn->prepare("UPDATE session_reminders 
                                   SET reminder_sent = NOT reminder_sent 
                                   WHERE session_id = ? AND user_id = ?");
        } else {
            // Create new reminder
            $stmt = $conn->prepare("INSERT INTO session_reminders 
                                   (session_id, user_id, reminder_sent) 
                                   VALUES (?, ?, 1)");
        }
        $stmt->bind_param("ii", $session_id, $current_user['id']);
        $stmt->execute();
    }
    $stmt->close();
    
    // Refresh the page to show updated status
    header("Location: students_notifications_schedule.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications & Schedule</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f7f7fb;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        .main-content {
            flex: 1;
            padding: 40px;
        }
        .session-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .session-card:hover {
            transform: translateY(-5px);
        }
        .reminder-toggle {
            cursor: pointer;
        }
        .reminder-active {
            color: #28a745;
        }
        .reminder-inactive {
            color: #6c757d;
        }
        .session-time {
            font-weight: bold;
            color: #2c3e50;
        }
        .group-name {
            color: #3f3d99;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar-->
        <!-- Main Content -->
        <div class="main-content">
            <div style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h2 style="margin: 0; color: #333; font-size: 1.5rem;">
                    <i class="fas fa-calendar-alt" style="margin-right: 10px; color: #007bff;"></i> 
                    Study Session Schedule
                    </h2>
                    <a href="./students_dashboard.php" 
                    style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-family: Arial, sans-serif; transition: background-color 0.3s ease;" 
                    onmouseover="this.style.backgroundColor='#0056b3'" 
                    onmouseout="this.style.backgroundColor='#007bff'">
                    Go Back to Dashboard
                    </a>
                </div>
                <p style="margin: 0; color: #6c757d; font-style: italic; font-size: 0.9rem;">
                    View and manage your upcoming study sessions
                </p>
            </div>
            <div class="row mt-4">
                <div class="col-md-8">
                    <?php if (empty($upcoming_sessions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You don't have any upcoming study sessions.
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="card session-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title"><?= htmlspecialchars($session['title']) ?></h5>
                                            <p class="card-text">
                                                <span class="group-name"><?= htmlspecialchars($session['group_name']) ?></span> •
                                                <span class="session-time">
                                                    <?= date('M j, Y g:i A', strtotime($session['session_datetime'])) ?>
                                                    (<?= $session['duration'] ? $session['duration'] . ' mins' : 'Duration not specified' ?>)
                                                </span>
                                            </p>
                                            <?php if ($session['description']): ?>
                                                <p class="card-text"><?= htmlspecialchars($session['description']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($session['meeting_link']): ?>
                                                <a href="<?= htmlspecialchars($session['meeting_link']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-video"></i> Join Meeting
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" class="reminder-toggle">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <input type="hidden" name="toggle_reminder" value="1">
                                            <button type="submit" class="btn btn-link p-0 border-0 bg-transparent">
                                                <i class="fas fa-bell <?= $session['reminder_status'] ? 'reminder-active' : 'reminder-inactive' ?>" 
                                                   data-bs-toggle="tooltip" 
                                                   title="<?= $session['reminder_status'] ? 'Reminder is ON' : 'Reminder is OFF' ?>"
                                                   style="font-size: 1.5rem;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-info-circle"></i> Reminder Information
                        </div>
                        <div class="card-body">
                            <p>When reminders are enabled, you'll receive notifications:</p>
                            <ul>
                                <li>24 hours before the session</li>
                                <li>1 hour before the session</li>
                                <li>When the session starts</li>
                            </ul>
                            <p class="text-muted small">Notifications will be sent via email and in-app alerts.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>