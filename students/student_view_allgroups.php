<?php
session_start();
require_once '../db_connect.php'; // Changed from include to require_once

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Define user_id from session

// Handle join group request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group'])) {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $join_code = isset($_POST['join_code']) ? trim($_POST['join_code']) : '';
    
    try {
        // Check if group exists and is joinable
        $stmt = $conn->prepare("SELECT * FROM study_groups WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        
        if ($group) {
            if ($group['is_private'] && $group['join_code'] !== $join_code) {
                $_SESSION['error'] = "Invalid join code for private group!";
            } else {
                // Check if already a member
                $stmt = $conn->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $group_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['error'] = "You are already a member of this group!";
                } else {
                    // Add as member
                    $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
                    $stmt->bind_param("ii", $group_id, $user_id);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Successfully joined the group!";
                    } else {
                        $_SESSION['error'] = "Failed to join the group.";
                    }
                }
            }
        } else {
            $_SESSION['error'] = "Group not found!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }
    
    header("Location: student_view_allgroups.php");
    exit();
}

// Get all groups with member counts and join status
$groups_query = "
    SELECT sg.*, 
           COUNT(gm.user_id) as member_count,
           MAX(CASE WHEN gm.user_id = ? THEN 1 ELSE 0 END) as is_member
    FROM study_groups sg
    LEFT JOIN group_members gm ON sg.id = gm.group_id
    GROUP BY sg.id
    ORDER BY sg.created_at DESC
";

$stmt = $conn->prepare($groups_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$groups = $result->fetch_all(MYSQLI_ASSOC);

// Get groups the student is member of
$my_groups_query = "
    SELECT sg.*, gm.role
    FROM study_groups sg
    JOIN group_members gm ON sg.id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY sg.name
";

$stmt = $conn->prepare($my_groups_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$my_groups = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-body {
            padding: 1.25rem;
        }

        .card-text {
            margin-bottom: 1.5rem;
            color: #333;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .d-grid {
            display: grid;
            gap: 0.75rem;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-exit {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-exit:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }

        .bi {
            font-size: 1rem;
        }
        
        .card-header {
            background-color: #0d6efd;
            color: #fff;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-bottom: 2px solid #0a58ca;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header a {
            color: #ffc107;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease-in-out;
        }

        .card-header a:hover {
            color: #fff;
            text-decoration: underline;
        }

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
        .member-badge {
            background-color: #0d6efd;
        }
        .owner-badge {
            background-color: #fd7e14;
        }
        
        /* Added for better responsive behavior */
        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div><h3 class="mb-0"><i class="bi bi-people-fill"></i> My Study Groups</h3></div>
                        <div><a href="./students_dashboard.php" class="btn btn-warning text-dark">Go Back Dashboard</a></div>
                    </div>

                    <div class="card-body">
                        <?php if (empty($my_groups)): ?>
                            <div class="alert alert-info">You haven't joined any groups yet.</div>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php foreach ($my_groups as $group): ?>
                                    <div class="col">
                                        <div class="card group-card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($group['name']); ?></h5>
                                                <span class="badge <?php echo $group['role'] === 'owner' ? 'owner-badge' : 'member-badge'; ?>">
                                                    <?php echo ucfirst($group['role']); ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo htmlspecialchars($group['description']); ?></p>
                                                <div class="d-grid">
                                                    <a href="student_enter_group.php?id=<?php echo $group['id']; ?>" class="btn btn-primary">
                                                        <i class="bi bi-box-arrow-in-right"></i> Enter Group
                                                    </a>
                                                    <?php if ($group['role'] !== 'owner'): ?>
                                                        <a href="student_exit_group.php?id=<?php echo $group['id']; ?>" class="btn btn-outline-danger">
                                                            <i class="bi bi-door-open"></i> Leave Group
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled>
                                                            <i class="bi bi-shield-lock"></i> Owner (Cannot Leave)
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="mb-0"><i class="bi bi-search"></i> Browse All Groups</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($groups)): ?>
                            <div class="alert alert-info">No study groups available at the moment.</div>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php foreach ($groups as $group): ?>
                                    <div class="col">
                                        <div class="card group-card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($group['name']); ?></h5>
                                                <span class="badge <?php echo $group['is_private'] ? 'private-badge' : 'public-badge'; ?>">
                                                    <?php echo $group['is_private'] ? 'Private' : 'Public'; ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo htmlspecialchars($group['description']); ?></p>
                                                <ul class="list-group list-group-flush mb-3">
                                                    <li class="list-group-item">
                                                        <i class="bi bi-book"></i> Subject: <?php echo htmlspecialchars($group['subject']); ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <i class="bi bi-people"></i> Members: <?php echo $group['member_count']; ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <i class="bi bi-calendar"></i> Created: <?php echo date('M d, Y', strtotime($group['created_at'])); ?>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <?php if ($group['is_member']): ?>
                                                    <button class="btn btn-success w-100" disabled>
                                                        <i class="bi bi-check-circle"></i> Already Joined
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                        <?php if ($group['is_private']): ?>
                                                            <div class="mb-3">
                                                                <label for="join_code_<?php echo $group['id']; ?>" class="form-label">Join Code</label>
                                                                <input type="text" class="form-control" id="join_code_<?php echo $group['id']; ?>" name="join_code" required>
                                                            </div>
                                                        <?php endif; ?>
                                                        <button type="submit" name="join_group" class="btn btn-primary w-100">
                                                            <i class="bi bi-plus-circle"></i> Join Group
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>