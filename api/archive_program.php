<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$program_id = $input['program_id'] ?? null;

if (!$program_id) {
    echo json_encode(['success' => false, 'message' => 'Missing program_id']);
    exit;
}

try {
    $db = getDB();
    
    // Update program status to archived
    $query = "UPDATE programs SET status = 'archived' WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$program_id])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Program archived successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Program not found or already archived']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to archive program']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
