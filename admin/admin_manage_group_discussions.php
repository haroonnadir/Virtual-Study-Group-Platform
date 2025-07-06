<?php
session_start();
require_once '../db_connect.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "CSRF token validation failed";
        header("Location: admin_view_allgroups.php");
        exit();
    }

    $group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
    if ($group_id <= 0) {
        $_SESSION['error'] = "Group ID not specified";
        header("Location: admin_view_allgroups.php");
        exit();
    }

    switch ($_POST['action']) {
        case 'delete':
            mysqli_begin_transaction($conn);
            try {
                $stmt1 = mysqli_prepare($conn, "DELETE FROM group_members WHERE group_id = ?");
                $stmt2 = mysqli_prepare($conn, "DELETE FROM discussions WHERE group_id = ?");
                $stmt3 = mysqli_prepare($conn, "DELETE FROM study_groups WHERE id = ?");
                
                mysqli_stmt_bind_param($stmt1, 'i', $group_id);
                mysqli_stmt_bind_param($stmt2, 'i', $group_id);
                mysqli_stmt_bind_param($stmt3, 'i', $group_id);
                
                mysqli_stmt_execute($stmt1);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_execute($stmt3);
                
                mysqli_commit($conn);
                $_SESSION['message'] = "Group deleted successfully!";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['error'] = "Error deleting group: " . $e->getMessage();
            }
            break;

        case 'toggle_privacy':
            $stmt = mysqli_prepare($conn, "UPDATE study_groups SET is_private = NOT is_private WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $group_id);
            mysqli_stmt_execute($stmt);
            $_SESSION['message'] = "Group privacy updated!";
            break;
    }

    header("Location: admin_view_allgroups.php");
    exit();
}

// Fetch all groups with their messages
$sql = "
    SELECT sg.*, 
           u.name AS creator_name,
           COUNT(gm.user_id) AS member_count,
           (SELECT COUNT(*) FROM group_messages gm WHERE gm.group_id = sg.id) AS message_count,
           (SELECT MAX(gm.created_at) FROM group_messages gm WHERE gm.group_id = sg.id) AS last_message_time
    FROM study_groups sg
    LEFT JOIN users u ON sg.created_by = u.id
    LEFT JOIN group_members gm ON sg.id = gm.group_id
    GROUP BY sg.id
    ORDER BY last_message_time DESC, sg.created_at DESC
";
$result = mysqli_query($conn, $sql);
$groups = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

// Get recent messages for each group
foreach ($groups as &$group) {
    $group_id = $group['id'];
    $messages_sql = "
        SELECT gm.*, u.name as sender_name, u.profile_picture as sender_pic
        FROM group_messages gm
        JOIN users u ON gm.sender_id = u.id
        WHERE gm.group_id = ?
        ORDER BY gm.created_at DESC
        LIMIT 3
    ";
    $stmt = mysqli_prepare($conn, $messages_sql);
    mysqli_stmt_bind_param($stmt, 'i', $group_id);
    mysqli_stmt_execute($stmt);
    $messages_result = mysqli_stmt_get_result($stmt);
    $group['recent_messages'] = mysqli_fetch_all($messages_result, MYSQLI_ASSOC);
}
unset($group); // Break the reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Group Chats - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .group-card {
            transition: transform 0.2s;
        }
        .group-card:hover {
            transform: translateY(-5px);
        }
        .private-badge {
            background-color: #6f42c1;
        }
        .public-badge {
            background-color: #20c997;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
        }
        .message-preview {
            font-size: 0.9rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .message-time {
            font-size: 0.8rem;
            color: #adb5bd;
        }
        .unread-badge {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-chat-left-text-fill"></i> Manage Group Chats</h2>
        <div>
            <span class="badge bg-primary">Total: <?= count($groups) ?></span>
        </div>
        <a href="admin_dashboard.php" class="btn btn-secondary">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (empty($groups)): ?>
        <div class="empty-state">
            <i class="bi bi-chat-left-text" style="font-size: 3rem; color: #6c757d;"></i>
            <h3 class="mt-3">No Group Chats Found</h3>
            <p class="text-muted">There are no group chats available at the moment.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($groups as $group): ?>
                <div class="col">
                    <div class="card group-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($group['name']) ?></h5>
                            <span class="badge <?= $group['is_private'] ? 'private-badge' : 'public-badge' ?>">
                                <?= $group['is_private'] ? 'Private' : 'Public' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?= htmlspecialchars($group['description']) ?></p>
                            
                            <!-- Recent Messages Preview -->
                            <div class="mt-3">
                                <h6 class="mb-2"><i class="bi bi-chat-left-text"></i> Recent Messages</h6>
                                <?php if (empty($group['recent_messages'])): ?>
                                    <p class="text-muted">No messages yet</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($group['recent_messages'] as $message): ?>
                                            <div class="list-group-item border-0 px-0 py-1">
                                                <div class="d-flex justify-content-between">
                                                    <strong><?= htmlspecialchars($message['sender_name']) ?></strong>
                                                    <span class="message-time">
                                                        <?= date('M j, g:i a', strtotime($message['created_at'])) ?>
                                                    </span>
                                                </div>
                                                <p class="message-preview mb-0">
                                                    <?= htmlspecialchars(substr($message['content'], 0, 50)) ?>
                                                    <?php if (strlen($message['content']) > 50): ?>...<?php endif; ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between">
                                <a href="admin_enter_group.php?id=<?= $group['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> View Chat
                                </a>
                                <div class="btn-group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <button type="submit" name="action" value="toggle_privacy" class="btn btn-sm btn-warning">
                                            <i class="bi bi-lock"></i> <?= $group['is_private'] ? 'Public' : 'Private' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this group and all its content?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>