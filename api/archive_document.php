<?php
/**
 * Archive Document API - Move document to archives
 */
require_once '../config/config.php';
requireStaff();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$documentId = $input['document_id'] ?? null;

if (!$documentId) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

$db = getDB();

try {
    // Get document details
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$documentId, $_SESSION['user_id']]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document not found or you do not have permission']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Insert into archived_documents table
    $stmt = $db->prepare("
        INSERT INTO archived_documents 
        (original_id, indicator_id, title, filename, file_path, status, folder_id, requirement_id, uploaded_by, archived_by, original_created_at, archived_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $document['id'],
        $document['indicator_id'],
        $document['title'],
        $document['filename'],
        $document['file_path'],
        $document['status'],
        $document['folder_id'],
        $document['requirement_id'],
        $document['uploaded_by'],
        $_SESSION['user_id'],
        $document['created_at']
    ]);
    
    // Delete from documents table
    $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Document archived successfully']);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error archiving document: ' . $e->getMessage()]);
}
?>
