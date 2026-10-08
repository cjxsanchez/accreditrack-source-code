<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$document_id = $_GET['document_id'] ?? null;

if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if user has access to this document
    $query = "SELECT d.*, i.id as indicator_id
              FROM documents d
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              WHERE d.id = ?";
    
    if (isStaff()) {
        $query .= " AND (d.uploaded_by = ? OR a.assigned_faculty_id = ?)";
        $params = [$document_id, $_SESSION['user_id'], $_SESSION['user_id']];
    } else {
        $params = [$document_id];
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document not found or access denied');
    }
    
    // Get document versions (documents with same indicator_id and codename)
    $query = "SELECT d.*, u.full_name as uploader_name
              FROM documents d
              JOIN users u ON d.uploaded_by = u.id
              WHERE d.indicator_id = ? AND d.codename = ?
              ORDER BY d.uploaded_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$document['indicator_id'], $document['codename']]);
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add version numbers
    foreach ($versions as $index => &$version) {
        $version['version_number'] = count($versions) - $index;
        $version['is_current'] = ($index === 0);
    }
    
    echo json_encode(['success' => true, 'versions' => $versions]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
