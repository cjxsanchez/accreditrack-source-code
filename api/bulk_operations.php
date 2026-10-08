<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;
$document_ids = $input['document_ids'] ?? [];
$status = $input['status'] ?? null;
$comment = sanitize($input['comment'] ?? '');

if (!$action || empty($document_ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    switch ($action) {
        case 'bulk_approve':
        case 'bulk_reject':
            $new_status = $action === 'bulk_approve' ? 'approved' : 'rejected';
            $placeholders = str_repeat('?,', count($document_ids) - 1) . '?';
            
            // Update documents
            $query = "UPDATE documents SET status = ?, admin_comments = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id IN ($placeholders)";
            $params = array_merge([$new_status, $comment, $_SESSION['user_id']], $document_ids);
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // Get document info for notifications
            $query = "SELECT d.uploaded_by, d.title, u.full_name FROM documents d JOIN users u ON d.uploaded_by = u.id WHERE d.id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $stmt->execute($document_ids);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Send notifications
            foreach ($docs as $doc) {
                $notification_title = "Document " . ucfirst($new_status);
                $notification_message = "Your document '{$doc['title']}' has been {$new_status}.";
                if ($comment) {
                    $notification_message .= " Comment: {$comment}";
                }
                
                $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $doc['uploaded_by'],
                    $notification_title,
                    $notification_message,
                    $new_status === 'approved' ? 'approval' : 'rejection'
                ]);
            }
            
            $message = count($document_ids) . " documents {$new_status} successfully";
            break;
            
        case 'bulk_delete':
            // Get file paths before deletion
            $placeholders = str_repeat('?,', count($document_ids) - 1) . '?';
            $query = "SELECT file_path FROM documents WHERE id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $stmt->execute($document_ids);
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete from database
            $query = "DELETE FROM documents WHERE id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $stmt->execute($document_ids);
            
            // Delete files
            foreach ($files as $file_path) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $message = count($document_ids) . " documents deleted successfully";
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
