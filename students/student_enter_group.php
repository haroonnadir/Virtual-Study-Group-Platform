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

// Handle new discussion post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_discussion'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    
    $stmt = $conn->prepare("INSERT INTO discussions (group_id, user_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group_id, $user_id, $title, $content);
    $stmt->execute();
    
    $_SESSION['message'] = "Discussion posted successfully!";
    header("Location: student_enter_group.php?id=$group_id");
    exit();
}

// Handle new reply
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
    
    header("Location: student_enter_group.php?id=$group_id#discussion-$discussion_id");
    exit();
}

// Handle new group message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $content = $conn->real_escape_string($_POST['message']);
    $is_announcement = isset($_POST['is_announcement']) ? 1 : 0;
    
    // Handle file upload
    $media_path = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/group_messages/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('msg_', true) . '.' . $file_ext;
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['media']['tmp_name'], $target_path)) {
            $media_path = $target_path;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, content, is_announcement, media_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisis", $group_id, $user_id, $content, $is_announcement, $media_path);
    $stmt->execute();
    
    $_SESSION['message'] = "Message sent successfully!";
    header("Location: student_enter_group.php?id=$group_id#messages");
    exit();
}

// Handle message update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_message'])) {
    $message_id = (int)$_POST['message_id'];
    $new_content = $conn->real_escape_string($_POST['new_content']);
    
    // Verify user owns this message
    $stmt = $conn->prepare("SELECT * FROM group_messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->fetch_assoc()) {
        $stmt = $conn->prepare("UPDATE group_messages SET content = ?, edited_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_content, $message_id);
        $stmt->execute();
        
        $_SESSION['message'] = "Message updated successfully!";
    } else {
        $_SESSION['error'] = "You can only edit your own messages!";
    }
    
    header("Location: student_enter_group.php?id=$group_id#messages");
    exit();
}

// Handle media update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_media'])) {
    $message_id = (int)$_POST['message_id'];
    
    // Verify user owns this message
    $stmt = $conn->prepare("SELECT * FROM group_messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    
    if ($message) {
        // Handle file upload
        $media_path = $message['media_path'];
        if (isset($_FILES['new_media']) && $_FILES['new_media']['error'] === UPLOAD_ERR_OK) {
            // Delete old media if exists
            if ($media_path && file_exists($media_path)) {
                unlink($media_path);
            }
            
            $upload_dir = '../uploads/group_messages/';
            $file_ext = pathinfo($_FILES['new_media']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('msg_', true) . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['new_media']['tmp_name'], $target_path)) {
                $media_path = $target_path;
            }
        }
        
        $stmt = $conn->prepare("UPDATE group_messages SET media_path = ?, edited_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $media_path, $message_id);
        $stmt->execute();
        
        $_SESSION['message'] = "Media updated successfully!";
    } else {
        $_SESSION['error'] = "You can only edit your own messages!";
    }
    
    header("Location: student_enter_group.php?id=$group_id#messages");
    exit();
}

