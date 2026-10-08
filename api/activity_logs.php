<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

try {
    // Get activity logs with user information
    $query = "SELECT 
                'document_upload' as activity_type,
                d.uploaded_at as activity_date,
                u.full_name as user_name,
                CONCAT('Uploaded document: ', d.title) as activity_description,
                d.status as activity_status
              FROM documents d
              JOIN users u ON d.uploaded_by = u.id
              
              UNION ALL
              
              SELECT 
                'document_review' as activity_type,
                d.reviewed_at as activity_date,
                u.full_name as user_name,
                CONCAT('Reviewed document: ', d.title, ' - Status: ', d.status) as activity_description,
                d.status as activity_status
              FROM documents d
              JOIN users u ON d.reviewed_by = u.id
              WHERE d.reviewed_at IS NOT NULL
              
              UNION ALL
              
              SELECT 
                'user_registration' as activity_type,
                u.created_at as activity_date,
                u.full_name as user_name,
                CONCAT('User registered: ', u.role) as activity_description,
                u.status as activity_status
              FROM users u
              WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              
              ORDER BY activity_date DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'activities' => $activities]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
