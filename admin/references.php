<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'References Management';
$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    try {
        if (!isset($_FILES['reference_file']) || $_FILES['reference_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file selected or upload error occurred');
        }
        
        $file = $_FILES['reference_file'];
        $title = sanitize($_POST['reference_title'] ?? '');
        $description = sanitize($_POST['reference_description'] ?? '');
        
        if (empty($title)) {
            throw new Exception('Reference title is required');
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_types = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_types));
        }
        
        if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
            throw new Exception('File size exceeds 50MB limit');
        }
        
        // Create reference directory if not exists
        $ref_dir = '../storage/references/';
        if (!is_dir($ref_dir)) {
            mkdir($ref_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'reference_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
        $file_path = $ref_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        // Insert into database - use correct table name
        $query = "INSERT INTO reference_files (title, description, filename, file_path, uploaded_by, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([$title, $description, $file['name'], $file_path, $_SESSION['user_id']]);
        
        $success = 'Reference file uploaded successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $reference_id = $_POST['reference_id'] ?? null;
        
        if (!$reference_id) {
            throw new Exception('Reference ID is required');
        }
        
        // Get file path
        $query = "SELECT file_path FROM reference_files WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$reference_id]);
        $reference = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reference) {
            throw new Exception('Reference not found');
        }
        
        // Delete file
        if (file_exists($reference['file_path'])) {
            unlink($reference['file_path']);
        }
        
        // Delete from database
        $query = "DELETE FROM reference_files WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$reference_id]);
        
        $success = 'Reference file deleted successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all references - use correct table name
$query = "SELECT rf.*, u.full_name 
          FROM reference_files rf 
          LEFT JOIN users u ON rf.uploaded_by = u.id 
          ORDER BY rf.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$references = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
.reference-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reference-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: #4f46e5;
}

.reference-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.reference-description {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 12px;
    line-height: 1.5;
}

.reference-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: #9ca3af;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.reference-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.reference-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.upload-area {
    border: 2px dashed #3b82f6;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #f0f9ff;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.upload-area.dragging {
    border-color: #2563eb;
    background: #eff6ff;
}

.upload-icon {
    font-size: 48px;
    color: #3b82f6;
    margin-bottom: 12px;
}

.upload-text {
    color: #1f2937;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.upload-subtext {
    color: #6b7280;
    font-size: 14px;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-book"></i> Reference Files
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($references)): ?>
                    <div style="text-align: center; padding: 40px; color: #9ca3af;">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                        <p>No reference files uploaded yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($references as $ref): ?>
                        <div class="reference-card">
                            <div class="reference-title">
                                <i class="fas fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($ref['title']); ?>
                            </div>
                            
                            <?php if (!empty($ref['description'])): ?>
                                <div class="reference-description">
                                    <?php echo htmlspecialchars($ref['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reference-meta">
                                <span>
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($ref['full_name'] ?? 'Admin'); ?>
                                </span>
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y H:i', strtotime($ref['created_at'])); ?>
                                </span>
                                <span>
                                    <i class="fas fa-file"></i>
                                    <?php echo htmlspecialchars($ref['filename']); ?>
                                </span>
                            </div>
                            
                            <div class="reference-actions">
                                <a href="<?php echo htmlspecialchars($ref['file_path']); ?>" 
                                   class="btn btn-sm btn-primary" 
                                   target="_blank">
                                    <i class="fas fa-external-link-alt"></i> View
                                </a>
                                <a href="<?php echo htmlspecialchars($ref['file_path']); ?>" 
                                   class="btn btn-sm btn-info" 
                                   download>
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="reference_id" value="<?php echo $ref['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Delete this reference file?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Reference
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="form-group mb-3">
                        <label class="form-label"><strong>Reference Title</strong> <span class="text-danger">*</span></label>
                        <input type="text" name="reference_title" class="form-control" 
                               placeholder="e.g., Accreditation Standards 2024" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label"><strong>Description (Optional)</strong></label>
                        <textarea name="reference_description" class="form-control" 
                                  rows="3" placeholder="Add description or notes about this reference file..."></textarea>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label"><strong>Select File</strong> <span class="text-danger">*</span></label>
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">Drag and drop your file here</div>
                            <div class="upload-subtext">or click to browse</div>
                            <input type="file" name="reference_file" id="fileInput" 
                                   style="display: none;" accept=".pdf,.doc,.docx,.xlsx,.xls,.ppt,.pptx,.jpg,.jpeg,.png" required>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Supported: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG (Max 50MB)
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-upload"></i> Upload Reference File
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');

uploadArea.addEventListener('click', () => fileInput.click());

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragging');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragging');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragging');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        updateFileName();
    }
});

fileInput.addEventListener('change', updateFileName);

function updateFileName() {
    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        uploadArea.innerHTML = `
            <div class="upload-icon" style="color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="upload-text" style="color: #10b981;">File selected</div>
            <div class="upload-subtext">${fileName}</div>
        `;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
