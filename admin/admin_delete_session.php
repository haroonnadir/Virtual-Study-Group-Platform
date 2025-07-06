<?php
session_start();
include '../db_connect.php';

// Verify admin is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit();
}

// Validate inputs
if (!isset($_GET['group_id']) || !isset($_GET['session_id'])) {
    $_SESSION['error'] = "Invalid request!";
    header("Location: admin_manage_groups.php");
    exit();
}

$group_id = (int)$_GET['group_id'];
$session_id = (int)$_GET['session_id'];

// Delete session
$stmt = $conn->prepare("DELETE FROM study_sessions WHERE id = ? AND group_id = ?");
$stmt->bind_param("ii", $session_id, $group_id);

if ($stmt->execute()) {
    $_SESSION['message'] = "Session deleted successfully!";
} else {
    $_SESSION['error'] = "Error deleting session: " . $conn->error;
}

header("Location: admin_view_groupinfo.php?id=$group_id");
exit();
?>