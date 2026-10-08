<?php
/**
 * View Document API - Intelligent Previewer
 * 1. PDFs/Images: Streams directly to browser (Native Preview)
 * 2. Office Docs (Supabase): Redirects to Google Docs Viewer using PUBLIC URL
 * 3. Office Docs (Local): Falls back to download (No Preview available)
 */
require_once '../config/config.php';
requireLogin();

$document_id = $_GET['id'] ?? null;
$mode = $_GET['mode'] ?? 'stream'; // 'stream' or 'url'

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
    
    // Get file extension and original name
    $originalFilename = $document['original_filename'] ?? $document['filename'];
    $fileExt = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    
    // ==========================================
    // 1. INTELLIGENT PREVIEW LOGIC
    // ==========================================
    
    $officeExtensions = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    
    // Check if it is an Office document that needs Google Viewer
    if (in_array($fileExt, $officeExtensions)) {
        
        // Google Viewer ONLY works with Supabase (Publicly accessible URLs)
        if (!empty($document['supabase_path'])) {
            // FIX: Use PUBLIC URL format instead of Signed URL.
            // Format: https://[project].supabase.co/storage/v1/object/public/[bucket]/[path]
            
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $bucket = SUPABASE_BUCKET;
            $path = $document['supabase_path'];
            
            // Clean/Encode path components to ensure valid URL
            // We encode each segment of the path, but NOT the slashes
            $pathParts = explode('/', $path);
            $encodedPathParts = array_map('rawurlencode', $pathParts);
            $encodedPath = implode('/', $encodedPathParts);
            
            $publicUrl = "$supabaseUrl/storage/v1/object/public/$bucket/$encodedPath";
            
            // Redirect to Google Docs Viewer with our PUBLIC URL
            // 'embedded=true' removes the Google header/sidebar
            $googleViewerUrl = "https://docs.google.com/gview?url=" . urlencode($publicUrl) . "&embedded=true";
            
            header("Location: " . $googleViewerUrl);
            exit;
        }
        
        // If we are here, it's a Local File (C:/xampp). Google cannot see local files.
        // We set a flag to force download later in the script.
        $forceDownload = true;
    }

    // ==========================================
    // 2. STANDARD STREAMING LOGIC (PDFs, Images, Local Files)
    // ==========================================

    // Determine MIME type
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain'
    ];
    
    $mimeType = $document['mime_type'] ?? ($mimeTypes[$fileExt] ?? 'application/octet-stream');
    
    $fileContent = null;
    
    // Try Fetching from Supabase (if not redirected to Google Viewer)
    if (!empty($document['supabase_path']) && !empty(SUPABASE_URL) && !empty(SUPABASE_SERVICE_KEY)) {
        $supabaseUrl = SUPABASE_URL;
        $supabaseKey = SUPABASE_SERVICE_KEY;
        $bucket = SUPABASE_BUCKET;
        $path = $document['supabase_path'];
        
        // For internal fetching, we still use the standard download endpoint
        $downloadUrl = rtrim($supabaseUrl, '/') . "/storage/v1/object/{$bucket}/{$path}";
        
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
            $fileContent = null;
        }
    }
    
    // Fallback to local storage
    if ($fileContent === null) {
        $filePath = $document['file_path'];
        
        // If path doesn't start with absolute path, prepend UPLOAD_PATH
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
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($fileContent));
    header('Cache-Control: private, max-age=3600');
    
    // Decide: Inline (Preview) or Attachment (Download)
    $inlineTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'html'];
    
    if (isset($forceDownload) && $forceDownload) {
        // If it was an Office doc but Local, we MUST download it
        header('Content-Disposition: attachment; filename="' . $originalFilename . '"');
    } 
    elseif (in_array($fileExt, $inlineTypes)) {
        // Native browser preview
        header('Content-Disposition: inline; filename="' . $originalFilename . '"');
    } 
    else {
        // Catch-all for other types
        header('Content-Disposition: attachment; filename="' . $originalFilename . '"');
    }
    
    echo $fileContent;
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>