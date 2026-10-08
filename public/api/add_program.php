<?php
/**
 * Add new program
 */
require_once dirname(__DIR__) . '/../config/config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([], 400, 'Invalid request method');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse([], 403, 'CSRF token invalid');
}

$name = sanitize($_POST['name'] ?? '');
$code = sanitize($_POST['code'] ?? '');
$description = sanitize($_POST['description'] ?? '');

if (empty($name) || empty($code)) {
    jsonResponse([], 400, 'Program name and code are required');
}

try {
    $db = getDB();
    
    // Check if program code already exists
    $query = "SELECT id FROM programs WHERE code = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        jsonResponse([], 400, 'Program code already exists');
    }
    
    // Create program
    $query = "INSERT INTO programs (name, code, description, status, created_at) 
              VALUES (?, ?, ?, 'active', NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$name, $code, $description]);
    
    logMessage("Program created: $name", 'info');
    redirectWithMessage('public/admin/programs.php', 'Program created successfully', 'success');
} catch (Exception $e) {
    logMessage('Error creating program: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error creating program');
}
?>
