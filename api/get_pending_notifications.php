<?php
/**
 * API to get pending notifications for admin
 * Returns pending document uploads and pending user registrations
 */
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

$db = getDB();
$notifications = [];
$total_count = 0;

try {
    // Get pending document uploads (limit to recent 10)
    // FIX: Changed from query() to prepare() + execute()
    $query = "
        SELECT 
            d.id,
            d.title,
            d.created_at,
            u.full_name as uploader_name,
            p.name as program_name,
            l.level_type,
            a.area_number
        FROM documents d
        JOIN users u ON d.uploaded_by = u.id
        JOIN indicators i ON d.indicator_id = i.id
        JOIN parameters pm ON i.parameter_id = pm.id
        JOIN areas a ON pm.area_id = a.id
        JOIN levels l ON a.level_id = l.id
        JOIN programs p ON l.program_id = p.id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'id' => 'doc_' . $row['id'],
            'type' => 'document_upload',
            'title' => 'New Document Upload',
            'message' => $row['uploader_name'] . ' uploaded "' . $row['title'] . '" for ' . $row['program_name'] . ' ' . $row['level_type'] . ' - ' . $row['area_number'],
            'created_at' => $row['created_at'],
            'is_read' => false,
            'url' => 'validation.php'
        ];
    }
    
    // Get pending user registrations (limit to recent 5)
    // FIX: Changed from query() to prepare() + execute()
    $userQuery = "
        SELECT id, full_name, email, created_at
        FROM users
        WHERE status = 'pending'
        ORDER BY created_at DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($userQuery);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'id' => 'user_' . $row['id'],
            'type' => 'user_registration',
            'title' => 'New Faculty Registration',
            'message' => $row['full_name'] . ' (' . $row['email'] . ') is waiting for approval',
            'created_at' => $row['created_at'],
            'is_read' => false,
            'url' => 'user_management.php'
        ];
    }
    
    // Sort all notifications by date
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Get total counts
    // FIX: Changed from query() to prepare() + execute()
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
    $stmt->execute();
    $pending_docs = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $stmt->execute();
    $pending_users = $stmt->fetchColumn();
    
    $total_count = $pending_docs + $pending_users;
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total_count' => $total_count,
        'pending_documents' => $pending_docs,
        'pending_users' => $pending_users
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'notifications' => [],
        'total_count' => 0
    ]);
}
?>