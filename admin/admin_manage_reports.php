<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get admin ID from session
$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';
$reports = [];

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $report_type = $_POST['report_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        // Validate inputs
        if (empty($report_type)) {
            $error = 'Please select a report type.';
        } else {
            $data = '';
            
            // Generate different reports based on type
            switch ($report_type) {
                case 'user_activity':
                    $data = generateUserActivityReport($conn, $start_date, $end_date);
                    break;
                case 'group_engagement':
                    $data = generateGroupEngagementReport($conn, $start_date, $end_date);
                    break;
                case 'resource_downloads':
                    $data = generateResourceDownloadsReport($conn, $start_date, $end_date);
                    break;
                case 'system_usage':
                    $data = generateSystemUsageReport($conn, $start_date, $end_date);
                    break;
                default:
                    $error = 'Invalid report type selected.';
                    break;
            }
            
            if (!$error && !empty($data)) {
                // Save the report to database
                $stmt = $conn->prepare("INSERT INTO reports (admin_id, report_type, start_date, end_date, data) 
                                      VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("issss", $admin_id, $report_type, $start_date, $end_date, $data);
                    if ($stmt->execute()) {
                        $success = 'Report generated successfully!';
                    } else {
                        $error = 'Failed to save report: ' . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare statement: ' . $conn->error;
                }
            }
        }
    }
}

// Fetch all reports for display
$query = "SELECT r.*, u.name as admin_name 
          FROM reports r
          JOIN users u ON r.admin_id = u.id
          ORDER BY r.generated_at DESC";
$result = $conn->query($query);
if ($result) {
    $reports = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} else {
    $error = 'Failed to fetch reports: ' . $conn->error;
}

// Report generation functions
function generateUserActivityReport($conn, $start_date, $end_date) {
    $where = '';
    $params = [];
    $types = '';
    
    if (!empty($start_date) && !empty($end_date)) {
        $where = "WHERE u.created_at BETWEEN ? AND ?";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        $types = 'ss';
    }
    
    $query = "SELECT 
              u.id, u.name, u.email, u.role, u.status,
              COUNT(DISTINCT gm.group_id) as groups_joined,
              COUNT(DISTINCT CASE WHEN gm.role = 'owner' THEN gm.group_id END) as groups_owned,
              COUNT(DISTINCT d.id) as discussions_started,
              COUNT(DISTINCT r.id) as replies_posted,
              COUNT(DISTINCT m.id) as messages_sent
              FROM users u
              LEFT JOIN group_members gm ON u.id = gm.user_id
              LEFT JOIN discussions d ON u.id = d.user_id
              LEFT JOIN replies r ON u.id = r.user_id
              LEFT JOIN group_messages m ON u.id = m.sender_id
              $where
              GROUP BY u.id
              ORDER BY u.name";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return json_encode([
        'type' => 'user_activity',
        'period' => ['start' => $start_date, 'end' => $end_date],
        'total_users' => count($users),
        'data' => $users
    ], JSON_PRETTY_PRINT);
}

function generateGroupEngagementReport($conn, $start_date, $end_date) {
    $where = '';
    $params = [];
    $types = '';
    
    if (!empty($start_date)) {
        $where = "WHERE g.created_at >= ?";
        $params = [$start_date . ' 00:00:00'];
        $types = 's';
    }
    
    if (!empty($end_date)) {
        $where .= empty($where) ? "WHERE " : " AND ";
        $where .= "g.created_at <= ?";
        $params[] = $end_date . ' 23:59:59';
        $types .= 's';
    }
    
    $query = "SELECT 
              g.id, g.name, g.subject, g.is_private,
              COUNT(DISTINCT gm.user_id) as member_count,
              COUNT(DISTINCT d.id) as discussion_count,
              COUNT(DISTINCT m.id) as message_count,
              COUNT(DISTINCT s.id) as session_count
              FROM study_groups g
              LEFT JOIN group_members gm ON g.id = gm.group_id
              LEFT JOIN discussions d ON g.id = d.group_id
              LEFT JOIN group_messages m ON g.id = m.group_id
              LEFT JOIN study_sessions s ON g.id = s.group_id
              $where
              GROUP BY g.id
              ORDER BY member_count DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return json_encode([
        'type' => 'group_engagement',
        'period' => ['start' => $start_date, 'end' => $end_date],
        'total_groups' => count($groups),
        'data' => $groups
    ], JSON_PRETTY_PRINT);
}

