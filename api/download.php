<?php
/**
 * Document Download API - Proxy Implementation
 * Fetches file from Supabase Storage or local storage and streams to browser
 * 
 * Usage: download.php?id={doc_id}
 */
require_once '../config/config.php';
requireLogin();

$document_id = $_GET['id'] ?? null;

if (!$document_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Query document with access control
    $query = "SELECT d.*, u.full_name as uploader_name
              FROM documents d
              JOIN users u ON d.uploaded_by = u.id
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              WHERE d.id = ?";
    
    if (isStaff()) {
        $query .= " AND a.assigned_faculty_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$document_id, $_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare($query);
        $stmt->execute([$document_id]);
    }
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
        exit;
    }
    
    $fileContent = null;
    $originalFilename = $document['original_filename'] ?? $document['filename'];
    $mimeType = $document['mime_type'] ?? 'application/octet-stream';
    
    if (!empty($document['supabase_path']) && !empty(SUPABASE_URL) && !empty(SUPABASE_SERVICE_KEY)) {
        $supabaseUrl = SUPABASE_URL;
        $supabaseKey = SUPABASE_SERVICE_KEY;
        $bucket = SUPABASE_BUCKET;
        $path = $document['supabase_path'];
        
        // Build download URL
        $downloadUrl = rtrim($supabaseUrl, '/') . "/storage/v1/object/{$bucket}/{$path}";
        
        // Fetch file using cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $downloadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $supabaseKey
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $fileContent = null; // Fall back to local
        }
    }
    
    if ($fileContent === null) {
        $filePath = $document['file_path'];
        
        if (!file_exists($filePath)) {
            $filePath = UPLOAD_PATH . $document['filename'];
        }
        
        if (!file_exists($filePath)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        $fileContent = file_get_contents($filePath);
    }
    
    if ($fileContent === false || $fileContent === null) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve file']);
        exit;
    }
    
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $originalFilename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($fileContent));
    
    // Output file content
    echo $fileContent;
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
