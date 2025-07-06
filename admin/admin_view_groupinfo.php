<?php
session_start();
include '../db_connect.php';

// Verify admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get group ID from URL
if (!isset($_GET['id'])) {
    header("Location: admin_manage_groups.php");
    exit();
}

$group_id = (int)$_GET['id'];

// Get group info
$stmt = $conn->prepare("SELECT * FROM study_groups WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
    $_SESSION['error'] = "Group not found!";
    header("Location: admin_manage_groups.php");
    exit();
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $session_datetime = $conn->real_escape_string($_POST['session_datetime']);
    $meeting_link = $conn->real_escape_string($_POST['meeting_link']);
    $duration = (int)$_POST['duration'];
    
    // Insert new session
    $stmt = $conn->prepare("INSERT INTO sessions (group_id, title, description, session_datetime, duration, meeting_link, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssisi", $group_id, $title, $description, $session_datetime, $duration, $meeting_link, $_SESSION['user_id']);
    $stmt->execute();
    
    // Get all group members to send notifications
    $stmt = $conn->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Create notifications for each member
    $message = "New session scheduled: " . $title . " on " . date('M d, Y h:i A', strtotime($session_datetime));
    $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, group_id, message) VALUES (?, ?, ?)");
    
    foreach ($members as $member) {
        $notification_stmt->bind_param("iis", $member['user_id'], $group_id, $message);
        $notification_stmt->execute();
    }
    
    $_SESSION['message'] = "Session created and members notified!";
    header("Location: admin_view_groupinfo.php?id=$group_id");
    exit();
}

// Get group members
$members = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.profile_picture, gm.role, gm.joined_at
    FROM users u
    JOIN group_members gm ON u.id = gm.user_id
    WHERE gm.group_id = ?
    ORDER BY 
        CASE gm.role 
            WHEN 'owner' THEN 1
            WHEN 'moderator' THEN 2
            ELSE 3
        END,
        u.name
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

// Get upcoming sessions
$sessions = [];
$stmt = $conn->prepare("
    SELECT * FROM study_sessions 
    WHERE group_id = ? AND session_datetime > NOW()
    ORDER BY session_datetime ASC
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Group: <?= htmlspecialchars($group['name']) ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .owner-badge {
            background-color: #fd7e14;
        }
        .moderator-badge {
            background-color: #0dcaf0;
        }
        .member-badge {
            background-color: #6c757d;
        }
        .session-card {
            transition: transform 0.2s;
        }
        .session-card:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-people-fill"></i> Manage Group: <?= htmlspecialchars($group['name']) ?>
            </h2>
            <a href="admin_view_allgroups.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Groups
            </a>
        </div>
        
        <div class="row">
            <!-- Group Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Group Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Subject:</strong> <?= htmlspecialchars($group['subject']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Description:</strong> <?= htmlspecialchars($group['description']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Status:</strong> <?= $group['is_private'] ? 'Private' : 'Public' ?>
                            </li>
                            <?php if ($group['is_private']): ?>
                                <li class="list-group-item">
                                    <strong>Join Code:</strong> <?= htmlspecialchars($group['join_code']) ?>
                                </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <strong>Created:</strong> <?= date('M d, Y', strtotime($group['created_at'])) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Group Members -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Group Members</h5>
                        <span class="badge bg-primary"><?= count($members) ?> Members</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($members as $member): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <?php if ($member['profile_picture']): ?>
                                            <img src="<?= htmlspecialchars($member['profile_picture']) ?>" alt="Profile" class="rounded-circle me-3" width="50" height="50">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($member['name']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($member['email']) ?></small>
                                                </div>
                                                <form method="POST" class="d-flex align-items-center">
                                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                    <select name="new_role" class="form-select form-select-sm me-2" style="width: auto;">
                                                        <option value="owner" <?= $member['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                                                        <option value="moderator" <?= $member['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                                        <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                                    </select>
                                                    <button type="submit" name="change_role" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-check"></i> Update
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="mt-2">
                                                <span class="badge <?= 
                                                    $member['role'] === 'owner' ? 'owner-badge' : 
                                                    ($member['role'] === 'moderator' ? 'moderator-badge' : 'member-badge') 
                                                ?>">
                                                    <?= ucfirst($member['role']) ?>
                                                </span>
                                                <small class="text-muted ms-2">Joined <?= date('M d, Y', strtotime($member['joined_at'])) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Sessions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sessions)): ?>
                            <div class="alert alert-info">
                                No upcoming sessions scheduled.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($sessions as $session): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card session-card">
                                            <div class="card-body">
                                                <form method="POST">
                                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($session['title']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($session['description']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Date & Time</label>
                                                        <input type="datetime-local" name="session_datetime" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($session['session_datetime'])) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Meeting Link (optional)</label>
                                                        <input type="url" name="meeting_link" class="form-control" value="<?= htmlspecialchars($session['meeting_link']) ?>" placeholder="https://meet.google.com/abc-xyz">
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <button type="submit" name="update_session" class="btn btn-primary">
                                                            <i class="bi bi-save"></i> Update Session
                                                        </button>
                                                        <a href="admin_delete_session.php?group_id=<?= $group_id ?>&session_id=<?= $session['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this session?')">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="card-footer text-muted">
                                                Created: <?= date('M d, Y', strtotime($session['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                                <i class="bi bi-plus-circle"></i> Add New Session
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Session Modal -->
    <div class="modal fade" id="newSessionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="admin_add_session.php">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date & Time</label>
                            <input type="datetime-local" name="session_datetime" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meeting Link (optional)</label>
                            <input type="url" name="meeting_link" class="form-control" placeholder="https://meet.google.com/abc-xyz">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activate tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>