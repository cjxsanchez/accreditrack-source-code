<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');

try {
    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $json_input = file_get_contents('php://input');
    $input = json_decode($json_input, true);

    $program_id = $input['program_id'] ?? $_POST['program_id'] ?? null;

    if (!$program_id) {
        throw new Exception('Missing program_id');
    }

    $db = getDB();

    // Check if program exists and is archived
    $query = "SELECT id, status FROM programs WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$program_id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        throw new Exception('Program not found');
    }
    
    if ($program['status'] !== 'archived') {
        throw new Exception('Only archived programs can be permanently deleted');
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Delete documents
        $query = "DELETE FROM documents WHERE indicator_id IN (
                    SELECT i.id FROM indicators i
                    JOIN parameters p ON i.parameter_id = p.id
                    JOIN areas a ON p.area_id = a.id
                    JOIN levels l ON a.level_id = l.id
                    WHERE l.program_id = ?
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute([$program_id]);

        // Delete indicators
        $query = "DELETE FROM indicators WHERE parameter_id IN (
                    SELECT p.id FROM parameters p
                    JOIN areas a ON p.area_id = a.id
                    JOIN levels l ON a.level_id = l.id
                    WHERE l.program_id = ?
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute([$program_id]);

        // Delete parameters
        $query = "DELETE FROM parameters WHERE area_id IN (
                    SELECT a.id FROM areas a
                    JOIN levels l ON a.level_id = l.id
                    WHERE l.program_id = ?
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute([$program_id]);

        // Delete areas
        $query = "DELETE FROM areas WHERE level_id IN (
                    SELECT id FROM levels WHERE program_id = ?
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute([$program_id]);

        // Delete levels
        $query = "DELETE FROM levels WHERE program_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$program_id]);

        // Delete program
        $query = "DELETE FROM programs WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$program_id]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Program permanently deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
