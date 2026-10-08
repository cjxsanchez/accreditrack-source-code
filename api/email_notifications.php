<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

$database = new Database();
$db = $database->getConnection();

try {
    switch ($action) {
        case 'send_reminder':
            $user_ids = $input['user_ids'] ?? [];
            $message = sanitize($input['message'] ?? '');
            
            if (empty($user_ids) || empty($message)) {
                throw new Exception('Missing required fields');
            }
            
            // Send notifications to selected users
            foreach ($user_ids as $user_id) {
                $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id, 'Reminder', $message, 'announcement']);
            }
            
            // In a real application, you would also send actual emails here
            // using a library like PHPMailer or similar
            
            echo json_encode(['success' => true, 'message' => 'Reminders sent to ' . count($user_ids) . ' users']);
            break;
            
        case 'send_deadline_notice':
            $deadline_date = $input['deadline_date'] ?? '';
            $areas = $input['areas'] ?? [];
            
            if (empty($deadline_date) || empty($areas)) {
                throw new Exception('Missing required fields');
            }
            
            // Get faculty assigned to selected areas
            $placeholders = str_repeat('?,', count($areas) - 1) . '?';
            $query = "SELECT DISTINCT a.assigned_faculty_id, u.full_name, u.email, a.name as area_name
                      FROM areas a
                      JOIN users u ON a.assigned_faculty_id = u.id
                      WHERE a.id IN ($placeholders) AND a.assigned_faculty_id IS NOT NULL";
            
            $stmt = $db->prepare($query);
            $stmt->execute($areas);
            $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($faculty as $member) {
                $message = "Deadline reminder: Please ensure all documents for {$member['area_name']} are submitted by {$deadline_date}.";
                
                $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$member['assigned_faculty_id'], 'Deadline Notice', $message, 'announcement']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Deadline notices sent to ' . count($faculty) . ' faculty members']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
