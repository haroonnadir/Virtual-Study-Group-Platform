<?php
session_start();

// Include the database connection file
require_once '../db_connect.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is admin (you should have proper admin authentication)
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     header("Location: ../login.php");
//     exit();
// }

$group_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Fetch group details
$group = null;
if ($group_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM study_groups WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $group_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $group = mysqli_fetch_assoc($result);
}

if (!$group) {
    $_SESSION['error'] = "Group not found";
    header("Location: admin_view_allgroups.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "CSRF token validation failed";
        header("Location: admin_edit_groups.php?id=$group_id");
        exit();
    }

    // Sanitize and validate input
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $join_code = $is_private ? filter_input(INPUT_POST, 'join_code', FILTER_SANITIZE_STRING) : null;

    // Validate required fields
    if (empty($name) || empty($description) || empty($subject)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: admin_edit_groups.php?id=$group_id");
        exit();
    }

    // Update group in database
    $stmt = mysqli_prepare($conn, "UPDATE study_groups SET name = ?, description = ?, subject = ?, is_private = ?, join_code = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'sssisi', $name, $description, $subject, $is_private, $join_code, $group_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Group updated successfully!";
        header("Location: admin_view_allgroups.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating group: " . mysqli_error($conn);
        header("Location: admin_edit_groups.php?id=$group_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Group - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square"></i> Edit Study Group</h2>
            <a href="admin_view_allgroups.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Groups
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="mb-3">
                    <label for="name" class="form-label">Group Name</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?= htmlspecialchars($group['name']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" 
                              rows="3" required><?= htmlspecialchars($group['description']) ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" 
                           value="<?= htmlspecialchars($group['subject']) ?>" required>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_private" 
                           name="is_private" <?= $group['is_private'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_private">Private Group</label>
                </div>
                
                <div class="mb-3" id="joinCodeContainer" style="<?= $group['is_private'] ? '' : 'display: none;' ?>">
                    <label for="join_code" class="form-label">Join Code</label>
                    <input type="text" class="form-control" id="join_code" name="join_code" 
                           value="<?= htmlspecialchars($group['join_code']) ?>">
                    <div class="form-text">Required for private groups</div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide join code field based on privacy checkbox
        document.getElementById('is_private').addEventListener('change', function() {
            document.getElementById('joinCodeContainer').style.display = this.checked ? 'block' : 'none';
        });
    </script>
</body>
</html>