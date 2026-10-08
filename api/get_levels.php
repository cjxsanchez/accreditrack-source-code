<?php
/**
 * Get levels for a program
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$program_id = $_GET['program_id'] ?? null;

if (!$program_id) {
    jsonResponse([], 400, 'Program ID required');
}

try {
    $db = getDB();
    $query = "SELECT id, program_id, level_type FROM levels WHERE program_id = ? ORDER BY level_type";
    $stmt = $db->prepare($query);
    $stmt->execute([$program_id]);
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse($levels, 200, 'Levels retrieved successfully');
} catch (Exception $e) {
    logMessage('Error fetching levels: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error fetching levels');
}
?>
