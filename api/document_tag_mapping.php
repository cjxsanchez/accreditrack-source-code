<?php
/**
 * Document Tag Mapping API
 * Handles mapping documents to their selected tags
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDB();

try {
    switch ($action) {
        case 'add_tag_to_document':
            addTagToDocument();
            break;
            
        case 'remove_tag_from_document':
            removeTagFromDocument();
            break;
            
        case 'get_document_tags':
            getDocumentTags();
            break;
            
        case 'map_tags_to_document':
            mapTagsToDocument();
            break;
            
        default:
            jsonResponse([], 400, 'Invalid action');
    }
} catch (Exception $e) {
    logMessage('Document tag mapping error: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error: ' . $e->getMessage());
}

/**
 * Add a tag to a document
 */
function addTagToDocument() {
    global $db;
    requireStaff();
    
    $document_id = $_POST['document_id'] ?? null;
    $tag_id = $_POST['tag_id'] ?? null;
    
    if (!$document_id || !$tag_id) {
        jsonResponse([], 400, 'Document ID and Tag ID required');
        return;
    }
    
    // Verify document belongs to user
    $query = "SELECT uploaded_by FROM documents WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$document_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc || $doc['uploaded_by'] != $_SESSION['user_id']) {
        jsonResponse([], 403, 'Unauthorized');
        return;
    }
    
    // Insert tag mapping
    $query = "INSERT IGNORE INTO document_tags (document_id, accreditation_tag_id) VALUES (?, ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$document_id, $tag_id])) {
        jsonResponse([], 201, 'Tag added to document');
    } else {
        jsonResponse([], 500, 'Failed to add tag');
    }
}

/**
 * Remove a tag from a document
 */
function removeTagFromDocument() {
    global $db;
    requireStaff();
    
    $document_id = $_POST['document_id'] ?? null;
    $tag_id = $_POST['tag_id'] ?? null;
    
    if (!$document_id || !$tag_id) {
        jsonResponse([], 400, 'Document ID and Tag ID required');
        return;
    }
    
    // Verify document belongs to user
    $query = "SELECT uploaded_by FROM documents WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$document_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc || $doc['uploaded_by'] != $_SESSION['user_id']) {
        jsonResponse([], 403, 'Unauthorized');
        return;
    }
    
    // Delete tag mapping
    $query = "DELETE FROM document_tags WHERE document_id = ? AND accreditation_tag_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$document_id, $tag_id])) {
        jsonResponse([], 200, 'Tag removed from document');
    } else {
        jsonResponse([], 500, 'Failed to remove tag');
    }
}

/**
 * Get all tags for a document
 */
function getDocumentTags() {
    global $db;
    
    $document_id = $_GET['document_id'] ?? null;
    
    if (!$document_id) {
        jsonResponse([], 400, 'Document ID required');
        return;
    }
    
    $query = "SELECT at.* FROM accreditation_tags at
              JOIN document_tags dt ON at.id = dt.accreditation_tag_id
              WHERE dt.document_id = ? AND at.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute([$document_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($tags, 200, 'Document tags retrieved');
}

/**
 * Map multiple tags to a document
 */
function mapTagsToDocument() {
    global $db;
    requireStaff();
    
    $document_id = $_POST['document_id'] ?? null;
    $tag_ids = $_POST['tag_ids'] ?? [];
    
    if (!$document_id || empty($tag_ids)) {
        jsonResponse([], 400, 'Document ID and tag IDs required');
        return;
    }
    
    // Verify document belongs to user
    $query = "SELECT uploaded_by FROM documents WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$document_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc || $doc['uploaded_by'] != $_SESSION['user_id']) {
        jsonResponse([], 403, 'Unauthorized');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Delete existing tag mappings
        $query = "DELETE FROM document_tags WHERE document_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$document_id]);
        
        // Insert new tag mappings
        $query = "INSERT INTO document_tags (document_id, accreditation_tag_id) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        
        foreach ($tag_ids as $tag_id) {
            $stmt->execute([$document_id, $tag_id]);
        }
        
        $db->commit();
        jsonResponse([], 200, 'Tags mapped to document successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to map tags');
    }
}
?>
