<?php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

// Verify admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if ($group_id <= 0) {
    echo json_encode(['error' => 'Invalid group ID']);
    exit();
}

// Get new messages
$stmt = $conn->prepare("
    SELECT m.*, u.name as sender_name, u.profile_picture as sender_pic, gm.role as sender_role
    FROM group_messages m
    JOIN users u ON m.sender_id = u.id
    JOIN group_members gm ON u.id = gm.user_id AND gm.group_id = ?
    WHERE m.group_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iii", $group_id, $group_id, $last_message_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'content' => htmlspecialchars($row['content']),
        'sender_id' => $row['sender_id'],
        'sender_name' => htmlspecialchars($row['sender_name']),
        'sender_role' => $row['sender_role'],
        'is_announcement' => (bool)$row['is_announcement'],
        'media_path' => $row['media_path'],
        'created_at' => $row['created_at'],
        'edited_at' => $row['edited_at']
    ];
}

echo json_encode(['new_messages' => $messages]);