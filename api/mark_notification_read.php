<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = $input['notification_id'] ?? null;
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        exit;
    }
    
    $db = getDB();
    
    try {
        $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
