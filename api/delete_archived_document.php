<?php
/**
 * Delete Archived Document API - Permanently delete archived document
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
    
    // Delete the physical file
    $filePath = '../' . $archived['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM archived_documents WHERE id = ?");
    $stmt->execute([$archiveId]);
    
    echo json_encode(['success' => true, 'message' => 'Document permanently deleted']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting document: ' . $e->getMessage()]);
}
?>
