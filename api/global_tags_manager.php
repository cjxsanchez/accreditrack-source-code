<?php
/**
 * Global Accreditation Tags Manager API
 * Handles CRUD operations for global tags and syncing to all programs
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDB();

try {
    switch ($action) {
        case 'get_global_tags':
            getGlobalTags();
            break;
            
        case 'add_global_tag':
            addGlobalTag();
            break;
            
        case 'edit_global_tag':
            editGlobalTag();
            break;
            
        case 'delete_global_tag':
            deleteGlobalTag();
            break;
            
        case 'sync_tag_to_programs':
            syncTagToAllPrograms();
            break;
            
        case 'get_tags_by_indicator':
            getTagsByIndicator();
            break;
            
        default:
            jsonResponse([], 400, 'Invalid action');
    }
} catch (Exception $e) {
    logMessage('Global tag management error: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error: ' . $e->getMessage());
}

function getGlobalTags() {
    global $db;
    
    $area = $_GET['area'] ?? null;
    $param = $_GET['parameter'] ?? null;
    $indicator = $_GET['indicator_type'] ?? null;
    
    $query = "SELECT * FROM global_accreditation_tags WHERE is_active = TRUE";
    $params = [];
    
    if ($area) {
        $query .= " AND area_number = ?";
        $params[] = $area;
    }
    
    if ($param) {
        $query .= " AND parameter_letter = ?";
        $params[] = $param;
    }
    
    if ($indicator) {
        $query .= " AND indicator_type = ?";
        $params[] = $indicator;
    }
    
    $query .= " ORDER BY area_number, parameter_letter, indicator_type, order_index";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($tags, 200, 'Global tags retrieved');
}

function addGlobalTag() {
    global $db;
    
    $area_number = sanitize($_POST['area_number'] ?? '');
    $parameter_letter = sanitize($_POST['parameter_letter'] ?? '');
    $indicator_type = sanitize($_POST['indicator_type'] ?? '');
    $tag_name = sanitize($_POST['tag_name'] ?? '');
    $tag_code = sanitize($_POST['tag_code'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (!$area_number || !$parameter_letter || !$indicator_type || !$tag_name || !$tag_code) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Get max order for this indicator type
        $query = "SELECT MAX(order_index) as max_order FROM global_accreditation_tags 
                  WHERE area_number = ? AND parameter_letter = ? AND indicator_type = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$area_number, $parameter_letter, $indicator_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_order = ($result['max_order'] ?? -1) + 1;
        
        // Insert global tag
        $query = "INSERT INTO global_accreditation_tags 
                  (area_number, parameter_letter, indicator_type, tag_name, tag_code, description, order_index) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$area_number, $parameter_letter, $indicator_type, $tag_name, $tag_code, $description, $next_order]);
        $global_tag_id = $db->lastInsertId();
        
        syncNewTagToAllPrograms($db, $global_tag_id);
        
        $db->commit();
        
        $query = "SELECT * FROM global_accreditation_tags WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$global_tag_id]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($tag, 201, 'Global tag added and synced to all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to add global tag: ' . $e->getMessage());
    }
}

function editGlobalTag() {
    global $db;
    
    $tag_id = $_POST['tag_id'] ?? null;
    $tag_name = sanitize($_POST['tag_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (!$tag_id || !$tag_name) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Update global tag
        $query = "UPDATE global_accreditation_tags SET tag_name = ?, description = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_name, $description, $tag_id]);
        
        $query = "UPDATE accreditation_tags SET tag_name = ?, description = ? WHERE global_tag_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_name, $description, $tag_id]);
        
        $db->commit();
        
        $query = "SELECT * FROM global_accreditation_tags WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_id]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($tag, 200, 'Global tag updated in all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to update tag: ' . $e->getMessage());
    }
}

function deleteGlobalTag() {
    global $db;
    
    $tag_id = $_POST['tag_id'] ?? null;
    
    if (!$tag_id) {
        jsonResponse([], 400, 'Tag ID required');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Soft delete global tag
        $query = "UPDATE global_accreditation_tags SET is_active = FALSE WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_id]);
        
        // Soft delete all copies in accreditation_tags
        $query = "UPDATE accreditation_tags SET is_active = FALSE WHERE global_tag_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_id]);
        
        $db->commit();
        jsonResponse([], 200, 'Global tag deleted from all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to delete tag: ' . $e->getMessage());
    }
}

function getTagsByIndicator() {
    global $db;
    
    $indicator_id = $_GET['indicator_id'] ?? null;
    
    if (!$indicator_id) {
        jsonResponse([], 400, 'Indicator ID required');
        return;
    }
    
    $query = "SELECT * FROM accreditation_tags 
              WHERE indicator_id = ? AND is_active = TRUE
              ORDER BY order_index ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$indicator_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($tags, 200, 'Tags retrieved');
}

function syncNewTagToAllPrograms($db, $global_tag_id) {
    $query = "SELECT gat.* FROM global_accreditation_tags gat WHERE gat.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$global_tag_id]);
    $global_tag = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Find all indicators across all programs that match this tag's criteria
    $query = "SELECT DISTINCT i.id as indicator_id, l.id as level_id 
              FROM indicators i
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              JOIN levels l ON a.level_id = l.id
              WHERE a.area_number = ? AND p.parameter_letter = ? AND i.indicator_type = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$global_tag['area_number'], $global_tag['parameter_letter'], $global_tag['indicator_type']]);
    $indicators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($indicators as $indicator) {
        // Check if tag already exists for this indicator
        $checkQuery = "SELECT id FROM accreditation_tags 
                       WHERE indicator_id = ? AND global_tag_id = ? AND tag_code = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$indicator['indicator_id'], $global_tag_id, $global_tag['tag_code']]);
        
        if ($checkStmt->rowCount() === 0) {
            // Create copy of global tag for this indicator
            $insertQuery = "INSERT INTO accreditation_tags 
                           (indicator_id, global_tag_id, tag_name, tag_code, description, order_index) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute([
                $indicator['indicator_id'],
                $global_tag_id,
                $global_tag['tag_name'],
                $global_tag['tag_code'],
                $global_tag['description'],
                $global_tag['order_index']
            ]);
            
            // Log sync
            $syncQuery = "INSERT INTO level_tag_sync (level_id, global_tag_id, accreditation_tag_id) 
                         VALUES (?, ?, ?)";
            $syncStmt = $db->prepare($syncQuery);
            $syncStmt->execute([$indicator['level_id'], $global_tag_id, $db->lastInsertId()]);
        }
    }
}

function syncTagToAllPrograms() {
    global $db;
    
    $global_tag_id = $_POST['global_tag_id'] ?? null;
    
    if (!$global_tag_id) {
        jsonResponse([], 400, 'Global tag ID required');
        return;
    }
    
    try {
        $db->beginTransaction();
        syncNewTagToAllPrograms($db, $global_tag_id);
        $db->commit();
        
        jsonResponse([], 200, 'Tag synced to all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Sync failed: ' . $e->getMessage());
    }
}

?>
