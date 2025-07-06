<?php
require_once 'db_connect.php';

// Get sessions starting in the next 1 hour that have reminders set
$now = date('Y-m-d H:i:s');
$one_hour_later = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt = $conn->prepare("
    SELECT s.id, s.title, s.session_datetime, s.group_id, sr.user_id
    FROM sessions s
    JOIN session_reminders sr ON s.id = sr.session_id
    WHERE s.session_datetime BETWEEN ? AND ?
    AND sr.reminder_sent = 0
");
$stmt->bind_param("ss", $now, $one_hour_later);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($sessions as $session) {
    // Create notification
    $message = "Reminder: Session '" . $session['title'] . "' starts at " . 
               date('h:i A', strtotime($session['session_datetime']));
    
    $notification_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, group_id, message) 
        VALUES (?, ?, ?)
    ");
    $notification_stmt->bind_param("iis", 
        $session['user_id'], 
        $session['group_id'], 
        $message
    );
    $notification_stmt->execute();
    
    // Mark reminder as sent
    $update_stmt = $conn->prepare("
        UPDATE session_reminders 
        SET reminder_sent = 1 
        WHERE session_id = ? AND user_id = ?
    ");
    $update_stmt->bind_param("ii", $session['id'], $session['user_id']);
    $update_stmt->execute();
    
    // Here you could also send email notifications if desired
}