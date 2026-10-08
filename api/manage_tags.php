<?php
/**
 * Manage Accreditation Tags API
 * Handles CRUD operations for accreditation tags
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDB();

try {
    switch ($action) {
        case 'get_tags':
            getIndicatorTags();
            break;
            
        case 'add_tag':
            addTag();
            break;
            
        case 'edit_tag':
            editTag();
            break;
            
        case 'delete_tag':
            deleteTag();
            break;
            
        case 'reorder_tags':
            reorderTags();
            break;
            
        case 'get_area_tags':
            getAreaTags();
            break;
            
        default:
            jsonResponse([], 400, 'Invalid action');
    }
} catch (Exception $e) {
    logMessage('Tag management error: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error: ' . $e->getMessage());
}

/**
 * Get all tags for a specific indicator
 */
function getIndicatorTags() {
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
    
    jsonResponse($tags, 200, 'Tags retrieved successfully');
}

/**
 * Add a new tag to an indicator
 */
function addTag() {
    global $db;
    
    $indicator_id = $_POST['indicator_id'] ?? null;
    $tag_name = sanitize($_POST['tag_name'] ?? '');
    $tag_code = sanitize($_POST['tag_code'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (!$indicator_id || !$tag_name || !$tag_code) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    // Get the next order index
    $query = "SELECT MAX(order_index) as max_order FROM accreditation_tags WHERE indicator_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$indicator_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_order = ($result['max_order'] ?? -1) + 1;
    
    // Insert new tag
    $query = "INSERT INTO accreditation_tags (indicator_id, tag_name, tag_code, description, order_index) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$indicator_id, $tag_name, $tag_code, $description, $next_order])) {
        $tag_id = $db->lastInsertId();
        
        // Get the newly created tag
        $query = "SELECT * FROM accreditation_tags WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_id]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($tag, 201, 'Tag added successfully');
    } else {
        jsonResponse([], 500, 'Failed to add tag');
    }
}

/**
 * Edit an existing tag
 */
function editTag() {
    global $db;
    
    $tag_id = $_POST['tag_id'] ?? null;
    $tag_name = sanitize($_POST['tag_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (!$tag_id || !$tag_name) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    $query = "UPDATE accreditation_tags SET tag_name = ?, description = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$tag_name, $description, $tag_id])) {
        // Get updated tag
        $query = "SELECT * FROM accreditation_tags WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$tag_id]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($tag, 200, 'Tag updated successfully');
    } else {
        jsonResponse([], 500, 'Failed to update tag');
    }
}

/**
 * Delete a tag (soft delete)
 */
function deleteTag() {
    global $db;
    
    $tag_id = $_POST['tag_id'] ?? null;
    
    if (!$tag_id) {
        jsonResponse([], 400, 'Tag ID required');
        return;
    }
    
    $query = "UPDATE accreditation_tags SET is_active = FALSE WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$tag_id])) {
        jsonResponse([], 200, 'Tag deleted successfully');
    } else {
        jsonResponse([], 500, 'Failed to delete tag');
    }
}

/**
 * Reorder tags for an indicator
 */
function reorderTags() {
    global $db;
    
    $tag_orders = $_POST['tag_orders'] ?? [];
    
    if (empty($tag_orders)) {
        jsonResponse([], 400, 'No tags to reorder');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        foreach ($tag_orders as $order => $tag_id) {
            $query = "UPDATE accreditation_tags SET order_index = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$order, $tag_id]);
        }
        
        $db->commit();
        jsonResponse([], 200, 'Tags reordered successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to reorder tags');
    }
}

/**
 * Get all tags for an entire area with all indicators
 */
function getAreaTags() {
    global $db;
    
    $area_id = $_GET['area_id'] ?? null;
    
    if (!$area_id) {
        jsonResponse([], 400, 'Area ID required');
        return;
    }
    
    $query = "SELECT at.*, i.id as indicator_id, i.indicator_type, i.name as indicator_name, 
                     p.id as parameter_id, p.parameter_letter, p.name as parameter_name
              FROM accreditation_tags at
              JOIN indicators i ON at.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              WHERE p.area_id = ? AND at.is_active = TRUE
              ORDER BY p.id, i.indicator_type, at.order_index";
    $stmt = $db->prepare($query);
    $stmt->execute([$area_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize tags by indicator for easier frontend use
    $organized_tags = [];
    foreach ($tags as $tag) {
        $indicator_id = $tag['indicator_id'];
        if (!isset($organized_tags[$indicator_id])) {
            $organized_tags[$indicator_id] = [
                'indicator_id' => $indicator_id,
                'indicator_type' => $tag['indicator_type'],
                'parameter_letter' => $tag['parameter_letter'],
                'tags' => []
            ];
        }
        $organized_tags[$indicator_id]['tags'][] = [
            'id' => $tag['id'],
            'tag_name' => $tag['tag_name'],
            'tag_code' => $tag['tag_code'],
            'description' => $tag['description']
        ];
    }
    
    jsonResponse(array_values($organized_tags), 200, 'Area tags retrieved successfully');
}
?>
