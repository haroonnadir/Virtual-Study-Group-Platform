<?php
session_start();
include '../db_connect.php';

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];

// Get group ID from URL
if (!isset($_GET['id'])) {
    header("Location: student_view_allgroups.php");
    exit();
}
$group_id = (int)$_GET['id'];

// Check if user is the owner (owners can't leave)
$stmt = $conn->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$membership = $result->fetch_assoc();

if (!$membership) {
    $_SESSION['error'] = "You are not a member of this group!";
    header("Location: student_view_allgroups.php");
    exit();
}

if ($membership['role'] === 'owner') {
    $_SESSION['error'] = "As the group owner, you must transfer ownership before leaving.";
    header("Location: student_enter_group.php?id=$group_id");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token!";
        header("Location: student_view_allgroups.php");
        exit();
    }
    
    // Remove user from group
    $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();
    
    // Check if group is now empty and delete if so
    $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['member_count'];
    
    if ($count === 0) {
        // Delete all group data (messages, discussions, etc.)
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM group_messages WHERE group_id = $group_id");
            $conn->query("DELETE FROM replies WHERE discussion_id IN (SELECT id FROM discussions WHERE group_id = $group_id)");
            $conn->query("DELETE FROM discussions WHERE group_id = $group_id");
            $conn->query("DELETE FROM study_sessions WHERE group_id = $group_id");
            $conn->query("DELETE FROM study_groups WHERE id = $group_id");
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting group data: " . $e->getMessage();
            header("Location: student_view_allgroups.php");
            exit();
        }
    }
    
    $_SESSION['message'] = "You have successfully left the group.";
    header("Location: student_view_allgroups.php");
    exit();
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Group - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-door-open"></i> Leave Group</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5 class="alert-heading">Are you sure you want to leave this group?</h5>
                            <p class="mb-0">You will lose access to all group discussions, messages, and resources.</p>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="student_enter_group.php?id=<?php echo $group_id; ?>" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-door-open"></i> Yes, Leave Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>