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
    header("Location: admin_view_allgroups.php ");
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
    header("Location: admin_view_allgroups.php ");
    exit();
}

// Handle sending new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $content = $conn->real_escape_string($_POST['content']);
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
    $stmt->bind_param("iisis", $group_id, $_SESSION['user_id'], $content, $is_announcement, $media_path);
    $stmt->execute();
    
    $_SESSION['message'] = "Message sent successfully!";
    header("Location: admin_view_groupinfo.php?id=$group_id#chat");
    exit();
}

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = (int)$_POST['message_id'];
    
    // Verify message belongs to this group
    $stmt = $conn->prepare("SELECT media_path FROM group_messages WHERE id = ? AND group_id = ?");
    $stmt->bind_param("ii", $message_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    
    if ($message) {
        // Delete associated media file if exists
        if ($message['media_path'] && file_exists($message['media_path'])) {
            unlink($message['media_path']);
        }
        
        // Delete message
        $stmt = $conn->prepare("DELETE FROM group_messages WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        
        $_SESSION['message'] = "Message deleted successfully!";
    } else {
        $_SESSION['error'] = "Message not found!";
    }
    
    header("Location: admin_view_groupinfo.php?id=$group_id#chat");
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

// Get group messages
$messages = [];
$stmt = $conn->prepare("
    SELECT m.*, u.name as sender_name, u.profile_picture as sender_pic, gm.role as sender_role
    FROM group_messages m
    JOIN users u ON m.sender_id = u.id
    JOIN group_members gm ON u.id = gm.user_id AND gm.group_id = ?
    WHERE m.group_id = ?
    ORDER BY m.created_at DESC
    LIMIT 50
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
        .chat-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 70%;
            position: relative;
        }
        .admin-message {
            background-color: #0d6efd;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0;
        }
        .student-message {
            background-color: #e9ecef;
            color: #212529;
            margin-right: auto;
            border-bottom-left-radius: 0;
        }
        .announcement {
            background-color: #dc3545;
            color: white;
            border-radius: 8px;
            max-width: 85%;
            margin: 15px auto;
            padding: 12px 18px;
            border-left: 5px solid #ffc107;
        }
        .message-sender {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            text-align: right;
            margin-top: 5px;
        }
        .message-actions {
            position: absolute;
            top: 5px;
            right: 10px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .message:hover .message-actions {
            opacity: 1;
        }
        .media-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
        }
        .badge-admin {
            background-color: #fd7e14;
        }
        .badge-mod {
            background-color: #0dcaf0;
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
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <ul class="nav nav-tabs" id="groupTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                    <i class="bi bi-info-circle"></i> Group Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
                    <i class="bi bi-people"></i> Members
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button" role="tab">
                    <i class="bi bi-calendar-event"></i> Sessions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="chat-tab" data-bs-toggle="tab" data-bs-target="#chat" type="button" role="tab">
                    <i class="bi bi-chat-left-text"></i> Group Chat
                </button>
            </li>
        </ul>
        
        <div class="tab-content mt-3" id="groupTabsContent">
            <!-- Group Info Tab -->
            <div class="tab-pane fade show active" id="info" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Group Information</h5>
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
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Stats</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <h5><i class="bi bi-people"></i> Members</h5>
                                                <h3><?= count($members) ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h5><i class="bi bi-chat-left-text"></i> Messages</h5>
                                                <h3><?= count($messages) ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h5><i class="bi bi-calendar-event"></i> Upcoming Sessions</h5>
                                                <h3><?= count($sessions) ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Members Tab -->
            <div class="tab-pane fade" id="members" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Group Members</h5>
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
                                                <span class="badge <?= 
                                                    $member['role'] === 'owner' ? 'badge-admin' : 
                                                    ($member['role'] === 'moderator' ? 'badge-mod' : 'bg-secondary') 
                                                ?>">
                                                    <?= ucfirst($member['role']) ?>
                                                </span>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Joined <?= date('M d, Y', strtotime($member['joined_at'])) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sessions Tab -->
            <div class="tab-pane fade" id="sessions" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Study Sessions</h5>
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
                                                <h5 class="card-title"><?= htmlspecialchars($session['title']) ?></h5>
                                                <p class="card-text"><?= htmlspecialchars($session['description']) ?></p>
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item">
                                                        <i class="bi bi-calendar"></i> 
                                                        <?= date('M d, Y h:i A', strtotime($session['session_datetime'])) ?>
                                                    </li>
                                                    <?php if ($session['meeting_link']): ?>
                                                        <li class="list-group-item">
                                                            <i class="bi bi-link-45deg"></i> 
                                                            <a href="<?= htmlspecialchars($session['meeting_link']) ?>" target="_blank">Meeting Link</a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="card-footer text-muted">
                                                Created: <?= date('M d, Y', strtotime($session['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Chat Tab -->
            <div class="tab-pane fade" id="chat" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Group Chat</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="chat-container" id="chatContainer">
                            <?php if (empty($messages)): ?>
                                <div class="alert alert-info">
                                    No messages yet. Start the conversation!
                                </div>
                            <?php else: ?>
                                <?php foreach (array_reverse($messages) as $msg): ?>
                                    <div class="message <?= 
                                        $msg['is_announcement'] ? 'announcement' : 
                                        ($msg['sender_id'] == $_SESSION['user_id'] ? 'admin-message' : 'student-message')
                                    ?>">
                                        <?php if ($msg['is_announcement']): ?>
                                            <div class="message-sender">
                                                <i class="bi bi-megaphone"></i> ANNOUNCEMENT
                                            </div>
                                        <?php else: ?>
                                            <div class="message-sender">
                                                <?= htmlspecialchars($msg['sender_name']) ?>
                                                <?php if ($msg['sender_role'] === 'owner' || $msg['sender_role'] === 'moderator'): ?>
                                                    <span class="badge bg-danger ms-1">Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                                        
                                        <?php if (!empty($msg['media_path'])): ?>
                                            <?php
                                            $file_ext = strtolower(pathinfo($msg['media_path'], PATHINFO_EXTENSION));
                                            $image_exts = ['jpg', 'jpeg', 'png', 'gif'];
                                            $video_exts = ['mp4', 'webm'];
                                            $audio_exts = ['mp3', 'wav'];
                                            ?>
                                            
                                            <div class="mt-2">
                                                <?php if (in_array($file_ext, $image_exts)): ?>
                                                    <img src="<?= htmlspecialchars($msg['media_path']) ?>" class="media-preview">
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
                                                        <i class="bi bi-download"></i> Download File
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="message-time">
                                            <?= date('M j, g:i a', strtotime($msg['created_at'])) ?>
                                            <?php if ($msg['edited_at']): ?>
                                                <span class="badge bg-info">edited</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($msg['sender_id'] == $_SESSION['user_id'] || $_SESSION['role'] === 'admin'): ?>
                                            <div class="message-actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                    <button type="submit" name="delete_message" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this message?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-3 border-top">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="messageContent" class="form-label">Your Message</label>
                                    <textarea class="form-control" id="messageContent" name="content" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="messageMedia" class="form-label">Attachment (optional)</label>
                                    <input type="file" class="form-control" id="messageMedia" name="media">
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="announcementCheck" name="is_announcement">
                                        <label class="form-check-label" for="announcementCheck">
                                            Send as announcement
                                        </label>
                                    </div>
                                    <button type="submit" name="send_message" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Send
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll chat to bottom
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        
        // Handle tab persistence
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash) {
                const tabTrigger = document.querySelector(`[data-bs-target="${window.location.hash}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
            
            // Activate tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Simple AJAX polling for new messages (every 10 seconds)
        setInterval(function() {
            fetch(`get_messages.php?group_id=<?= $group_id ?>&last_message_id=<?= !empty($messages) ? $messages[0]['id'] : 0 ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.new_messages.length > 0) {
                        // Add new messages to chat
                        data.new_messages.forEach(msg => {
                            const messageHtml = `
                                <div class="message ${msg.is_announcement ? 'announcement' : (msg.sender_id == <?= $_SESSION['user_id'] ?> ? 'admin-message' : 'student-message')}">
                                    ${msg.is_announcement ? 
                                        '<div class="message-sender"><i class="bi bi-megaphone"></i> ANNOUNCEMENT</div>' : 
                                        `<div class="message-sender">${msg.sender_name} ${(msg.sender_role === 'owner' || msg.sender_role === 'moderator') ? '<span class="badge bg-danger ms-1">Admin</span>' : ''}</div>`
                                    }
                                    <div>${msg.content.replace(/\n/g, '<br>')}</div>
                                    ${msg.media_path ? 
                                        `<div class="mt-2">
                                            ${msg.media_path.match(/\.(jpg|jpeg|png|gif)$/i) ? 
                                                `<img src="${msg.media_path}" class="media-preview">` : 
                                                msg.media_path.match(/\.(mp4|webm)$/i) ? 
                                                    `<video controls class="media-preview"><source src="${msg.media_path}" type="video/${msg.media_path.split('.').pop()}"></video>` : 
                                                    msg.media_path.match(/\.(mp3|wav)$/i) ? 
                                                        `<audio controls class="media-preview"><source src="${msg.media_path}" type="audio/${msg.media_path.split('.').pop()}"></audio>` : 
                                                        `<a href="${msg.media_path}" class="btn btn-sm btn-secondary" download><i class="bi bi-download"></i> Download File</a>`
                                            }
                                        </div>` : ''
                                    }
                                    <div class="message-time">
                                        ${new Date(msg.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                                        ${msg.edited_at ? '<span class="badge bg-info">edited</span>' : ''}
                                    </div>
                                    ${msg.sender_id == <?= $_SESSION['user_id'] ?> || <?= $_SESSION['role'] === 'admin' ? 'true' : 'false' ?> ? 
                                        `<div class="message-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="message_id" value="${msg.id}">
                                                <button type="submit" name="delete_message" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this message?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>` : ''
                                    }
                                </div>
                            `;
                            chatContainer.insertAdjacentHTML('beforeend', messageHtml);
                        });
                        
                        // Scroll to bottom
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                        
                        // Play notification sound if not on chat tab
                        const activeTab = document.querySelector('.nav-link.active');
                        if (activeTab && activeTab.id !== 'chat-tab') {
                            const audio = new Audio('../assets/notification.mp3');
                            audio.play().catch(e => console.log('Audio play failed:', e));
                        }
                    }
                })
                .catch(error => console.error('Error fetching messages:', error));
        }, 10000); // 10 seconds
    </script>
</body>
</html>