function generateResourceDownloadsReport($conn, $start_date, $end_date) {
    // Check if resources table exists
    $result = $conn->query("SHOW TABLES LIKE 'resources'");
    if ($result->num_rows > 0) {
        $where = '';
        $params = [];
        $types = '';
        
        if (!empty($start_date) && !empty($end_date)) {
            $where = "WHERE download_date BETWEEN ? AND ?";
            $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
            $types = 'ss';
        }
        
        $query = "SELECT 
                  r.id, r.title, r.type, r.file_size,
                  COUNT(d.id) as download_count,
                  COUNT(DISTINCT d.user_id) as unique_users
                  FROM resources r
                  LEFT JOIN downloads d ON r.id = d.resource_id
                  $where
                  GROUP BY r.id
                  ORDER BY download_count DESC";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $resources = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return json_encode([
            'type' => 'resource_downloads',
            'period' => ['start' => $start_date, 'end' => $end_date],
            'total_resources' => count($resources),
            'total_downloads' => array_sum(array_column($resources, 'download_count')),
            'data' => $resources
        ], JSON_PRETTY_PRINT);
    } else {
        return json_encode([
            'type' => 'resource_downloads',
            'period' => ['start' => $start_date, 'end' => $end_date],
            'message' => 'Resource tracking not implemented in current schema'
        ], JSON_PRETTY_PRINT);
    }
}

