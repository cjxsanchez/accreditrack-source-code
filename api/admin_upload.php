<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $indicator_id = $_POST['indicator_id'] ?? null;
    $codename = sanitize($_POST['codename'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    
    if (!$indicator_id || !$codename || !$title) {
        throw new Exception('Missing required fields');
    }
    
    // Handle multiple file uploads
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        throw new Exception('No files selected');
    }
    
    $files = $_FILES['files'];
    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
    $max_size = 10 * 1024 * 1024; // 10MB
    $uploaded_files = [];
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $file_ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('Invalid file type: ' . $files['name'][$i]);
        }
        
        if ($files['size'][$i] > $max_size) {
            throw new Exception('File too large: ' . $files['name'][$i]);
        }
        
        // Generate unique filename
        $filename = 'admin_doc_' . $indicator_id . '_' . time() . '_' . $i . '.' . $file_ext;
        $upload_path = UPLOAD_PATH . $filename;
        
        if (move_uploaded_file($files['tmp_name'][$i], $upload_path)) {
            $uploaded_files[] = [
                'original_name' => $files['name'][$i],
                'filename' => $filename,
                'path' => $upload_path
            ];
        }
    }
    
    if (empty($uploaded_files)) {
        throw new Exception('No files were uploaded successfully');
    }
    
    // Insert documents into database
    foreach ($uploaded_files as $file) {
        $file_title = count($uploaded_files) > 1 ? $title . ' - ' . $file['original_name'] : $title;
        
        $query = "INSERT INTO documents (indicator_id, codename, title, filename, file_path, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, 'approved')";
        $stmt = $db->prepare($query);
        $stmt->execute([$indicator_id, $codename, $file_title, $file['original_name'], $file['path'], $_SESSION['user_id']]);
    }
    
    echo json_encode(['success' => true, 'message' => count($uploaded_files) . ' document(s) uploaded successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
