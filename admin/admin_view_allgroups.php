<?php
session_start();
require_once '../db_connect.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        $_SESSION['error'] = "Invalid Group ID";
        header("Location: admin_view_allgroups.php");
        exit();
    }

    switch ($_POST['action']) {
        case 'delete':
            mysqli_begin_transaction($conn);
            try {
                // Delete group members first
                $stmt1 = mysqli_prepare($conn, "DELETE FROM group_members WHERE group_id = ?");
                if (!$stmt1) throw new Exception(mysqli_error($conn));
                mysqli_stmt_bind_param($stmt1, 'i', $group_id);
                mysqli_stmt_execute($stmt1);
                
                // Delete discussions
                $stmt2 = mysqli_prepare($conn, "DELETE FROM discussions WHERE group_id = ?");
                if (!$stmt2) throw new Exception(mysqli_error($conn));
                mysqli_stmt_bind_param($stmt2, 'i', $group_id);
                mysqli_stmt_execute($stmt2);
                
                // Finally delete the group
                $stmt3 = mysqli_prepare($conn, "DELETE FROM study_groups WHERE id = ?");
                if (!$stmt3) throw new Exception(mysqli_error($conn));
                mysqli_stmt_bind_param($stmt3, 'i', $group_id);
                mysqli_stmt_execute($stmt3);
                
                mysqli_commit($conn);
                $_SESSION['message'] = "Group and all related data deleted successfully!";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['error'] = "Error deleting group: " . $e->getMessage();
            }
            break;

        case 'toggle_privacy':
            try {
                // Toggle privacy status
                $stmt = mysqli_prepare($conn, "UPDATE study_groups SET is_private = NOT is_private WHERE id = ?");
                if (!$stmt) throw new Exception(mysqli_error($conn));
                
                mysqli_stmt_bind_param($stmt, 'i', $group_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }
                
                // Get updated group info for the message
                $result = mysqli_query($conn, "SELECT is_private FROM study_groups WHERE id = $group_id");
                if ($result && mysqli_num_rows($result)) {
                    $group = mysqli_fetch_assoc($result);
                    $status = $group['is_private'] ? 'Private' : 'Public';
                    $_SESSION['message'] = "Group privacy updated to $status!";
                } else {
                    $_SESSION['message'] = "Group privacy updated!";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error updating privacy: " . $e->getMessage();
            }
            break;
            
        default:
            $_SESSION['error'] = "Invalid action requested";
            break;
    }

    header("Location: admin_view_allgroups.php");
    exit();
}

// Fetch all groups with creator info and member count
$sql = "
    SELECT sg.*, 
           u.name AS creator_name,
           COUNT(gm.user_id) AS member_count
    FROM study_groups sg
    LEFT JOIN users u ON sg.created_by = u.id
    LEFT JOIN group_members gm ON sg.id = gm.group_id
    GROUP BY sg.id
    ORDER BY sg.created_at DESC
";
$result = mysqli_query($conn, $sql);
$groups = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Groups - Admin Panel</title>
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
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people-fill"></i> Manage Study Groups</h2>
        <div>
            <a href="admin_creat_groups.php" class="btn btn-primary me-2">
                <i class="bi bi-plus-circle"></i> Create New Group
            </a>
            <span class="badge bg-primary">Total: <?= count($groups) ?></span>
        </div>
        <a href="admin_dashboard.php" class="btn btn-secondary">
            <i class="bi bi-speedometer2"></i> Go Back Dashboard
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
            <i class="bi bi-people" style="font-size: 3rem; color: #6c757d;"></i>
            <h3 class="mt-3">No Groups Found</h3>
            <p class="text-muted">There are no study groups available at the moment.</p>
            <a href="admin_creat_groups.php" class="btn btn-primary mt-2">
                <i class="bi bi-plus-circle"></i> Create New Group
            </a>
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
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item">
                                    <i class="bi bi-book"></i> Subject: <?= htmlspecialchars($group['subject']) ?>
                                </li>
                                <li class="list-group-item">
                                    <i class="bi bi-person"></i> Creator: <?= htmlspecialchars($group['creator_name']) ?>
                                </li>
                                <li class="list-group-item">
                                    <i class="bi bi-people"></i> Members: <?= htmlspecialchars($group['member_count']) ?>
                                </li>
                                <li class="list-group-item">
                                    <i class="bi bi-calendar"></i> Created: <?= htmlspecialchars(date('M d, Y', strtotime($group['created_at']))) ?>
                                </li>
                                <?php if ($group['is_private']): ?>
                                    <li class="list-group-item">
                                        <i class="bi bi-lock"></i> Join Code: <?= htmlspecialchars($group['join_code']) ?>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between">
                                <a href="admin_view_groupinfo.php?id=<?= $group['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="admin_edit_groups.php?id=<?= $group['id'] ?>" class="btn btn-sm btn-secondary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <div class="btn-group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <button type="submit" name="action" value="toggle_privacy" class="btn btn-sm btn-warning">
                                            <i class="bi bi-lock"></i> <?= $group['is_private'] ? 'Make Public' : 'Make Private' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this group and all its content?');">
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