<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$document_id = $input['document_id'] ?? null;
$status = $input['status'] ?? null;
$comment = sanitize($input['comment'] ?? '');

if (!$document_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($status, ['approved', 'rejected', 'edited'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Update document status
    $query = "UPDATE documents SET status = ?, admin_comments = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$status, $comment, $_SESSION['user_id'], $document_id]);
    
    // Get document info for notification
    $query = "SELECT d.uploaded_by, d.title, u.full_name FROM documents d JOIN users u ON d.uploaded_by = u.id WHERE d.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$document_id]);
    $doc_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc_info) {
        // Send notification to uploader
        $notification_title = "Document " . ucfirst($status);
        $notification_message = "Your document '{$doc_info['title']}' has been {$status}.";
        if ($comment) {
            $notification_message .= " Comment: {$comment}";
        }
        
        $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $doc_info['uploaded_by'],
            $notification_title,
            $notification_message,
            $status == 'approved' ? 'approval' : 'rejection'
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => "Document {$status} successfully"]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