// Handle media deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {
    $message_id = (int)$_POST['message_id'];
    
    // Verify user owns this message
    $stmt = $conn->prepare("SELECT * FROM group_messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    
    if ($message && $message['media_path']) {
        if (file_exists($message['media_path'])) {
            unlink($message['media_path']);
        }
        
        $stmt = $conn->prepare("UPDATE group_messages SET media_path = NULL, edited_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        
        $_SESSION['message'] = "Media deleted successfully!";
    } else {
        $_SESSION['error'] = "You can only delete media from your own messages!";
    }
    
    header("Location: student_enter_group.php?id=$group_id#messages");
    exit();
}

// Get group members
$members = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.profile_picture, gm.role, gm.joined_at
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

// Get discussions with reply counts
$discussions = [];
$stmt = $conn->prepare("
    SELECT d.*, u.name as author_name, u.profile_picture as author_pic,
           (SELECT COUNT(*) FROM replies r WHERE r.discussion_id = d.id) as reply_count
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    WHERE d.group_id = ?
    ORDER BY d.is_pinned DESC, d.posted_at DESC
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $discussions[] = $row;
}

// Get group messages
$messages = [];
$stmt = $conn->prepare("
    SELECT m.*, u.name as sender_name, u.profile_picture as sender_pic, gm.role as sender_role
    FROM group_messages m
    JOIN users u ON m.sender_id = u.id
    JOIN group_members gm ON u.id = gm.user_id AND gm.group_id = ?
    WHERE m.group_id = ?
    ORDER BY m.created_at DESC
    LIMIT 20
");
$stmt->bind_param("ii", $group_id, $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

// Get upcoming sessions
$sessions = [];
$stmt = $conn->prepare("
    SELECT * FROM study_sessions 
    WHERE group_id = ? AND session_datetime > NOW()
    ORDER BY session_datetime ASC
    LIMIT 3
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

// Check if user is admin/moderator
$is_admin = in_array($group['role'], ['owner', 'moderator']);
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
        .message-bubble {
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            max-width: 80%;
            position: relative;
        }
        .your-message {
            background-color: #0d6efd;
            color: white;
            align-self: flex-end;
        }
        .other-message {
            background-color: #f8f9fa;
            color: #212529;
            align-self: flex-start;
        }
        .announcement-message {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        .message-sender {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
        }
        .message-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .message-bubble:hover .message-actions {
            opacity: 1;
        }
        .action-btn {
            background: none;
            border: none;
            padding: 0.25rem;
            margin-left: 0.25rem;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .your-message .action-btn {
            color: rgba(255, 255, 255, 0.7);
        }
        .your-message .action-btn:hover {
            color: white;
        }
        .other-message .action-btn {
            color: rgba(0, 0, 0, 0.5);
        }
        .other-message .action-btn:hover {
            color: black;
        }
        .media-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 0.5rem;
        }
        .edit-message-form {
            display: none;
            margin-top: 0.5rem;
        }
        .timestamp {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 0.5rem;
            text-align: right;
        }
        .conversation-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 1rem;
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
                    <span>
                        <a href="./students_dashboard.php" 
                            style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-family: Arial, sans-serif; transition: background-color 0.3s ease;" 
                            onmouseover="this.style.backgroundColor='#0056b3'" 
                            onmouseout="this.style.backgroundColor='#007bff'">
                            Go Back to Dashboard
                        </a>
                    </span>
                </div>
                <p class="lead"><?= htmlspecialchars($group['description']) ?></p>
                
                <?php if ($is_admin): ?>
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
                                            $replies = [];
                                            $stmt = $conn->prepare("
                                                SELECT r.*, u.name as author_name, u.profile_picture as author_pic
                                                FROM replies r
                                                JOIN users u ON r.user_id = u.id
                                                WHERE r.discussion_id = ?
                                                ORDER BY r.replied_at
                                            ");
                                            $stmt->bind_param("i", $discussion['id']);
                                            $stmt->execute();
                                            $replyResult = $stmt->get_result();
                                            while ($row = $replyResult->fetch_assoc()) {
                                                $replies[] = $row;
                                            }
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
                            <div class="card-body p-0">
                                <div class="conversation-container" id="conversationContainer">
                                    <?php if (empty($messages)): ?>
                                        <div class="alert alert-info m-3">
                                            No messages yet. Start the conversation!
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="d-flex flex-column <?= $msg['sender_id'] != $user_id ? 'align-items-start' : 'align-items-end' ?> p-3">
                                                <div class="message-bubble
                                                    <?= $msg['sender_id'] != $user_id ? 'other-message' : 'your-message' ?>
                                                    <?= $msg['is_announcement'] ? 'announcement-message' : '' ?>">
                                                    
                                                    <!-- Sender info -->
                                                    <div class="message-sender">
                                                        <?php if ($msg['sender_id'] != $user_id): ?>
                                                            <?php if ($msg['sender_pic']): ?>
                                                                <img src="<?= htmlspecialchars($msg['sender_pic']) ?>" 
                                                                     class="rounded-circle me-2" width="24" height="24">
                                                            <?php else: ?>
                                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                                     style="width: 24px; height: 24px;">
                                                                    <i class="bi bi-person text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                                            <?php if ($msg['sender_role'] === 'owner' || $msg['sender_role'] === 'moderator'): ?>
                                                                <span class="badge bg-danger ms-1">Admin</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <strong>You</strong>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Message actions for sender -->
                                                    <?php if ($msg['sender_id'] == $user_id): ?>
                                                        <div class="message-actions">
                                                            <button class="action-btn edit-message-btn" data-message-id="<?= $msg['id'] ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <?php if ($msg['media_path']): ?>
                                                                <button class="action-btn edit-media-btn" data-message-id="<?= $msg['id'] ?>">
                                                                    <i class="bi bi-image"></i>
                                                                </button>
                                                                <button class="action-btn delete-media-btn" data-message-id="<?= $msg['id'] ?>">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Message content -->
                                                    <div id="message-content-<?= $msg['id'] ?>">
                                                        <p><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                                                    </div>

                                                    <!-- Edit message form (hidden by default) -->
                                                    <div id="edit-message-form-<?= $msg['id'] ?>" class="edit-message-form">
                                                        <form method="POST" class="mb-3">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                            <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                            <textarea class="form-control mb-2" name="new_content" rows="3"><?= htmlspecialchars($msg['content']) ?></textarea>
                                                            <button type="submit" name="update_message" class="btn btn-sm btn-success">
                                                                <i class="bi bi-save"></i> Save
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary cancel-edit-btn">
                                                                <i class="bi bi-x"></i> Cancel
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Edit media form (hidden by default) -->
                                                    <div id="edit-media-form-<?= $msg['id'] ?>" class="edit-message-form">
                                                        <form method="POST" enctype="multipart/form-data" class="mb-3">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                            <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                            <input type="file" class="form-control mb-2" name="new_media" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                                                            <button type="submit" name="update_media" class="btn btn-sm btn-success">
                                                                <i class="bi bi-save"></i> Update Media
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary cancel-edit-btn">
                                                                <i class="bi bi-x"></i> Cancel
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Media attachment -->
                                                    <?php if (!empty($msg['media_path'])): ?>
                                                        <div id="media-container-<?= $msg['id'] ?>" class="mt-2">
                                                            <?php
                                                            $file_ext = strtolower(pathinfo($msg['media_path'], PATHINFO_EXTENSION));
                                                            $image_exts = ['jpg', 'jpeg', 'png', 'gif'];
                                                            $video_exts = ['mp4', 'webm'];
                                                            $audio_exts = ['mp3', 'wav'];
                                                            ?>

                                                            <?php if (in_array($file_ext, $image_exts)): ?>
                                                                <img src="<?= htmlspecialchars($msg['media_path']) ?>" class="media-preview img-thumbnail">
                                                            <?php elseif (in_array($file_ext, $video_exts)): ?>
                                                                <video controls class="media-preview">
                                                                    <source src="<?= htmlspecialchars($msg['media_path']) ?>" type="video/<?= $file_ext ?>">
                                                                </video>
                                                            <?php elseif (in_array($file_ext, $audio_exts)): ?>
                                                                <audio controls class="media-preview">
                                                                    <source src="<?= htmlspecialchars($msg['media_path']) ?>" type="audio/<?= $file_ext ?>">
                                                                </audio>
                                                            <?php else: ?>
                                                                <a href="<?= htmlspecialchars($msg['media_path']) ?>" class="btn btn-sm btn-secondary" download>
                                                                    <i class="bi bi-download"></i> Download Attachment
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Timestamp and status -->
                                                    <div class="timestamp">
                                                        <?= date('M j, g:i a', strtotime($msg['created_at'])) ?>
                                                        <?php if (isset($msg['edited_at']) && $msg['edited_at']): ?>
                                                            <span class="badge bg-info">Edited</span>
                                                        <?php endif; ?>
                                                        <?php if (($msg['sender_role'] === 'owner' || $msg['sender_role'] === 'moderator') && $msg['sender_id'] != $user_id && !$msg['is_read']): ?>
                                                            <span class="badge bg-success">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Reply form -->
                                <div class="reply-form p-3 border-top">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?= isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : '' ?>">
                                        <input type="hidden" name="group_id" value="<?= $group_id ?>">

                                        <div class="mb-3">
                                            <label for="message" class="form-label">Your Message</label>
                                            <textarea class="form-control" id="message" name="message" rows="3" required 
                                                      placeholder="Type your message here..."></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="mediaInput" class="form-label">Attachment (Optional)</label>
                                            <input type="file" class="form-control" name="media" id="mediaInput" 
                                                   accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                                            <small class="text-muted">Max size: 5MB. Supported: images, videos, audio, PDF, Word</small>
                                            <div id="mediaPreview" class="mt-2"></div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($is_admin): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="is_announcement" id="isAnnouncement">
                                                        <label class="form-check-label" for="isAnnouncement">
                                                            Post as Announcement
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="submit" name="send_message" class="btn btn-primary">
                                                <i class="bi bi-send"></i> Send Message
                                            </button>
                                        </div>
                                    </form>
                                </div>
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
                            <li class="list-group-item">
                                <i class="bi bi-envelope"></i> Messages: <?= count($messages) ?>
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

    <!-- Delete Media Modal -->
    <div class="modal fade" id="deleteMediaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this attachment? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteMediaForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="group_id" id="deleteGroupId">
                        <input type="hidden" name="message_id" id="deleteMessageId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_media" class="btn btn-danger">Delete</button>
                    </form>
                </div>
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
            
            // Scroll to bottom of conversation container
            const conversationContainer = document.getElementById('conversationContainer');
            if (conversationContainer) {
                conversationContainer.scrollTop = conversationContainer.scrollHeight;
            }
            
            // Edit message buttons
            document.querySelectorAll('.edit-message-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const messageId = this.dataset.messageId;
                    document.getElementById(`message-content-${messageId}`).style.display = 'none';
                    document.getElementById(`edit-message-form-${messageId}`).style.display = 'block';
                });
            });

            // Edit media buttons
            document.querySelectorAll('.edit-media-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const messageId = this.dataset.messageId;
                    document.getElementById(`media-container-${messageId}`).style.display = 'none';
                    document.getElementById(`edit-media-form-${messageId}`).style.display = 'block';
                });
            });

            // Cancel edit buttons
            document.querySelectorAll('.cancel-edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const form = this.closest('.edit-message-form');
                    const messageId = form.id.split('-')[3];
                    
                    form.style.display = 'none';
                    if (form.id.includes('edit-message-form')) {
                        document.getElementById(`message-content-${messageId}`).style.display = 'block';
                    } else {
                        document.getElementById(`media-container-${messageId}`).style.display = 'block';
                    }
                });
            });

            // Delete media buttons
            document.querySelectorAll('.delete-media-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const messageId = this.dataset.messageId;
                    document.getElementById('deleteGroupId').value = <?= $group_id ?>;
                    document.getElementById('deleteMessageId').value = messageId;
                    
                    const modal = new bootstrap.Modal(document.getElementById('deleteMediaModal'));
                    modal.show();
                });
            });

            // Media preview
            document.getElementById('mediaInput')?.addEventListener('change', function(e) {
                const preview = document.getElementById('mediaPreview');
                preview.innerHTML = '';
                
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const fileType = file.type.split('/')[0];
                    
                    if (fileType === 'image') {
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        img.classList.add('img-thumbnail');
                        img.style.maxHeight = '150px';
                        preview.appendChild(img);
                    } else {
                        const div = document.createElement('div');
                        div.className = 'alert alert-info';
                        div.textContent = `File selected: ${file.name}`;
                        preview.appendChild(div);
                    }
                }
            });
        });
    </script>
</body>
</html>