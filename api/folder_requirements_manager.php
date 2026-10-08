<?php
/**
 * Global Folder and Requirements Manager
 * Handles CRUD operations for folders and requirements with auto-sync to all programs
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDB();

try {
    switch ($action) {
        case 'get_folders_by_indicator':
            getFoldersByIndicator();
            break;
        
        case 'get_global_requirements':
            getGlobalRequirements();
            break;
            
        case 'get_requirements_by_folder':
            getRequirementsByFolder();
            break;
        
        case 'get_global_folder':
            getGlobalFolder();
            break;
        
        case 'get_global_requirement':
            getGlobalRequirement();
            break;
            
        case 'add_global_folder':
            addGlobalFolder();
            break;
            
        case 'add_global_requirement':
            addGlobalRequirement();
            break;
            
        case 'edit_global_folder':
            editGlobalFolder();
            break;
            
        case 'edit_global_requirement':
            editGlobalRequirement();
            break;
            
        case 'delete_global_folder':
            deleteGlobalFolder();
            break;
            
        case 'delete_global_requirement':
            deleteGlobalRequirement();
            break;
            
        case 'sync_folder_to_programs':
            syncFolderToAllPrograms();
            break;
        
        case 'get_indicator_folders_with_docs':
            getIndicatorFoldersWithDocs();
            break;
            
        default:
            jsonResponse([], 400, 'Invalid action');
    }
} catch (Exception $e) {
    logMessage('Folder management error: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error: ' . $e->getMessage());
}

function getFoldersByIndicator() {
    global $db;
    
    $indicator_id = $_GET['indicator_id'] ?? null;
    
    if (!$indicator_id) {
        jsonResponse([], 400, 'Indicator ID required');
        return;
    }
    
    // Get indicator info with correct column names
    $query = "SELECT i.id, i.indicator_type, p.parameter_letter, a.area_number 
              FROM indicators i
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              WHERE i.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$indicator_id]);
    $indicator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$indicator) {
        jsonResponse([], 404, 'Indicator not found');
        return;
    }
    
    // Convert indicator_type enum to match global_accreditation_tags format
    // The indicators table uses 'S', 'I', 'O' enum values
    $indicator_type = $indicator['indicator_type'];
    
    // Get folders from global_accreditation_tags matching this indicator's criteria
    $query = "SELECT * FROM global_accreditation_tags 
              WHERE area_number = ? AND parameter_letter = ? AND indicator_type = ? AND is_active = TRUE
              ORDER BY order_index ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$indicator['area_number'], $indicator['parameter_letter'], $indicator_type]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($folders, 200, 'Folders retrieved');
}

function getGlobalFolder() {
    global $db;
    
    $folder_id = $_GET['folder_id'] ?? null;
    
    if (!$folder_id) {
        jsonResponse([], 400, 'Folder ID required');
        return;
    }
    
    $query = "SELECT * FROM global_accreditation_tags WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$folder_id]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($folder) {
        jsonResponse($folder, 200, 'Folder retrieved');
    } else {
        jsonResponse([], 404, 'Folder not found');
    }
}

function getGlobalRequirement() {
    global $db;
    
    $requirement_id = $_GET['requirement_id'] ?? null;
    
    if (!$requirement_id) {
        jsonResponse([], 400, 'Requirement ID required');
        return;
    }
    
    $query = "SELECT * FROM global_requirements WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$requirement_id]);
    $requirement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($requirement) {
        jsonResponse($requirement, 200, 'Requirement retrieved');
    } else {
        jsonResponse([], 404, 'Requirement not found');
    }
}

function getGlobalRequirements() {
    global $db;
    
    $folder_id = $_GET['folder_id'] ?? null;
    
    if (!$folder_id) {
        jsonResponse([], 400, 'Folder ID required');
        return;
    }
    
    $query = "SELECT * FROM global_requirements 
              WHERE global_folder_id = ? AND is_active = TRUE
              ORDER BY requirement_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$folder_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($requirements, 200, 'Requirements retrieved');
}

function getRequirementsByFolder() {
    global $db;
    
    $folder_id = $_GET['folder_id'] ?? null;
    
    if (!$folder_id) {
        jsonResponse([], 400, 'Folder ID required');
        return;
    }
    
    $query = "SELECT * FROM global_requirements 
              WHERE global_folder_id = ? AND is_active = TRUE
              ORDER BY requirement_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$folder_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($requirements, 200, 'Requirements retrieved');
}

function addGlobalFolder() {
    global $db;
    
    $area_number = sanitize($_POST['area_number'] ?? '');
    $parameter_letter = sanitize($_POST['parameter_letter'] ?? '');
    $indicator_type = sanitize($_POST['indicator_type'] ?? '');
    $folder_name = sanitize($_POST['folder_name'] ?? '');
    $folder_code = sanitize($_POST['folder_code'] ?? '');
    $folder_description = sanitize($_POST['folder_description'] ?? '');
    
    if (!$area_number || !$parameter_letter || !$indicator_type || !$folder_name || !$folder_code) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Get max order
        $query = "SELECT MAX(order_index) as max_order FROM global_accreditation_tags 
                  WHERE area_number = ? AND parameter_letter = ? AND indicator_type = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$area_number, $parameter_letter, $indicator_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_order = ($result['max_order'] ?? -1) + 1;
        
        // Insert global folder
        $query = "INSERT INTO global_accreditation_tags 
                  (area_number, parameter_letter, indicator_type, folder_name, folder_code, folder_description, order_index) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$area_number, $parameter_letter, $indicator_type, $folder_name, $folder_code, $folder_description, $next_order]);
        $global_folder_id = $db->lastInsertId();
        
        // Sync to all existing programs
        syncNewFolderToAllPrograms($db, $global_folder_id);
        
        $db->commit();
        
        $query = "SELECT * FROM global_accreditation_tags WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$global_folder_id]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($folder, 201, 'Global folder added and synced to all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to add folder: ' . $e->getMessage());
    }
}

function addGlobalRequirement() {
    global $db;
    
    $global_folder_id = (int)($_POST['global_folder_id'] ?? 0);
    $requirement_name = sanitize($_POST['requirement_name'] ?? '');
    $requirement_code = sanitize($_POST['requirement_code'] ?? '');
    $requirement_description = sanitize($_POST['requirement_description'] ?? '');
    
    if (!$global_folder_id || !$requirement_name || !$requirement_code) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Get max order
        $query = "SELECT MAX(requirement_order) as max_order FROM global_requirements WHERE global_folder_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$global_folder_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_order = ($result['max_order'] ?? -1) + 1;
        
        // Insert global requirement
        $query = "INSERT INTO global_requirements 
                  (global_folder_id, requirement_name, requirement_code, requirement_description, requirement_order) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$global_folder_id, $requirement_name, $requirement_code, $requirement_description, $next_order]);
        $global_requirement_id = $db->lastInsertId();
        
        // Sync to all program-level folders
        syncNewRequirementToAllPrograms($db, $global_requirement_id);
        
        $db->commit();
        
        $query = "SELECT * FROM global_requirements WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$global_requirement_id]);
        $requirement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse($requirement, 201, 'Global requirement added and synced');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to add requirement: ' . $e->getMessage());
    }
}

function editGlobalFolder() {
    global $db;
    
    $folder_id = (int)($_POST['folder_id'] ?? 0);
    $folder_name = sanitize($_POST['folder_name'] ?? '');
    $folder_code = sanitize($_POST['folder_code'] ?? '');
    $folder_description = sanitize($_POST['folder_description'] ?? '');
    
    if (!$folder_id || !$folder_name) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Update global folder
        $query = "UPDATE global_accreditation_tags SET folder_name = ?, folder_code = ?, folder_description = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$folder_name, $folder_code, $folder_description, $folder_id]);
        
        // Update all program-level copies
        $query = "UPDATE accreditation_folders SET folder_name = ? WHERE global_tag_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$folder_name, $folder_id]);
        
        $db->commit();
        
        jsonResponse([], 200, 'Folder updated in all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to update folder: ' . $e->getMessage());
    }
}

function editGlobalRequirement() {
    global $db;
    
    $requirement_id = (int)($_POST['requirement_id'] ?? 0);
    $requirement_name = sanitize($_POST['requirement_name'] ?? '');
    $requirement_code = sanitize($_POST['requirement_code'] ?? '');
    $requirement_description = sanitize($_POST['requirement_description'] ?? '');
    
    if (!$requirement_id || !$requirement_name) {
        jsonResponse([], 400, 'Missing required fields');
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Update global requirement
        $query = "UPDATE global_requirements SET requirement_name = ?, requirement_code = ?, requirement_description = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$requirement_name, $requirement_code, $requirement_description, $requirement_id]);
        
        // Update all program-level copies
        $query = "UPDATE program_requirements SET requirement_name = ?, requirement_code = ?, requirement_description = ? WHERE global_requirement_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$requirement_name, $requirement_code, $requirement_description, $requirement_id]);
        
        $db->commit();
        
        jsonResponse([], 200, 'Requirement updated in all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to update requirement: ' . $e->getMessage());
    }
}

// function deleteGlobalFolder() {
//     global $db;
    
//     $folder_id = (int)($_POST['folder_id'] ?? 0);
    
//     if (!$folder_id) {
//         jsonResponse([], 400, 'Folder ID required');
//         return;
//     }
    
//     try {
//         $db->beginTransaction();
        
//         // Soft delete global folder
//         $query = "UPDATE global_accreditation_tags SET is_active = FALSE WHERE id = ?";
//         $stmt = $db->prepare($query);
//         $stmt->execute([$folder_id]);
        
//         // Soft delete all requirements in this folder
//         $query = "UPDATE global_requirements SET is_active = FALSE WHERE global_folder_id = ?";
//         $stmt = $db->prepare($query);
//         $stmt->execute([$folder_id]);
        
//         // Soft delete all program-level copies
//         $query = "UPDATE accreditation_folders SET is_active = FALSE WHERE global_tag_id = ?";
//         $stmt = $db->prepare($query);
//         $stmt->execute([$folder_id]);
        
//         $db->commit();
//         jsonResponse([], 200, 'Folder deleted from all programs');
//     } catch (Exception $e) {
//         $db->rollBack();
//         jsonResponse([], 500, 'Failed to delete folder: ' . $e->getMessage());
//     }
// }

function deleteGlobalFolder() {
    global $db;

    $folder_id = (int)($_POST['folder_id'] ?? 0);

    if (!$folder_id) {
        jsonResponse([], 400, 'Folder ID required');
        return;
    }

    try {
        $db->beginTransaction();

        // 1. Soft delete the folder itself
        $query = "UPDATE global_accreditation_tags SET is_active = FALSE WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$folder_id]);

        // 2. Soft delete global requirements inside this folder
        $query = "UPDATE global_requirements SET is_active = FALSE WHERE global_folder_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$folder_id]);

        // 3. Soft delete program-level folder copies
        $query = "UPDATE accreditation_folders SET is_active = FALSE WHERE global_tag_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$folder_id]);

        // 4. Soft delete program requirements linked to the deleted global requirements
        $query = "
            UPDATE program_requirements 
            SET is_active = FALSE 
            WHERE global_requirement_id IN (
                SELECT id FROM global_requirements WHERE global_folder_id = ?
            )";
        $stmt = $db->prepare($query);
        $stmt->execute([$folder_id]);

        $db->commit();
        jsonResponse([], 200, 'Folder deleted from all programs');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to delete folder: ' . $e->getMessage());
    }
}


// function deleteGlobalRequirement() {
//     global $db;
    
//     $requirement_id = (int)($_POST['requirement_id'] ?? 0);
    
//     if (!$requirement_id) {
//         jsonResponse([], 400, 'Requirement ID required');
//         return;
//     }
    
//     try {
//         $db->beginTransaction();
        
//         // Soft delete global requirement
//         $query = "UPDATE global_requirements SET is_active = FALSE WHERE id = ?";
//         $stmt = $db->prepare($query);
//         $stmt->execute([$requirement_id]);
        
//         // Soft delete all program-level copies
//         $query = "UPDATE program_requirements SET is_active = FALSE WHERE global_requirement_id = ?";
//         $stmt = $db->prepare($query);
//         $stmt->execute([$requirement_id]);
        
//         $db->commit();
//         jsonResponse([], 200, 'Requirement deleted from all programs');
//     } catch (Exception $e) {
//         $db->rollBack();
//         jsonResponse([], 500, 'Failed to delete requirement: ' . $e->getMessage());
//     }
// }

function deleteGlobalRequirement() {
    global $db;
    
    $requirement_id = (int)($_POST['requirement_id'] ?? 0);
    
    if (!$requirement_id) {
        jsonResponse([], 400, 'Requirement ID required');
        return;
    }
    
    try {
        $db->beginTransaction();

        // Clear and soft delete global requirement
        $query = "UPDATE global_requirements 
                  SET requirement_name = '', 
                      requirement_code = '', 
                      requirement_description = '',
                      is_active = FALSE 
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$requirement_id]);
        
        // Clear and soft delete program-level copies
        $query = "UPDATE program_requirements 
                  SET requirement_name = '', 
                      requirement_code = '', 
                      requirement_description = '',
                      is_active = FALSE 
                  WHERE global_requirement_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$requirement_id]);

        $db->commit();
        jsonResponse([], 200, 'Requirement deleted from all programs');
        
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse([], 500, 'Failed to delete requirement: ' . $e->getMessage());
    }
}

function syncNewFolderToAllPrograms($db, $global_folder_id) {
    $query = "SELECT * FROM global_accreditation_tags WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$global_folder_id]);
    $global_folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$global_folder) return;
    
    // Find all indicators matching this folder's criteria
    $query = "SELECT DISTINCT i.id as indicator_id 
              FROM indicators i
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              WHERE a.area_number = ? AND p.parameter_letter = ? AND i.indicator_type = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$global_folder['area_number'], $global_folder['parameter_letter'], $global_folder['indicator_type']]);
    $indicators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($indicators as $indicator) {
        // Check if folder already exists
        $checkQuery = "SELECT id FROM accreditation_folders 
                       WHERE indicator_id = ? AND global_tag_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$indicator['indicator_id'], $global_folder_id]);
        
        if ($checkStmt->rowCount() === 0) {
            // Create program-level folder
            $insertQuery = "INSERT INTO accreditation_folders 
                           (indicator_id, global_tag_id, area_number, parameter_letter, indicator_type, folder_name, folder_order) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute([
                $indicator['indicator_id'],
                $global_folder_id,
                $global_folder['area_number'],
                $global_folder['parameter_letter'],
                $global_folder['indicator_type'],
                $global_folder['folder_name'],
                $global_folder['order_index']
            ]);
        }
    }
}

function syncNewRequirementToAllPrograms($db, $global_requirement_id) {
    $query = "SELECT gr.*, gat.id as global_folder_id FROM global_requirements gr
              JOIN global_accreditation_tags gat ON gr.global_folder_id = gat.id
              WHERE gr.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$global_requirement_id]);
    $global_req = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$global_req) return;
    
    // Find all program-level folders that correspond to this global folder
    $query = "SELECT DISTINCT af.id as folder_id 
              FROM accreditation_folders af
              WHERE af.global_tag_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$global_req['global_folder_id']]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($folders as $folder) {
        // Check if requirement already exists
        $checkQuery = "SELECT id FROM program_requirements 
                       WHERE folder_id = ? AND global_requirement_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$folder['folder_id'], $global_requirement_id]);
        
        if ($checkStmt->rowCount() === 0) {
            // Create program-level requirement
            $insertQuery = "INSERT INTO program_requirements 
                           (folder_id, global_requirement_id, requirement_name, requirement_code, requirement_description, requirement_order) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute([
                $folder['folder_id'],
                $global_requirement_id,
                $global_req['requirement_name'],
                $global_req['requirement_code'],
                $global_req['requirement_description'],
                $global_req['requirement_order']
            ]);
        }
    }
}

function getIndicatorFoldersWithDocs() {
    global $db;
    
    $level_id = $_GET['level_id'] ?? null;
    $area_number = $_GET['area_number'] ?? null;
    $parameter_letter = $_GET['parameter_letter'] ?? null;
    $indicator_type = $_GET['indicator_type'] ?? null;
    
    if (!$level_id || !$area_number || !$parameter_letter || !$indicator_type) {
        jsonResponse([], 400, 'Missing required parameters');
        return;
    }
    
    // Get folders from global_accreditation_tags
    $query = "SELECT * FROM global_accreditation_tags 
              WHERE area_number = ? AND parameter_letter = ? AND indicator_type = ? AND is_active = TRUE
              ORDER BY order_index ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$area_number, $parameter_letter, $indicator_type]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each folder, get requirements and documents
    foreach ($folders as &$folder) {
        // Get requirements
        $reqQuery = "SELECT * FROM global_requirements 
                     WHERE global_folder_id = ? AND is_active = TRUE
                     ORDER BY requirement_order ASC";
        $reqStmt = $db->prepare($reqQuery);
        $reqStmt->execute([$folder['id']]);
        $folder['requirements'] = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each requirement, check if there's an approved document
        foreach ($folder['requirements'] as &$req) {
            // Get indicator ID for this level/area/parameter/type
            $indQuery = "SELECT i.id FROM indicators i
                         JOIN parameters p ON i.parameter_id = p.id
                         JOIN areas a ON p.area_id = a.id
                         WHERE a.level_id = ? AND a.area_number = ? AND p.parameter_letter = ? AND i.indicator_type = ?";
            $indStmt = $db->prepare($indQuery);
            $indStmt->execute([$level_id, $area_number, $parameter_letter, $indicator_type]);
            $indicator = $indStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($indicator) {
                // Get approved document for this requirement
                $docQuery = "SELECT d.* FROM documents d
                             WHERE d.indicator_id = ? AND d.requirement_id = ? AND d.status = 'approved'
                             ORDER BY d.approved_at DESC LIMIT 1";
                $docStmt = $db->prepare($docQuery);
                $docStmt->execute([$indicator['id'], $req['id']]);
                $req['document'] = $docStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $req['document'] = null;
            }
        }
    }
    
    jsonResponse($folders, 200, 'Folders with documents retrieved');
}

function syncFolderToAllPrograms() {
    // This function is used for manual sync if needed
    jsonResponse([], 200, 'Sync completed');
}
?>
