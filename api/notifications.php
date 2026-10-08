<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    
    if ($action === 'mark_read') {
        try {
            $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    else if ($action === 'mark_single_read') {
        try {
            $notificationId = $input['id'] ?? null;
            if ($notificationId) {
                $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$notificationId, $_SESSION['user_id']]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
} else {
    // Get notifications with document_id for linking
    try {
        $query = "SELECT n.*, n.related_id as document_id 
                  FROM notifications n 
                  WHERE n.user_id = ? 
                  ORDER BY n.created_at DESC 
                  LIMIT 15";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
