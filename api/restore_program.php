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
    
    // Update program status back to active
    $query = "UPDATE programs SET status = 'active' WHERE id = ? AND status = 'archived'";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$program_id])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Program restored successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Program not found or not archived']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to restore program']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
