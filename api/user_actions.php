<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;
$type = $input['type'] ?? null;
$id = $input['id'] ?? null;

if (!$action || !$type || !$id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    if ($action === 'delete') {
        switch ($type) {
            case 'program':
                if (!isAdmin()) {
                    throw new Exception('Access denied');
                }
                $query = "DELETE FROM programs WHERE id = ?";
                break;
                
            case 'level':
                if (!isAdmin()) {
                    throw new Exception('Access denied');
                }
                $query = "DELETE FROM levels WHERE id = ?";
                break;
                
            case 'area':
                if (!isAdmin()) {
                    throw new Exception('Access denied');
                }
                $query = "DELETE FROM areas WHERE id = ?";
                break;
                
            case 'parameter':
                if (!isAdmin()) {
                    throw new Exception('Access denied');
                }
                $query = "DELETE FROM parameters WHERE id = ?";
                break;
                
            case 'document':
                // Check if user owns the document or is admin
                $query = "SELECT uploaded_by, file_path FROM documents WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$doc || ($doc['uploaded_by'] != $_SESSION['user_id'] && !isAdmin())) {
                    throw new Exception('Access denied');
                }
                
                // Delete file
                if (file_exists($doc['file_path'])) {
                    unlink($doc['file_path']);
                }
                
                $query = "DELETE FROM documents WHERE id = ?";
                break;
                
            case 'user':
                if (!isAdmin()) {
                    throw new Exception('Access denied');
                }
                $query = "DELETE FROM users WHERE id = ?";
                break;
                
            default:
                throw new Exception('Invalid type');
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' deleted successfully']);
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
