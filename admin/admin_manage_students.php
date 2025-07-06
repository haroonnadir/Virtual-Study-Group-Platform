<?php
session_start();

// Include the database connection file
require_once '../db_connect.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_NUMBER_INT);
        
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $conn->prepare("UPDATE users SET status = 'Active' WHERE id = ? AND role = 'students'");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $_SESSION['message'] = "Student approved successfully!";
                break;
                
            case 'ban':
                $stmt = $conn->prepare("UPDATE users SET status = 'Banned', is_active = 0 WHERE id = ? AND role = 'students'");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $_SESSION['message'] = "Student banned successfully!";
                break;
                
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'students'");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $_SESSION['message'] = "Student deleted successfully!";
                break;
                
            case 'activate':
                $stmt = $conn->prepare("UPDATE users SET status = 'Active', is_active = 1 WHERE id = ? AND role = 'students'");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $_SESSION['message'] = "Student activated successfully!";
                break;
        }
        
        header("Location: admin_manage_students.php");
        exit();
    }
}

// Search and filter functionality
$search = '';
$status_filter = '';
$where = "WHERE role = 'students'";
$params = [];
$param_types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $where .= " AND (name LIKE ? OR email LIKE ? OR cnic LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_fill(0, 4, $search_term);
    $param_types .= str_repeat('s', 4); // 4 string parameters
}

if (isset($_GET['status']) && in_array($_GET['status'], ['Active', 'Pending', 'Banned'])) {
    $status_filter = $_GET['status'];
    $where .= " AND status = ?";
    $params[] = $status_filter;
    $param_types .= 's'; // 1 string parameter
}

// Get total students count for pagination
$count_sql = "SELECT COUNT(*) FROM users $where";
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}

$count_stmt->execute();
$result = $count_stmt->get_result();
$total_students = $result->fetch_row()[0];
$count_stmt->close();

// Pagination setup
$per_page = 10;
$total_pages = ceil($total_students / $per_page);
$current_page = isset($_GET['page']) ? max(1, min($total_pages, (int)$_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get students with pagination
$sql = "SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii'; // 2 integer parameters

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 50px;
        }
        .Pending {
            background-color: #ffc107;
            color: #000;
        }
        .Active {
            background-color: #28a745;
            color: #fff;
        }
        .Banned {
            background-color: #dc3545;
            color: #fff;
        }
        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>   
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill"></i> Manage Students</h2>
            <!-- <a href="admin_create_student.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Add Student
            </a> -->
            <a href="admin_dashboard.php" class="btn btn-secondary mb-3">
                &larr; Go Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Banned" <?= $status_filter === 'Banned' ? 'selected' : '' ?>>Banned</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                        <a href="admin_manage_students.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Student Accounts</h5>
                    <span class="badge bg-primary">Total: <?= $total_students ?></span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="alert alert-info">No students found matching your criteria.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= $student['id'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($student['profile_picture']): ?>
                                                    <img src="<?= htmlspecialchars($student['profile_picture']) ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                        <i class="bi bi-person text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($student['name']) ?></strong>
                                                    <div class="text-muted small"><?= htmlspecialchars($student['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($student['phone']) ?></div>
                                            <div class="text-muted small">CNIC: <?= htmlspecialchars($student['cnic']) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($student['town']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($student['country']) ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $student['status'] ?>">
                                                <?= $student['status'] ?>
                                                <?php if (!$student['is_active']): ?>
                                                    <span class="text-danger">(Inactive)</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($student['created_at'])) ?>
                                            <div class="text-muted small">
                                                Age: <?= $student['age'] ?>
                                            </div>
                                        </td>
                                        <td class="action-btns">
                                            <div class="d-flex gap-1">
                                                <a href="admin_view_student.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php if ($student['status'] === 'Pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" title="Approve">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($student['status'] === 'Active' && $student['is_active']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                        <button type="submit" name="action" value="ban" class="btn btn-sm btn-warning" title="Ban">
                                                            <i class="bi bi-slash-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($student['status'] === 'Banned' || !$student['is_active']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                        <button type="submit" name="action" value="activate" class="btn btn-sm btn-success" title="Activate">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this student?');">
                                                    <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>