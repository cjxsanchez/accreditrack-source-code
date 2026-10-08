<?php
/**
 * Document Upload API with Supabase Storage Integration
 * Uploads files to Supabase Storage and stores metadata in MySQL
 */
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

function uploadToSupabase($filePath, $fileName, $mimeType) {
    $supabaseUrl = SUPABASE_URL;
    $supabaseKey = SUPABASE_SERVICE_KEY;
    $bucket = SUPABASE_BUCKET;
    
    if (empty($supabaseUrl) || empty($supabaseKey)) {
        return ['success' => false, 'message' => 'Supabase not configured'];
    }
    
    // Generate unique path: faculty_docs/{user_id}/{timestamp}_{filename}
    $userId = $_SESSION['user_id'];
    $timestamp = time();
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $supabasePath = "faculty_docs/{$userId}/{$timestamp}_{$safeName}";
    
    // Read file content
    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) {
        return ['success' => false, 'message' => 'Failed to read file'];
    }
    
    // Build Supabase Storage REST API URL
    $uploadUrl = rtrim($supabaseUrl, '/') . "/storage/v1/object/{$bucket}/{$supabasePath}";
    
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $uploadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'cURL error: ' . $curlError];
    }
    
    // Check response
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'path' => $supabasePath,
            'url' => rtrim($supabaseUrl, '/') . "/storage/v1/object/public/{$bucket}/{$supabasePath}"
        ];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['message'] ?? $errorData['error'] ?? 'Upload failed';
        return ['success' => false, 'message' => $errorMsg, 'http_code' => $httpCode];
    }
}

try {
    $indicator_id = $_POST['indicator_id'] ?? null;
    $folder_id = $_POST['folder_id'] ?? null;
    $requirement_id = $_POST['requirement_id'] ?? null;
    $title = sanitize($_POST['title'] ?? '');
    
    if (!$indicator_id || !$folder_id || !$requirement_id || !$title) {
        throw new Exception('Missing required fields');
    }
    
    // Verify user has access to this indicator
    if (isStaff()) {
        $query = "SELECT i.* FROM indicators i
                  JOIN parameters p ON i.parameter_id = p.id
                  JOIN areas a ON p.area_id = a.id
                  WHERE i.id = ? AND a.assigned_faculty_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$indicator_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Access denied');
        }
    }
    
    // Handle file uploads
    if (!isset($_FILES['files']) && !isset($_FILES['file'])) {
        throw new Exception('No files selected');
    }
    
    $files_to_process = [];
    
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $files = $_FILES['files'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $files_to_process[] = [
                    'name' => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'type' => $files['type'][$i],
                    'error' => $files['error'][$i]
                ];
            }
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $files_to_process[] = $_FILES['file'];
    }
    
    if (empty($files_to_process)) {
        throw new Exception('No valid files to upload');
    }
    
    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'ppt', 'pptx'];
    $max_size = 10 * 1024 * 1024; // 10MB
    $uploaded_files = [];
    $document_ids = [];
    
    $useSupabase = !empty(SUPABASE_URL) && !empty(SUPABASE_SERVICE_KEY);
    
    foreach ($files_to_process as $file) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('Invalid file type: ' . $file['name']);
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('File too large: ' . $file['name']);
        }
        
        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $filename = 'doc_' . $indicator_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $supabasePath = null;
        $localPath = null;
        
        if ($useSupabase) {
            $uploadResult = uploadToSupabase($file['tmp_name'], $file['name'], $mimeType);
            
            if ($uploadResult['success']) {
                $supabasePath = $uploadResult['path'];
                // Also save locally as backup
                $localPath = UPLOAD_PATH . $filename;
                move_uploaded_file($file['tmp_name'], $localPath);
            } else {
                // Fallback to local storage if Supabase fails
                $localPath = UPLOAD_PATH . $filename;
                if (!move_uploaded_file($file['tmp_name'], $localPath)) {
                    throw new Exception('Failed to upload: ' . $file['name']);
                }
            }
        } else {
            // Local storage only
            $localPath = UPLOAD_PATH . $filename;
            if (!move_uploaded_file($file['tmp_name'], $localPath)) {
                throw new Exception('Failed to upload: ' . $file['name']);
            }
        }
        
        $uploaded_files[] = [
            'original_name' => $file['name'],
            'filename' => $filename,
            'path' => $localPath,
            'supabase_path' => $supabasePath,
            'size' => $file['size'],
            'mime_type' => $mimeType
        ];
    }
    
    if (empty($uploaded_files)) {
        throw new Exception('Failed to upload files');
    }
    
    // Insert documents into database
    foreach ($uploaded_files as $file) {
        $file_title = count($uploaded_files) > 1 ? $title . ' - ' . $file['original_name'] : $title;
        
        $query = "INSERT INTO documents (indicator_id, folder_id, requirement_id, title, filename, file_path, original_filename, uploaded_by, status, supabase_path, file_size, mime_type) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $indicator_id, 
            $folder_id, 
            $requirement_id, 
            $file_title, 
            $file['filename'], 
            $file['path'], 
            $file['original_name'], 
            $_SESSION['user_id'],
            $file['supabase_path'],
            $file['size'],
            $file['mime_type']
        ]);
        
        $document_id = $db->lastInsertId();
        $document_ids[] = $document_id;
    }
    
    // Send notification to admin
    $query = "SELECT id FROM users WHERE role = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($admins as $admin) {
        $query = "INSERT INTO notifications (user_id, title, message, type, related_document_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $admin['id'],
            'New Documents Uploaded',
            $_SESSION['full_name'] . ' uploaded ' . count($uploaded_files) . ' document(s): ' . $title,
            'upload',
            $document_ids[0] ?? null
        ]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => count($uploaded_files) . ' document(s) uploaded successfully',
        'document_ids' => $document_ids,
        'storage' => $useSupabase && !empty($uploaded_files[0]['supabase_path']) ? 'supabase' : 'local'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
