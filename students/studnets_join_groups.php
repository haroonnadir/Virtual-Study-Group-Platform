<?php
session_start();
include '../db_connect.php';

// Get group ID from URL
if (!isset($_GET['id'])) {
    header("Location: student_view_allgroups.php");
    exit();
}

$group_id = (int)$_GET['id']; // Cast to integer for safety

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];

// Verify user is member of this group (MySQLi version)
$stmt = $conn->prepare("
    SELECT sg.*, gm.role 
    FROM study_groups sg
    JOIN group_members gm ON sg.id = gm.group_id
    WHERE sg.id = ? AND gm.user_id = ?
");
$stmt->bind_param("ii", $group_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$group = $result->fetch_assoc();

if (!$group) {
    $_SESSION['error'] = "You are not a member of this group or it doesn't exist!";
    header("Location: student_view_allgroups.php");
    exit();
}

// Handle new discussion post (MySQLi version)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_discussion'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    
    $stmt = $conn->prepare("INSERT INTO discussions (group_id, user_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group_id, $user_id, $title, $content);
    $stmt->execute();
    
    $_SESSION['message'] = "Discussion posted successfully!";
    header("Location: student_group.php?id=$group_id");
    exit();
}

// Handle new reply (MySQLi version)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_reply'])) {
    $discussion_id = (int)$_POST['discussion_id'];
    $content = $conn->real_escape_string($_POST['content']);
    
    // Verify discussion belongs to this group
    $stmt = $conn->prepare("SELECT * FROM discussions WHERE id = ? AND group_id = ?");
    $stmt->bind_param("ii", $discussion_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO replies (discussion_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $discussion_id, $user_id, $content);
        $stmt->execute();
        
        $_SESSION['message'] = "Reply posted successfully!";
    } else {
        $_SESSION['error'] = "Invalid discussion!";
    }
    
    header("Location: student_group.php?id=$group_id#discussion-$discussion_id");
    exit();
}

// Get group members (MySQLi version)
$members = [];
$result = $conn->query("
    SELECT u.id, u.name, u.profile_picture, gm.role, gm.joined_at
    FROM users u
    JOIN group_members gm ON u.id = gm.user_id
    WHERE gm.group_id = $group_id
    ORDER BY 
        CASE gm.role 
            WHEN 'owner' THEN 1
            WHEN 'moderator' THEN 2
            ELSE 3
        END,
        u.name
");
if ($result) {
    $members = $result->fetch_all(MYSQLI_ASSOC);
}

// Get discussions with reply counts (MySQLi version)
$discussions = [];
$result = $conn->query("
    SELECT d.*, u.name as author_name, u.profile_picture as author_pic,
           (SELECT COUNT(*) FROM replies r WHERE r.discussion_id = d.id) as reply_count
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    WHERE d.group_id = $group_id
    ORDER BY d.is_pinned DESC, d.posted_at DESC
");
if ($result) {
    $discussions = $result->fetch_all(MYSQLI_ASSOC);
}

// Get recent messages (MySQLi version)
$messages = [];
$result = $conn->query("
    SELECT m.*, u.name as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.group_id = $group_id
    ORDER BY m.created_at DESC
    LIMIT 5
");
if ($result) {
    $messages = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .discussion-card {
            transition: transform 0.2s;
        }
        .discussion-card:hover {
            transform: translateY(-3px);
        }
        .pinned-discussion {
            border-left: 4px solid #ffc107;
            background-color: rgba(255, 193, 7, 0.05);
        }
        .owner-badge {
            background-color: #fd7e14;
        }
        .moderator-badge {
            background-color: #0dcaf0;
        }
        .member-badge {
            background-color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'student_navbar.php'; ?>
    
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
        
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>
                        <i class="bi bi-people-fill"></i> <?= htmlspecialchars($group['name']) ?>
                        <small class="text-muted"><?= htmlspecialchars($group['subject']) ?></small>
                    </h2>
                    <span class="badge bg-primary">
                        <i class="bi bi-people"></i> <?= count($members) ?> Members
                    </span>
                </div>
                <p class="lead"><?= htmlspecialchars($group['description']) ?></p>
                
                <?php if ($group['role'] === 'owner' || $group['role'] === 'moderator'): ?>
                    <div class="btn-group mb-3">
                        <a href="student_group_settings.php?id=<?= $group_id ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-gear"></i> Group Settings
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <!-- Left sidebar with members -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Members</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($members as $member): ?>
                                <div class="list-group-item d-flex align-items-center">
                                    <?php if ($member['profile_picture']): ?>
                                        <img src="<?= htmlspecialchars($member['profile_picture']) ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <i class="bi bi-person text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong><?= htmlspecialchars($member['name']) ?></strong>
                                            <span class="badge <?= 
                                                $member['role'] === 'owner' ? 'owner-badge' : 
                                                ($member['role'] === 'moderator' ? 'moderator-badge' : 'member-badge') 
                                            ?>">
                                                <?= ucfirst($member['role']) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">Joined <?= date('M Y', strtotime($member['joined_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDiscussionModal">
                                <i class="bi bi-plus-circle"></i> New Discussion
                            </button>
                            <a href="student_group_resources.php?id=<?= $group_id ?>" class="btn btn-success">
                                <i class="bi bi-file-earmark"></i> Group Resources
                            </a>
                            <a href="student_group_schedule.php?id=<?= $group_id ?>" class="btn btn-info">
                                <i class="bi bi-calendar"></i> Study Schedule
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main content area -->
            <div class="col-lg-6 mb-4">
                <ul class="nav nav-tabs" id="groupTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="discussions-tab" data-bs-toggle="tab" data-bs-target="#discussions" type="button" role="tab">
                            <i class="bi bi-chat-left-text"></i> Discussions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab">
                            <i class="bi bi-envelope"></i> Messages
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="groupTabsContent">
                    <!-- Discussions Tab -->
                    <div class="tab-pane fade show active" id="discussions" role="tabpanel">
                        <?php if (empty($discussions)): ?>
                            <div class="alert alert-info mt-3">
                                No discussions yet. Start the first one!
                            </div>
                        <?php else: ?>
                            <?php foreach ($discussions as $discussion): ?>
                                <div class="card mt-3 discussion-card <?= $discussion['is_pinned'] ? 'pinned-discussion' : '' ?>">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start">
                                            <?php if ($discussion['author_pic']): ?>
                                                <img src="<?= htmlspecialchars($discussion['author_pic']) ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h5 class="card-title mb-1">
                                                        <?= htmlspecialchars($discussion['title']) ?>
                                                        <?php if ($discussion['is_pinned']): ?>
                                                            <i class="bi bi-pin-angle-fill text-warning" title="Pinned"></i>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <small class="text-muted"><?= date('M d, Y', strtotime($discussion['posted_at'])) ?></small>
                                                </div>
                                                <p class="card-text"><?= htmlspecialchars($discussion['content']) ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        By <?= htmlspecialchars($discussion['author_name']) ?>
                                                    </small>
                                                    <a href="#discussion-<?= $discussion['id'] ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse">
                                                        <i class="bi bi-chat"></i> <?= $discussion['reply_count'] ?> Replies
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Replies section -->
                                        <div class="collapse mt-3" id="discussion-<?= $discussion['id'] ?>">
                                            <?php 
                                            $replies = $pdo->query("
                                                SELECT r.*, u.name as author_name, u.profile_picture as author_pic
                                                FROM replies r
                                                JOIN users u ON r.user_id = u.id
                                                WHERE r.discussion_id = {$discussion['id']}
                                                ORDER BY r.replied_at
                                            ")->fetchAll();
                                            ?>
                                            
                                            <?php if (!empty($replies)): ?>
                                                <div class="ps-4 border-start">
                                                    <?php foreach ($replies as $reply): ?>
                                                        <div class="mb-3">
                                                            <div class="d-flex align-items-start">
                                                                <?php if ($reply['author_pic']): ?>
                                                                    <img src="<?= htmlspecialchars($reply['author_pic']) ?>" alt="Profile" class="rounded-circle me-2" width="32" height="32">
                                                                <?php else: ?>
                                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                                        <i class="bi bi-person text-white"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="flex-grow-1">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <strong><?= htmlspecialchars($reply['author_name']) ?></strong>
                                                                        <small class="text-muted"><?= date('M d, Y', strtotime($reply['replied_at'])) ?></small>
                                                                    </div>
                                                                    <p class="mb-0"><?= htmlspecialchars($reply['content']) ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Reply form -->
                                            <form method="POST" class="mt-2">
                                                <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                                                <div class="input-group">
                                                    <input type="text" name="content" class="form-control" placeholder="Write a reply..." required>
                                                    <button type="submit" name="post_reply" class="btn btn-primary">
                                                        <i class="bi bi-send"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Messages Tab -->
                    <div class="tab-pane fade" id="messages" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-envelope"></i> Group Messages</h5>
                                
                                <?php if (empty($messages)): ?>
                                    <div class="alert alert-info">
                                        No messages in this group yet.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($messages as $message): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between">
                                                            <strong><?= htmlspecialchars($message['sender_name']) ?></strong>
                                                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($message['created_at'])) ?></small>
                                                        </div>
                                                        <?php if ($message['subject']): ?>
                                                            <h6 class="mb-1"><?= htmlspecialchars($message['subject']) ?></h6>
                                                        <?php endif; ?>
                                                        <p class="mb-0"><?= htmlspecialchars($message['content']) ?></p>
                                                    </div>
                                                    <?php if (!$message['is_read'] && $message['sender_id'] !== $user_id): ?>
                                                        <span class="badge bg-danger ms-2">New</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <a href="student_group_messages.php?id=<?= $group_id ?>" class="btn btn-outline-primary">
                                            View All Messages
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Message form -->
                                <form method="POST" class="mt-4">
                                    <div class="mb-3">
                                        <label for="messageSubject" class="form-label">Subject (optional)</label>
                                        <input type="text" class="form-control" id="messageSubject" name="subject">
                                    </div>
                                    <div class="mb-3">
                                        <label for="messageContent" class="form-label">Message</label>
                                        <textarea class="form-control" id="messageContent" name="content" rows="3" required></textarea>
                                    </div>
                                    <button type="submit" name="send_message" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Send Message
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right sidebar with group info -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Group Info</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="bi bi-calendar"></i> Created: <?= date('M d, Y', strtotime($group['created_at'])) ?>
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-lock"></i> Status: <?= $group['is_private'] ? 'Private' : 'Public' ?>
                            </li>
                            <?php if ($group['is_private']): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-key"></i> Join Code: <?= $group['join_code'] ?>
                                </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <i class="bi bi-chat-left-text"></i> Discussions: <?= count($discussions) ?>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Upcoming events -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $sessions = $pdo->query("
                            SELECT * FROM study_sessions 
                            WHERE group_id = $group_id AND session_datetime > NOW()
                            ORDER BY session_datetime ASC
                            LIMIT 3
                        ")->fetchAll();
                        ?>
                        
                        <?php if (empty($sessions)): ?>
                            <div class="alert alert-info">
                                No upcoming sessions scheduled.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($sessions as $session): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($session['title']) ?></h6>
                                                <small><?= date('M d, Y h:i A', strtotime($session['session_datetime'])) ?></small>
                                            </div>
                                            <?php if ($session['meeting_link']): ?>
                                                <a href="<?= htmlspecialchars($session['meeting_link']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    Join
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3 text-center">
                            <a href="student_group_schedule.php?id=<?= $group_id ?>" class="btn btn-sm btn-outline-secondary">
                                View Full Schedule
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Discussion Modal -->
    <div class="modal fade" id="newDiscussionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Discussion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="discussionTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="discussionTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="discussionContent" class="form-label">Content</label>
                            <textarea class="form-control" id="discussionContent" name="content" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="post_discussion" class="btn btn-primary">Post Discussion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activate tab from URL hash
        window.addEventListener('DOMContentLoaded', () => {
            if (window.location.hash) {
                const tabTrigger = document.querySelector(`[data-bs-target="${window.location.hash}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
            
            // Auto-focus reply input when expanding discussion
            document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(button => {
                button.addEventListener('click', function() {
                    const target = document.querySelector(this.getAttribute('data-bs-target'));
                    if (target.classList.contains('show')) {
                        const input = target.querySelector('input[type="text"]');
                        if (input) input.focus();
                    }
                });
            });
        });
    </script>
</body>
</html>