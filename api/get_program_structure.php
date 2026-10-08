<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

$level_id = $_GET['level_id'] ?? null;

if (!$level_id) {
    jsonResponse([], 400, 'Level ID required');
    exit;
}

$db = getDB();

try {
    // Get areas for the selected level
    $query = "SELECT a.id, a.area_number, a.name, a.assigned_faculty_id, u.full_name as assigned_faculty
              FROM areas a
              LEFT JOIN users u ON a.assigned_faculty_id = u.id
              WHERE a.level_id = ?
              ORDER BY a.area_number ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$level_id]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($areas, 200, 'Areas retrieved successfully');
} catch (Exception $e) {
    jsonResponse([], 500, 'Error: ' . $e->getMessage());
}
?>