function generateSystemUsageReport($conn, $start_date, $end_date) {
    // Initialize variables
    $where = '';
    $params = [];
    $types = '';
    
    // Format dates if provided
    $start_date_formatted = !empty($start_date) ? $start_date . ' 00:00:00' : null;
    $end_date_formatted = !empty($end_date) ? $end_date . ' 23:59:59' : null;
    
    // Get user counts by role
    $query_users = "SELECT role, COUNT(*) as count FROM users";
    $where_users = '';
    $params_users = [];
    $types_users = '';
    
    if (!empty($start_date_formatted) && !empty($end_date_formatted)) {
        $where_users = " WHERE created_at BETWEEN ? AND ?";
        $params_users = [$start_date_formatted, $end_date_formatted];
        $types_users = 'ss';
    } elseif (!empty($start_date_formatted)) {
        $where_users = " WHERE created_at >= ?";
        $params_users = [$start_date_formatted];
        $types_users = 's';
    } elseif (!empty($end_date_formatted)) {
        $where_users = " WHERE created_at <= ?";
        $params_users = [$end_date_formatted];
        $types_users = 's';
    }
    
    $stmt = $conn->prepare($query_users . $where_users);
    if (!empty($params_users)) {
        $stmt->bind_param($types_users, ...$params_users);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $user_counts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get group counts
    $query_groups = "SELECT 
                    COUNT(*) as total_groups,
                    SUM(is_private) as private_groups,
                    COUNT(*) - SUM(is_private) as public_groups
                    FROM study_groups";
    $where_groups = '';
    $params_groups = [];
    $types_groups = '';
    
    if (!empty($start_date_formatted) && !empty($end_date_formatted)) {
        $where_groups = " WHERE created_at BETWEEN ? AND ?";
        $params_groups = [$start_date_formatted, $end_date_formatted];
        $types_groups = 'ss';
    } elseif (!empty($start_date_formatted)) {
        $where_groups = " WHERE created_at >= ?";
        $params_groups = [$start_date_formatted];
        $types_groups = 's';
    } elseif (!empty($end_date_formatted)) {
        $where_groups = " WHERE created_at <= ?";
        $params_groups = [$end_date_formatted];
        $types_groups = 's';
    }
    
    $stmt = $conn->prepare($query_groups . $where_groups);
    if (!empty($params_groups)) {
        $stmt->bind_param($types_groups, ...$params_groups);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $group_counts = $result->fetch_assoc();
    $stmt->close();
    
    // Get activity counts
    $tables = [
        'discussions' => "SELECT COUNT(*) as count FROM discussions",
        'replies' => "SELECT COUNT(*) as count FROM replies", 
        'group_messages' => "SELECT COUNT(*) as count FROM group_messages",
        'study_sessions' => "SELECT COUNT(*) as count FROM study_sessions"
    ];
    
    $activity_counts = [];
    foreach ($tables as $table => $query) {
        // Check if table has created_at column
        $has_created_at = false;
        $check_col = $conn->query("SHOW COLUMNS FROM $table LIKE 'created_at'");
        if ($check_col && $check_col->num_rows > 0) {
            $has_created_at = true;
        }
        
        $where_activity = '';
        $params_activity = [];
        $types_activity = '';
        
        if ($has_created_at) {
            if (!empty($start_date_formatted) && !empty($end_date_formatted)) {
                $where_activity = " WHERE created_at BETWEEN ? AND ?";
                $params_activity = [$start_date_formatted, $end_date_formatted];
                $types_activity = 'ss';
            } elseif (!empty($start_date_formatted)) {
                $where_activity = " WHERE created_at >= ?";
                $params_activity = [$start_date_formatted];
                $types_activity = 's';
            } elseif (!empty($end_date_formatted)) {
                $where_activity = " WHERE created_at <= ?";
                $params_activity = [$end_date_formatted];
                $types_activity = 's';
            }
        }
        
        $stmt = $conn->prepare($query . $where_activity);
        if (!empty($params_activity)) {
            $stmt->bind_param($types_activity, ...$params_activity);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $activity_counts[$table] = $result->fetch_assoc()['count'];
        $stmt->close();
    }
    
    return json_encode([
        'type' => 'system_usage',
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'user_counts' => $user_counts,
        'group_counts' => $group_counts,
        'activity_counts' => [
            'discussions' => $activity_counts['discussions'] ?? 0,
            'replies' => $activity_counts['replies'] ?? 0,
            'messages' => $activity_counts['group_messages'] ?? 0,
            'sessions' => $activity_counts['study_sessions'] ?? 0
        ]
    ], JSON_PRETTY_PRINT);
}

// Handle report deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $report_id = $_POST['report_id'] ?? 0;
        
        if ($report_id > 0) {
            $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $report_id);
                if ($stmt->execute()) {
                    $success = 'Report deleted successfully!';
                    
                    // Refresh the reports list
                    $query = "SELECT r.*, u.name as admin_name 
                             FROM reports r
                             JOIN users u ON r.admin_id = u.id
                             ORDER BY r.generated_at DESC";
                    $result = $conn->query($query);
                    $reports = $result->fetch_all(MYSQLI_ASSOC);
                    $result->free();
                } else {
                    $error = 'Failed to delete report: ' . $conn->error;
                }
                $stmt->close();
            } else {
                $error = 'Failed to prepare statement: ' . $conn->error;
            }
        } else {
            $error = 'Invalid report ID.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="admin_view_report" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - Virtual Study Group</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Admin Reports Management - Complete CSS */
        :root {
            --primary-color: #3498db;
            --primary-hover: #2980b9;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
            --warning-color: #f39c12;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --gray-color: #95a5a6;
            --white-color: #ffffff;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 6px 12px rgba(0, 0, 0, 0.15);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            padding: 0;
            margin: 0;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Typography */
        h1, h2, h3, h4 {
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        h1 {
            font-size: 2.2rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h2 {
            font-size: 1.8rem;
            margin-top: 2rem;
        }

        h3 {
            font-size: 1.4rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .alert-error {
            background-color: #fdecea;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-success {
            background-color: #e8f5e9;
            color: var(--secondary-color);
            border-left: 4px solid var(--secondary-color);
        }

        /* Forms */
        .report-form {
            background: var(--white-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-row {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--danger-hover);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Report Cards Layout */
        .report-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        .report-card {
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            background-color: var(--white-color);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .report-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding-bottom: 0.625rem;
            border-bottom: 1px solid #eee;
        }

        .report-card p {
            margin-bottom: 0.5rem;
            color: #555;
            font-size: 0.95rem;
        }

        .report-card strong {
            color: var(--dark-color);
            font-weight: 600;
        }

        .report-actions {
            margin-top: auto;
            padding-top: 1rem;
            display: flex;
            justify-content: space-between;
            gap: 0.625rem;
        }

        .report-actions form {
            flex: 1;
        }

        /* Dashboard Link */
        .dashboard-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .dashboard-link:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Chart Container */
        .chart-container {
            width: 100%;
            max-width: 800px;
            margin: 2rem auto;
            background: var(--white-color);
            padding: 1.25rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .report-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .report-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 600px) {
            .report-container {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
            
            h1 {
                font-size: 1.8rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        /* Icons */
        .fas {
            font-size: 0.9em;
        }
    </style>
</head>
<body>  
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0;">Manage Reports</h1>
            </div>
            <div>
                <a href="./admin_dashboard.php" 
                    style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-family: Arial, sans-serif; transition: background-color 0.3s ease;" 
                    onmouseover="this.style.backgroundColor='#0056b3'" 
                    onmouseout="this.style.backgroundColor='#007bff'">
                    Go Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="report-form">
            <h2>Generate New Report</h2>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="report_type">Report Type:</label>
                    <select id="report_type" name="report_type" required>
                        <option value="">-- Select Report Type --</option>
                        <option value="user_activity">User Activity</option>
                        <option value="group_engagement">Group Engagement</option>
                        <option value="resource_downloads">Resource Downloads</option>
                        <option value="system_usage">System Usage</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date">
                    </div>
                </div>
                
                <button type="submit" name="generate_report" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
            </form>
        </div>
        
        <h2>Generated Reports</h2>
        <?php if (empty($reports)): ?>
            <p>No reports have been generated yet.</p>
        <?php else: ?>
            <div class="report-container">
                <?php foreach ($reports as $report): 
                    $data = json_decode($report['data'], true);
                    $icon = '';
                    $title = '';
                    
                    switch ($report['report_type']) {
                        case 'user_activity':
                            $icon = 'users';
                            $title = 'User Activity';
                            $summary = isset($data['total_users']) ? $data['total_users'] . ' users' : 'N/A';
                            break;
                        case 'group_engagement':
                            $icon = 'users';
                            $title = 'Group Engagement';
                            $summary = isset($data['total_groups']) ? $data['total_groups'] . ' groups' : 'N/A';
                            break;
                        case 'resource_downloads':
                            $icon = 'file-download';
                            $title = 'Resource Downloads';
                            $summary = isset($data['total_downloads']) ? $data['total_downloads'] . ' downloads' : 'N/A';
                            break;
                        case 'system_usage':
                            $icon = 'server';
                            $title = 'System Usage';
                            $summary = 'System statistics';
                            break;
                        default:
                            $icon = 'file-alt';
                            $title = 'Report';
                            $summary = '';
                    }
                ?>
                    <div class="report-card">
                        <h3><i class="fas fa-<?php echo $icon; ?>"></i> <?php echo htmlspecialchars($title); ?></h3>
                        <p><strong>Period:</strong> 
                            <?php echo $report['start_date'] ? htmlspecialchars($report['start_date']) : 'N/A'; ?> 
                            to 
                            <?php echo $report['end_date'] ? htmlspecialchars($report['end_date']) : 'N/A'; ?>
                        </p>
                        <p><strong>Summary:</strong> <?php echo htmlspecialchars($summary); ?></p>
                        <p><strong>Generated by:</strong> <?php echo htmlspecialchars($report['admin_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('M j, Y g:i a', strtotime($report['generated_at'])); ?></p>
                        
                        <div class="report-actions">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <button type="submit" name="delete_report" class="btn btn-sm btn-danger" 
                                        onclick="return confirm('Are you sure you want to delete this report?');">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>