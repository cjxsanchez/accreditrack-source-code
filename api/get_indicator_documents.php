<?php
/**
 * Get approved documents for an indicator
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$area = $_GET['area'] ?? null;
$parameter = $_GET['parameter'] ?? null;
$indicator = $_GET['indicator'] ?? null;
$level_id = $_GET['level_id'] ?? null;

if (!$area || !$parameter || !$indicator || !$level_id) {
    jsonResponse([], 400, 'Missing required parameters');
}

try {
    $db = getDB();
    
    // Map indicator type to name
    $indicatorNames = [
        'S' => 'System – Inputs and Processes',
        'I' => 'Implementation',
        'O' => 'Outcome/s'
    ];
    
    $indicatorName = $indicatorNames[$indicator] ?? $indicator;
    
    // Get documents
    $query = "SELECT d.*, u.full_name as uploader_name 
              FROM documents d 
              JOIN indicators ind ON d.indicator_id = ind.id 
              JOIN parameters p ON ind.parameter_id = p.id 
              JOIN areas a ON p.area_id = a.id 
              JOIN users u ON d.uploaded_by = u.id
              WHERE a.level_id = ? 
              AND CONCAT('Area ', a.area_number) = ? 
              AND p.parameter_order = ? 
              AND ind.indicator_type = ? 
              AND d.status = 'approved'
              ORDER BY d.uploaded_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$level_id, 'Area ' . $area, $parameter, $indicatorName]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse($documents, 200, 'Documents retrieved');
} catch (Exception $e) {
    logMessage('Error fetching documents: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error fetching documents');
}
?>
