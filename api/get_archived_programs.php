<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT p.*, 
                     (SELECT COUNT(*) FROM levels WHERE program_id = p.id) as levels_count,
                     (SELECT COUNT(*) FROM documents d 
                      JOIN indicators i ON d.indicator_id = i.id 
                      JOIN parameters pr ON i.parameter_id = pr.id 
                      JOIN areas a ON pr.area_id = a.id 
                      JOIN levels l ON a.level_id = l.id 
                      WHERE l.program_id = p.id) as documents_count
              FROM programs p 
              WHERE p.status = 'archived' 
              ORDER BY p.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'programs' => $programs]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
