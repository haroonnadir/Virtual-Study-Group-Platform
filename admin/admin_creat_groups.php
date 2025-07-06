<?php
session_start();
require_once '../db_connect.php';

// Redirect if not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Initialize variables to null
$check_stmt = $stmt = $stmt2 = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "CSRF token validation failed";
    } else {
        // Validate and sanitize inputs
        $name = trim(htmlspecialchars($_POST['name']));
        $description = trim(htmlspecialchars($_POST['description']));
        $subject = trim(htmlspecialchars($_POST['subject']));
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        $join_code = $is_private ? bin2hex(random_bytes(4)) : NULL;
        $created_by = $_SESSION['user_id'];

        // Validate inputs
        $validation_errors = [];
        
        if (empty($name)) {
            $validation_errors[] = "Group name is required";
        } elseif (strlen($name) > 100) {
            $validation_errors[] = "Group name must be less than 100 characters";
        } elseif (!preg_match('/^[\w\s\-]+$/', $name)) {
            $validation_errors[] = "Group name can only contain letters, numbers, spaces and hyphens";
        }

        if (empty($description)) {
            $validation_errors[] = "Description is required";
        } elseif (strlen($description) > 500) {
            $validation_errors[] = "Description must be less than 500 characters";
        } elseif (strlen($description) < 20) {
            $validation_errors[] = "Description must be at least 20 characters";
        }

        if (empty($subject)) {
            $validation_errors[] = "Subject is required";
        } elseif (strlen($subject) > 50) {
            $validation_errors[] = "Subject must be less than 50 characters";
        }

        if (!empty($validation_errors)) {
            $error = implode("<br>", $validation_errors);
        } else {
            mysqli_begin_transaction($conn);
            
            try {
                // Check if group name already exists
                $check_stmt = mysqli_prepare($conn, "SELECT id FROM study_groups WHERE name = ?");
                if (!$check_stmt) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($check_stmt, 's', $name);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    throw new Exception("A group with this name already exists");
                }
                
                // Close the check statement before proceeding
                if ($check_stmt) {
                    mysqli_stmt_close($check_stmt);
                    $check_stmt = null;
                }

                // Insert group
                $stmt = mysqli_prepare($conn, "INSERT INTO study_groups 
                    (name, description, subject, created_by, is_private, join_code, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'sssiis', $name, $description, $subject, $created_by, $is_private, $join_code);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
                }
                
                $group_id = mysqli_insert_id($conn);
                
                // Add creator as owner
                $stmt2 = mysqli_prepare($conn, "INSERT INTO group_members 
                    (group_id, user_id, role, joined_at) 
                    VALUES (?, ?, 'owner', NOW())");
                    
                if (!$stmt2) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt2, 'ii', $group_id, $created_by);
                
                if (!mysqli_stmt_execute($stmt2)) {
                    throw new Exception("Execute failed: " . mysqli_stmt_error($stmt2));
                }
                
                mysqli_commit($conn);
                $_SESSION['success'] = "Group created successfully!";
                if ($is_private) {
                    $_SESSION['success'] .= "<br>Join code: <strong>" . htmlspecialchars($join_code) . "</strong>";
                }
                header("Location: admin_view_allgroups.php");
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = $e->getMessage();
                error_log("Group creation error: " . $e->getMessage());
            } finally {
                // Close statements only if they exist and haven't been closed already
                if ($check_stmt !== null) {
                    mysqli_stmt_close($check_stmt);
                }
                if ($stmt !== null) {
                    mysqli_stmt_close($stmt);
                }
                if ($stmt2 !== null) {
                    mysqli_stmt_close($stmt2);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Study Group - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.25rem 1.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-check-input {
            width: 1.3em;
            height: 1.3em;
            margin-top: 0.15em;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline-secondary {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.85rem;
        }
        
        .character-counter {
            font-size: 0.8rem;
            color: var(--secondary-color);
            text-align: right;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .card-header {
                padding: 1rem;
            }
            
            .btn, .form-control {
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Study Group</h3>
                            <a href="admin_view_allgroups.php" class="btn btn-sm btn-light">
                                <i class="bi bi-arrow-left me-1"></i>Back to Groups
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= $_SESSION['success'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="mb-4">
                                <label for="name" class="form-label">Group Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required maxlength="100"
                                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                                <div class="invalid-feedback">Please provide a valid group name (max 100 characters).</div>
                                <div class="character-counter"><span id="name-counter">0</span>/100</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" required maxlength="500"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                                <div class="invalid-feedback">Please provide a meaningful description (20-500 characters).</div>
                                <div class="character-counter"><span id="description-counter">0</span>/500</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" required maxlength="50"
                                       value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>">
                                <div class="invalid-feedback">Please provide a subject (max 50 characters).</div>
                                <div class="character-counter"><span id="subject-counter">0</span>/50</div>
                            </div>
                            
                            <div class="mb-4 form-check form-switch ps-0">
                                <div class="d-flex align-items-center">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="is_private" name="is_private"
                                        <?= isset($_POST['is_private']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_private">Make this a private group (requires join code)</label>
                                </div>
                                <div class="form-text mt-1">Private groups require a code to join. The code will be generated automatically.</div>
                            </div>
                            
                            <div class="d-grid gap-3 d-md-flex justify-content-md-end mt-4">
                                <a href="admin_view_allgroups.php" class="btn btn-outline-secondary btn-lg me-md-2">
                                    <i class="bi bi-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save me-2"></i>Create Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Character counters with debounce
        function setupCounter(elementId, maxLength) {
            const element = document.getElementById(elementId);
            const counter = document.getElementById(`${elementId}-counter`);
            
            const updateCounter = () => {
                counter.textContent = element.value.length;
                if (element.value.length > maxLength * 0.9) {
                    counter.style.color = '#dc3545';
                } else {
                    counter.style.color = '#6c757d';
                }
            };
            
            // Initial update
            updateCounter();
            
            // Update on input with debounce
            let timeout;
            element.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(updateCounter, 300);
            });
        }

        // Initialize all counters
        document.addEventListener('DOMContentLoaded', () => {
            setupCounter('name', 100);
            setupCounter('description', 500);
            setupCounter('subject', 50);
            
            // Persist form data on page refresh
            if (window.history.replaceState && window.performance.navigation.type === 1) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>