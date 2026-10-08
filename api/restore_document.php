<?php
/**
 * Restore Document API - Move document back from archives
 */
require_once '../config/config.php';
requireStaff();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$archiveId = $input['archive_id'] ?? null;

if (!$archiveId) {
    echo json_encode(['success' => false, 'message' => 'Archive ID is required']);
    exit;
}

$db = getDB();

try {
    // Get archived document
    $stmt = $db->prepare("SELECT * FROM archived_documents WHERE id = ? AND archived_by = ?");
    $stmt->execute([$archiveId, $_SESSION['user_id']]);
    $archived = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$archived) {
        echo json_encode(['success' => false, 'message' => 'Archived document not found']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Insert back into documents table
    $stmt = $db->prepare("
        INSERT INTO documents 
        (indicator_id, title, filename, file_path, status, folder_id, requirement_id, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $archived['indicator_id'],
        $archived['title'],
        $archived['filename'],
        $archived['file_path'],
        $archived['status'],
        $archived['folder_id'],
        $archived['requirement_id'],
        $archived['uploaded_by'],
        $archived['original_created_at']
    ]);
    
    // Delete from archived_documents
    $stmt = $db->prepare("DELETE FROM archived_documents WHERE id = ?");
    $stmt->execute([$archiveId]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Document restored successfully']);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error restoring document: ' . $e->getMessage()]);
}
?>
