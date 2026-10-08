<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

try {
    $level_id = $_GET['level_id'] ?? null;
    
    if (!$level_id) {
        throw new Exception('Level ID is required');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get only unassigned areas for this level
    $query = "SELECT id, area_number, name 
              FROM areas 
              WHERE level_id = ? AND assigned_faculty_id IS NULL 
              ORDER BY area_number ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$level_id]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $areas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
