<?php
/**
 * Get Document API - Combined View/Download/Info
 * Supports action=view, action=download, or returns JSON info
 */
require_once '../config/config.php';
requireLogin();

$document_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'info';

if (!$document_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // FIX: Removed 'gf.name as folder_name' and changed 'gr.name' to 'gr.requirement_name'
    // This prevents the "Unknown column" SQL error
    $query = "SELECT d.*, u.full_name as uploader_name, i.name as indicator_name, i.indicator_type,
                     p.name as parameter_name, p.parameter_letter,
                     a.name as area_name, a.area_number,
                     l.name as level_name, l.level_type,
                     pr.name as program_name, pr.code as program_code,
                     gr.requirement_name
              FROM documents d
              JOIN users u ON d.uploaded_by = u.id
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              JOIN areas a ON p.area_id = a.id
              JOIN levels l ON a.level_id = l.id
              JOIN programs pr ON l.program_id = pr.id
              LEFT JOIN global_accreditation_tags gf ON d.folder_id = gf.id
              LEFT JOIN global_requirements gr ON d.requirement_id = gr.id
              WHERE d.id = ?";
    
    // Add access control for staff users
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
    
    if ($action === 'view' || $action === 'download') {
        $fileContent = null;
        $originalFilename = $document['original_filename'] ?? $document['filename'];
        $fileExt = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        
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
            'txt' => 'text/plain',
            'csv' => 'text/csv'
        ];
        
        $mimeType = $document['mime_type'] ?? ($mimeTypes[$fileExt] ?? 'application/octet-stream');
        
        // Handle Supabase Storage
        if (!empty($document['supabase_path']) && defined('SUPABASE_URL') && !empty(SUPABASE_URL)) {
            $supabaseUrl = SUPABASE_URL;
            $supabaseKey = SUPABASE_SERVICE_KEY;
            $bucket = SUPABASE_BUCKET;
            $path = $document['supabase_path'];
            
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
                $fileContent = null; // Fall back to local if cloud fails
            }
        }
        
        // Handle Local Storage (Fallback)
        if ($fileContent === null) {
            $filePath = $document['file_path'];
            
            // Check if file exists at stored path
            if (!file_exists($filePath)) {
                // Try alternative path
                $filePath = UPLOAD_PATH . $document['filename'];
            }
            
            if (!file_exists($filePath)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'File not found on server']);
                exit;
            }
            
            $fileContent = file_get_contents($filePath);
        }
        
        if ($fileContent === false || $fileContent === null) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve file']);
            exit;
        }
        
        // Clear output buffers to ensure clean file output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        if ($action === 'download') {
            // Force download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $originalFilename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
        } else {
            // Inline view
            header('Content-Type: ' . $mimeType);
            if (in_array($fileExt, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'])) {
                header('Content-Disposition: inline; filename="' . $originalFilename . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $originalFilename . '"');
            }
        }
        
        header('Content-Length: ' . strlen($fileContent));
        
        echo $fileContent;
        exit;
    }
    
    // Default: return document info as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'document' => $document
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>