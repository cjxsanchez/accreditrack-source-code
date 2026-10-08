<?php
/**
 * Get Indicator Folders with Documents for Program View
 * Returns folders, requirements, and approved documents for a specific indicator
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$db = getDB();

$level_id = $_GET['level_id'] ?? null;
$area_number = $_GET['area_number'] ?? null; // Expecting "1", "2", etc.
$parameter_letter = $_GET['parameter_letter'] ?? null;
$indicator_type = $_GET['indicator_type'] ?? null;

if (!$level_id || !$area_number || !$parameter_letter || !$indicator_type) {
    echo json_encode(['status' => 400, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // 1. ROBUST INDICATOR SEARCH
    // We search for the specific indicator ID for this Level + Area + Param + Type.
    // We handle cases where DB stores "Area 1" vs "1".
    $query = "SELECT i.id as indicator_id 
              FROM indicators i
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              WHERE a.level_id = ? 
              AND (a.area_number = ? OR a.area_number = ?) 
              AND p.parameter_letter = ? 
              AND i.indicator_type = ?";
              
    $stmt = $db->prepare($query);
    // Pass both "1" and "Area 1" to ensure a match
    $stmt->execute([$level_id, $area_number, "Area $area_number", $parameter_letter, $indicator_type]);
    $indicatorResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $indicator_id = $indicatorResult ? $indicatorResult['indicator_id'] : null;
    
    // 2. FETCH GLOBAL FOLDERS
    // We assume global tags use the simple number "1", "2", etc.
    $query = "SELECT * FROM global_accreditation_tags 
              WHERE area_number = ? AND parameter_letter = ? AND indicator_type = ? AND is_active = TRUE
              ORDER BY order_index ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$area_number, $parameter_letter, $indicator_type]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. FETCH REQUIREMENTS & DOCUMENTS
    foreach ($folders as &$folder) {
        // Get requirements for this folder
        $reqQuery = "SELECT * FROM global_requirements 
                     WHERE global_folder_id = ? AND is_active = TRUE
                     ORDER BY requirement_order ASC";
        $reqStmt = $db->prepare($reqQuery);
        $reqStmt->execute([$folder['id']]);
        $folder['requirements'] = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $folder['uploaded_count'] = 0;
        $folder['total_requirements'] = count($folder['requirements']);
        
        // Check for approved documents for each requirement
        foreach ($folder['requirements'] as &$req) {
            $req['document'] = null;
            
            if ($indicator_id) {
                // Find document: Matches Indicator ID AND (Requirement ID OR matching Code) AND is Approved
                $docQuery = "SELECT d.id, d.title, d.file_path, d.status, d.created_at, u.full_name as uploader_name 
                             FROM documents d
                             LEFT JOIN users u ON d.uploaded_by = u.id
                             WHERE d.indicator_id = ? 
                             AND (d.requirement_id = ? OR d.title LIKE ?)
                             AND d.status = 'approved'
                             ORDER BY d.id DESC LIMIT 1";
                
                $docStmt = $db->prepare($docQuery);
                // Use the code (e.g., "S.1") to fuzzy match title if ID is missing
                $docStmt->execute([$indicator_id, $req['id'], '%' . $req['requirement_code'] . '%']);
                $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($doc) {
                    $req['document'] = $doc;
                    $folder['uploaded_count']++;
                }
            }
        }
    }
    
    // Manual JSON construction to ensure correct format
    echo json_encode([
        'status' => 200,
        'message' => 'Folders retrieved successfully',
        'data' => $folders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 500, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>