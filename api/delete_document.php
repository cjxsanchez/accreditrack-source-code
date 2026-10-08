<?php
/**
 * Delete Document API
 * Removes file from server and record from database
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin(); // Ensure only admins can delete via this API

header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$document_id = $data['document_id'] ?? null;

if (!$document_id) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Document ID required']);
    exit;
}

try {
    $db = getDB();
    
    // 1. Get file path to delete physical file
    $query = "SELECT file_path FROM documents WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$document_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doc) {
        $filePath = dirname(__DIR__) . '/uploads/' . $doc['file_path'];
        
        // Delete physical file if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // 2. Delete from database
        $deleteQuery = "DELETE FROM documents WHERE id = ?";
        $deleteStmt = $db->prepare($deleteQuery);
        
        if ($deleteStmt->execute([$document_id])) {
            echo json_encode(['status' => 200, 'message' => 'Document deleted successfully']);
        } else {
            throw new Exception('Failed to delete database record');
        }
    } else {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Document not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>