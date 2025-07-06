<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get group ID from URL
if (!isset($_GET['id'])) {
    header("Location: admin_view_allgroups.php");
    exit();
}
$group_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

// Verify user is member of this group
$stmt = $conn->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "You are not a member of this group!";
    header("Location: admin_view_allgroups.php");
    exit();
}

// Get group info
$stmt = $conn->prepare("SELECT name FROM study_groups WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

// Get all sessions for this group, ordered by date
$stmt = $conn->prepare("
    SELECT * FROM study_sessions 
    WHERE group_id = ? 
    ORDER BY session_datetime ASC
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Separate upcoming and past sessions
$upcoming_sessions = [];
$past_sessions = [];
$now = new DateTime();

foreach ($sessions as $session) {
    $session_time = new DateTime($session['session_datetime']);
    if ($session_time > $now) {
        $upcoming_sessions[] = $session;
    } else {
        $past_sessions[] = $session;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> Schedule - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .session-card {
            transition: transform 0.2s;
            margin-bottom: 1rem;
        }
        .session-card:hover {
            transform: translateY(-3px);
        }
        .upcoming-session {
            border-left: 4px solid #0d6efd;
        }
        .past-session {
            border-left: 4px solid #6c757d;
            opacity: 0.8;
        }
        .session-title {
            font-weight: 600;
        }
        .session-description {
            color: #495057;
        }
        .session-time {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .session-link {
            text-decoration: none;
            color: inherit;
        }
        .session-link:hover {
            text-decoration: none;
        }
        .section-title {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-calendar-week"></i> 
                <?= htmlspecialchars($group['name']) ?> Schedule
            </h2>
            <a href="admin_view_allgroups.php?id=<?= $group_id ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Group
            </a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Upcoming Sessions -->
        <div class="mb-5">
            <h4 class="section-title">
                <i class="bi bi-calendar-plus"></i> Upcoming Sessions
                <span class="badge bg-primary ms-2"><?= count($upcoming_sessions) ?></span>
            </h4>
            
            <?php if (empty($upcoming_sessions)): ?>
                <div class="alert alert-info">
                    No upcoming sessions scheduled.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($upcoming_sessions as $session): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card session-card upcoming-session h-100">
                                <div class="card-body">
                                    <h5 class="session-title card-title"><?= htmlspecialchars($session['title']) ?></h5>
                                    <p class="session-time card-subtitle mb-2 text-muted">
                                        <i class="bi bi-clock"></i> 
                                        <?= date('M d, Y h:i A', strtotime($session['session_datetime'])) ?>
                                    </p>
                                    <?php if ($session['description']): ?>
                                        <p class="session-description card-text"><?= htmlspecialchars($session['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['meeting_link']): ?>
                                        <a href="<?= htmlspecialchars($session['meeting_link']) ?>" 
                                           class="btn btn-primary btn-sm mt-2" 
                                           target="_blank">
                                            <i class="bi bi-camera-video"></i> Join Session
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Sessions -->
        <div class="mb-3">
            <h4 class="section-title">
                <i class="bi bi-calendar-check"></i> Past Sessions
                <span class="badge bg-secondary ms-2"><?= count($past_sessions) ?></span>
            </h4>
            
            <?php if (empty($past_sessions)): ?>
                <div class="alert alert-info">
                    No past sessions recorded.
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($past_sessions as $session): ?>
                        <div class="list-group-item past-session">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="session-title mb-1"><?= htmlspecialchars($session['title']) ?></h6>
                                    <small class="session-time text-muted">
                                        <i class="bi bi-clock"></i> 
                                        <?= date('M d, Y h:i A', strtotime($session['session_datetime'])) ?>
                                    </small>
                                </div>
                                <?php if ($session['meeting_link']): ?>
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-link-45deg"></i> Link was available
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>