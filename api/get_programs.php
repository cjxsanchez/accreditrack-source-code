<?php
header('Content-Type: application/json');

require_once '../config/config.php';
requireLogin();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, name FROM programs WHERE status = 'active' ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $programs
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
