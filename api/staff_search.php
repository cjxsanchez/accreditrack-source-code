<?php
/**
 * Staff Search API - Search assigned areas, documents, profile
 */
require_once '../config/config.php';
requireStaff();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$db = getDB();
$results = [];
$userId = $_SESSION['user_id'];

try {
    // Search assigned areas
    $stmt = $db->prepare("
        SELECT a.id, a.name, a.area_number, p.name as program_name, l.level_type
        FROM areas a
        JOIN levels l ON a.level_id = l.id
        JOIN programs p ON l.program_id = p.id
        WHERE a.assigned_faculty_id = ? 
        AND (a.name LIKE ? OR a.area_number LIKE ? OR p.name LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$userId, "%$query%", "%$query%", "%$query%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'title' => $row['area_number'] . ' - ' . $row['name'],
            'description' => $row['program_name'] . ' - ' . $row['level_type'],
            'url' => 'assigned_areas.php?area_id=' . $row['id'],
            'icon' => 'fas fa-folder-open'
        ];
    }
    
    // Search user's documents
    $stmt = $db->prepare("
        SELECT d.id, d.title, d.filename, d.status, a.name as area_name, a.area_number
        FROM documents d
        JOIN indicators i ON d.indicator_id = i.id
        JOIN parameters pm ON i.parameter_id = pm.id
        JOIN areas a ON pm.area_id = a.id
        WHERE d.uploaded_by = ? 
        AND (d.title LIKE ? OR d.filename LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$userId, "%$query%", "%$query%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusIcon = $row['status'] === 'approved' ? 'check-circle text-success' : 
                     ($row['status'] === 'rejected' ? 'times-circle text-danger' : 'clock text-warning');
        $results[] = [
            'title' => $row['title'],
            'description' => ucfirst($row['status']) . ' - ' . $row['area_number'],
            'url' => 'assigned_areas.php?doc=' . $row['id'],
            'icon' => 'fas fa-file-alt'
        ];
    }
    
    // Search archived documents
    try {
        $stmt = $db->prepare("
            SELECT ad.id, ad.title, ad.filename
            FROM archived_documents ad
            WHERE ad.archived_by = ? 
            AND (ad.title LIKE ? OR ad.filename LIKE ?)
            LIMIT 3
        ");
        $stmt->execute([$userId, "%$query%", "%$query%"]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'title' => $row['title'],
                'description' => 'Archived Document',
                'url' => 'archives.php?id=' . $row['id'],
                'icon' => 'fas fa-archive'
            ];
        }
    } catch (Exception $e) {
        // Table might not exist, skip
    }
    
    // Profile search
    if (stripos('profile', $query) !== false || stripos('account', $query) !== false || stripos('settings', $query) !== false) {
        $results[] = [
            'title' => 'My Profile',
            'description' => 'View and edit your profile',
            'url' => 'profile.php',
            'icon' => 'fas fa-user-circle'
        ];
    }
    
    // Report search
    if (stripos('report', $query) !== false || stripos('summary', $query) !== false) {
        $results[] = [
            'title' => 'Reports',
            'description' => 'View document reports and summaries',
            'url' => 'reports.php',
            'icon' => 'fas fa-chart-bar'
        ];
    }
    
    // Notifications search
    if (stripos('notification', $query) !== false || stripos('alert', $query) !== false) {
        $results[] = [
            'title' => 'Notifications',
            'description' => 'View your notifications',
            'url' => 'notifications.php',
            'icon' => 'fas fa-bell'
        ];
    }
    
    // References search
    if (stripos('reference', $query) !== false || stripos('guide', $query) !== false) {
        $results[] = [
            'title' => 'References',
            'description' => 'View accreditation references',
            'url' => 'references.php',
            'icon' => 'fas fa-book'
        ];
    }
    
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
?>
