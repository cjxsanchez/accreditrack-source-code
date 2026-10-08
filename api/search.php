<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$db = getDB();
$results = [];

try {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['results' => [], 'error' => 'Unauthorized']);
        exit;
    }
    
    // Search programs
    $stmt = $db->prepare("SELECT id, name, code FROM programs WHERE status = 'active' AND (name LIKE ? OR code LIKE ?) LIMIT 5");
    $stmt->execute(["%$query%", "%$query%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'title' => $row['name'],
            'description' => 'Program: ' . $row['code'],
            'url' => 'programs.php?id=' . $row['id'],
            'icon' => 'fas fa-folder'
        ];
    }
    
    // Search users
    $stmt = $db->prepare("SELECT id, full_name, email, role, status FROM users WHERE full_name LIKE ? OR email LIKE ? OR username LIKE ? LIMIT 5");
    $stmt->execute(["%$query%", "%$query%", "%$query%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'title' => $row['full_name'],
            'description' => ucfirst($row['role']) . ' (' . ucfirst($row['status']) . ') - ' . $row['email'],
            'url' => 'user_management.php?search=' . urlencode($row['full_name']),
            'icon' => 'fas fa-user'
        ];
    }
    
    // Search documents
    $stmt = $db->prepare("
        SELECT d.id, d.title, d.filename, d.status, p.name as program_name, l.level_type
        FROM documents d 
        JOIN indicators i ON d.indicator_id = i.id
        JOIN parameters pm ON i.parameter_id = pm.id
        JOIN areas a ON pm.area_id = a.id
        JOIN levels l ON a.level_id = l.id
        JOIN programs p ON l.program_id = p.id
        WHERE d.title LIKE ? OR d.filename LIKE ? 
        LIMIT 5
    ");
    $stmt->execute(["%$query%", "%$query%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'title' => $row['title'],
            'description' => ucfirst($row['status']) . ' - ' . $row['program_name'] . ' ' . $row['level_type'],
            'url' => 'validation.php?doc=' . $row['id'],
            'icon' => 'fas fa-file-alt'
        ];
    }
    
    // Search archived programs
    $stmt = $db->prepare("SELECT id, name, code FROM programs WHERE status = 'archived' AND (name LIKE ? OR code LIKE ?) LIMIT 3");
    $stmt->execute(["%$query%", "%$query%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'title' => $row['name'],
            'description' => 'Archived Program: ' . $row['code'],
            'url' => 'archives.php?id=' . $row['id'],
            'icon' => 'fas fa-archive'
        ];
    }
    
    // Search by date (if query looks like a date)
    if (preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $query) || 
        preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}$/', $query)) {
        $results[] = [
            'title' => 'Search by date: ' . $query,
            'description' => 'View documents from this date',
            'url' => 'validation.php?date=' . urlencode($query),
            'icon' => 'fas fa-calendar'
        ];
    }
    
    // Profile search
    if (stripos('profile', $query) !== false || stripos('account', $query) !== false || stripos('settings', $query) !== false) {
        $results[] = [
            'title' => 'My Profile',
            'description' => 'View and edit your profile settings',
            'url' => 'profile.php',
            'icon' => 'fas fa-user-circle'
        ];
    }
    
    // Reports search
    if (stripos('report', $query) !== false || stripos('analytics', $query) !== false) {
        $results[] = [
            'title' => 'Reports & Analytics',
            'description' => 'View system reports and statistics',
            'url' => 'reports.php',
            'icon' => 'fas fa-chart-bar'
        ];
    }
    
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
?>
