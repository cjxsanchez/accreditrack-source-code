<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    try {
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        
        $count = $stmt->rowCount();
        
        echo json_encode(['success' => true, 'message' => "Marked $count notifications as read"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
