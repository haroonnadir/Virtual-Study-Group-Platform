<?php
session_start();
include '../db_connect.php';

// Verify admin is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}


// Redirect if not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}


// Validate inputs
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['group_id'])) {
    $_SESSION['error'] = "Invalid request!";
    header("Location: admin_manage_groups.php");
    exit();
}

$group_id = (int)$_POST['group_id'];
$title = $conn->real_escape_string($_POST['title']);
$description = $conn->real_escape_string($_POST['description']);
$session_datetime = $conn->real_escape_string($_POST['session_datetime']);
$meeting_link = isset($_POST['meeting_link']) ? $conn->real_escape_string($_POST['meeting_link']) : null;

// Basic validation
if (empty($title) || empty($session_datetime)) {
    $_SESSION['error'] = "Title and date/time are required!";
    header("Location: admin_view_groupinfo.php?id=$group_id");
    exit();
}

/// Insert new session (includes created_by)
$stmt = $conn->prepare("INSERT INTO study_sessions (group_id, title, description, session_datetime, meeting_link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
$created_by = $_SESSION['user_id'];
$stmt->bind_param("issssi", $group_id, $title, $description, $session_datetime, $meeting_link, $created_by);

if ($stmt->execute()) {
    $_SESSION['message'] = "Session created successfully!";
} else {
    $_SESSION['error'] = "Error creating session: " . $stmt->error;
}

header("Location: admin_view_groupinfo.php?id=$group_id");
exit();
?>