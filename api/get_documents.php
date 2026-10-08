<?php
/**
 * Get Documents API
 * Returns documents for a specific indicator with folder and requirement info
 */
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$indicator_id = $_GET['indicator_id'] ?? null;

if (!$indicator_id) {
    echo json_encode(['success' => false, 'message' => 'Indicator ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verify access for staff users
    if (isStaff()) {
        $query = "SELECT i.id FROM indicators i
                  JOIN parameters p ON i.parameter_id = p.id
                  JOIN areas a ON p.area_id = a.id
                  WHERE i.id = ? AND a.assigned_faculty_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$indicator_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }
    
    $query = "SELECT d.*, 
                     u.full_name as uploader_name,
                     gf.name as folder_name, gf.code as folder_code,
                     gr.name as requirement_name, gr.code as requirement_code
              FROM documents d
              JOIN users u ON d.uploaded_by = u.id
              LEFT JOIN global_accreditation_tags gf ON d.folder_id = gf.id
              LEFT JOIN global_requirements gr ON d.requirement_id = gr.id
              WHERE d.indicator_id = ?
              ORDER BY d.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$indicator_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
