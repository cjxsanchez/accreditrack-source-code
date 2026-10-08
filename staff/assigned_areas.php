<?php
require_once '../config/config.php';
requireStaff();

$page_title = 'Assigned Areas';

$database = new Database();
$db = $database->getConnection();

// Get assigned areas for the current user
$query = "SELECT a.*, l.name as level_name, l.level_type, p.name as program_name, p.code as program_code
          FROM areas a
          JOIN levels l ON a.level_id = l.id
          JOIN programs p ON l.program_id = p.id
          WHERE a.assigned_faculty_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$assigned_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specific area if requested
$selected_area = null;
$area_structure = [];

if (isset($_GET['area_id'])) {
    $area_id = $_GET['area_id'];
    
    // Verify user has access to this area
    $query = "SELECT a.*, l.name as level_name, l.level_type, p.name as program_name, p.code as program_code
              FROM areas a
              JOIN levels l ON a.level_id = l.id
              JOIN programs p ON l.program_id = p.id
              WHERE a.id = ? AND a.assigned_faculty_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$area_id, $_SESSION['user_id']]);
    $selected_area = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_area) {
        // Get area structure with parameters and indicators
        $query = "SELECT p.*, i.id as indicator_id, i.name as indicator_name, i.indicator_type
                  FROM parameters p
                  LEFT JOIN indicators i ON p.id = i.parameter_id
                  WHERE p.area_id = ?
                  ORDER BY p.id, i.indicator_type";
        $stmt = $db->prepare($query);
        $stmt->execute([$area_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize data structure
        foreach ($results as $row) {
            $param_id = $row['id'];
            if (!isset($area_structure[$param_id])) {
                $area_structure[$param_id] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'parameter_order' => $row['parameter_letter'] ?? '',
                    'indicators' => []
                ];
            }
            
            if ($row['indicator_id']) {
                $query = "SELECT d.*, 
                          gat.folder_name, 
                          gr.requirement_name, gr.requirement_code
                          FROM documents d
                          LEFT JOIN global_accreditation_tags gat ON d.folder_id = gat.id
                          LEFT JOIN global_requirements gr ON d.requirement_id = gr.id
                          WHERE d.indicator_id = ? AND d.uploaded_by = ? 
                          ORDER BY d.id DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$row['indicator_id'], $_SESSION['user_id']]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $area_structure[$param_id]['indicators'][] = [
                    'id' => $row['indicator_id'],
                    'name' => $row['indicator_name'],
                    'type' => $row['indicator_type'],
                    'documents' => $documents
                ];
            }
        }
    }
}

function getIndicatorTypeName($type) {
    switch ($type) {
        case 'S': return 'System - Inputs and Processes';
        case 'I': return 'Implementation';
        case 'O': return 'Outcome/s';
        default: return $type;
    }
}

include '../includes/header.php';
?>

<style>
.document-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 15px;
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.12);
}

.document-card.approved {
    border-left: 4px solid #28a745;
}

.document-card.pending {
    border-left: 4px solid #ffc107;
}

.document-card.rejected {
    border-left: 4px solid #dc3545;
}

.document-card .card-body {
    padding: 15px 20px;
}

.document-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.document-meta {
    font-size: 0.85rem;
    color: #6c757d;
}

.document-meta .badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

.folder-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
    margin-right: 5px;
}

.requirement-badge {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
}

.indicator-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 15px;
    border-radius: 8px 8px 0 0;
    font-weight: 600;
}

.indicator-header.system {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.indicator-header.implementation {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.indicator-header.outcome {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.document-actions .btn {
    padding: 5px 10px;
    font-size: 0.8rem;
    border-radius: 6px;
}

.admin-comment {
    background: #fff3cd;
    border-left: 3px solid #ffc107;
    padding: 10px 15px;
    border-radius: 0 8px 8px 0;
    margin-top: 10px;
    font-size: 0.85rem;
}

.upload-area {
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
}

.upload-area:hover {
    border-color: #667eea;
    background: linear-gradient(145deg, #fff 0%, #f8f9fa 100%);
}

.btn-archive {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
    border: none;
}

.btn-archive:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    color: white;
}
</style>

<?php if (empty($assigned_areas)): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i> 
        You have not been assigned to any areas yet. Please contact the administrator.
    </div>
<?php else: ?>
    
    <?php if (!$selected_area): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Select an Area to Manage</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($assigned_areas as $area): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($area['name']); ?></h6>
                                            <p class="card-text">
                                                <strong>Program:</strong> <?php echo htmlspecialchars($area['program_name']); ?><br>
                                                <strong>Code:</strong> <?php echo htmlspecialchars($area['program_code']); ?><br>
                                                <strong>Level:</strong> <?php echo htmlspecialchars($area['level_name']); ?> 
                                            </p>
                                            <a href="?area_id=<?php echo $area['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-folder-open"></i> Manage Documents
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="row mb-3">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="assigned_areas.php">Assigned Areas</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($selected_area['name']); ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php echo htmlspecialchars($selected_area['name']); ?>
                            <small class="text-muted">- <?php echo htmlspecialchars($selected_area['program_name']); ?></small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Level:</strong> <?php echo htmlspecialchars($selected_area['level_name']); ?> </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($area_structure)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No parameters have been set up for this area yet.
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="accordion" id="parametersAccordion">
                        <?php foreach ($area_structure as $parameter): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $parameter['id']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $parameter['id']; ?>">
                                        <strong><?php echo htmlspecialchars($parameter['parameter_order']); ?>.</strong>
                                        <span class="ms-2"><?php echo htmlspecialchars($parameter['name']); ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $parameter['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#parametersAccordion">
                                    <div class="accordion-body">
                                        <?php if (empty($parameter['indicators'])): ?>
                                            <p class="text-muted">No indicators available for this parameter.</p>
                                        <?php else: ?>
                                            <div class="row">
                                                <?php foreach ($parameter['indicators'] as $indicator): ?>
                                                    <?php 
                                                    $headerClass = 'system';
                                                    if ($indicator['type'] == 'I') $headerClass = 'implementation';
                                                    if ($indicator['type'] == 'O') $headerClass = 'outcome';
                                                    ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card h-100 border-0 shadow-sm">
                                                            <div class="indicator-header <?php echo $headerClass; ?>">
                                                                <i class="fas fa-<?php echo $indicator['type'] == 'S' ? 'cogs' : ($indicator['type'] == 'I' ? 'tasks' : 'chart-line'); ?> me-2"></i>
                                                                <?php echo getIndicatorTypeName($indicator['type']); ?>
                                                            </div>
                                                            <div class="card-body">
                                                                <?php if (!empty($indicator['documents'])): ?>
                                                                    <div class="documents-list">
                                                                        <?php foreach ($indicator['documents'] as $doc): ?>
                                                                            <div class="document-card <?php echo $doc['status']; ?>">
                                                                                <div class="card-body">
                                                                                    <h6 class="document-title"><?php echo htmlspecialchars($doc['title']); ?></h6>
                                                                                    
                                                                                    <div class="mb-2">
                                                                                        <?php if (!empty($doc['folder_name'])): ?>
                                                                                            <span class="folder-badge">
                                                                                                <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($doc['folder_name']); ?>
                                                                                            </span>
                                                                                        <?php endif; ?>
                                                                                        <?php if (!empty($doc['requirement_name'])): ?>
                                                                                            <span class="requirement-badge">
                                                                                                <i class="fas fa-file-alt me-1"></i><?php echo htmlspecialchars($doc['requirement_code'] ?? ''); ?> - <?php echo htmlspecialchars($doc['requirement_name']); ?>
                                                                                            </span>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                    
                                                                                    <div class="document-meta">
                                                                                        <?php if (!empty($doc['filename'])): ?>
                                                                                            <p class="mb-1"><i class="fas fa-paperclip me-1"></i><?php echo htmlspecialchars($doc['filename']); ?></p>
                                                                                        <?php endif; ?>
                                                                                        <p class="mb-1">
                                                                                            <strong>Status:</strong> 
                                                                                            <span class="badge bg-<?php echo $doc['status'] == 'approved' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                                                                <?php echo ucfirst($doc['status']); ?>
                                                                                            </span>
                                                                                        </p>
                                                                                        <p class="mb-0">
                                                                                            <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y g:i A', strtotime($doc['created_at'])); ?>
                                                                                        </p>
                                                                                    </div>
                                                                                    
                                                                                    <?php if (!empty($doc['admin_comment'])): ?>
                                                                                        <div class="admin-comment">
                                                                                            <strong><i class="fas fa-comment me-1"></i>Admin Comments:</strong><br>
                                                                                            <?php echo htmlspecialchars($doc['admin_comment']); ?>
                                                                                        </div>
                                                                                    <?php endif; ?>
                                                                                    
                                                                                    <div class="document-actions mt-3">
                                                                                        <div class="btn-group w-100" role="group">
                                                                                            <button class="btn btn-outline-info btn-sm" onclick="viewDocument('../api/view_document.php?id=<?php echo $doc['id']; ?>', '<?php echo htmlspecialchars($doc['filename'] ?? $doc['title']); ?>')">
                                                                                                <i class="fas fa-eye"></i> View
                                                                                            </button>
                                                                                            <a href="../api/download.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                                                                <i class="fas fa-download"></i> Download
                                                                                            </a>
                                                                                            <button class="btn btn-archive btn-sm" onclick="archiveDocument(<?php echo $doc['id']; ?>)">
                                                                                                <i class="fas fa-archive"></i> Archive
                                                                                            </button>
                                                                                            
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                        
                                                                        <button class="btn btn-success btn-sm w-100 mt-2" onclick="uploadDocument(<?php echo $indicator['id']; ?>)">
                                                                            <i class="fas fa-plus"></i> Add More Documents
                                                                        </button>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="upload-area">
                                                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                                                                        <p class="text-muted mb-3">No documents uploaded</p>
                                                                        <button class="btn btn-primary" onclick="uploadDocument(<?php echo $indicator['id']; ?>)">
                                                                            <i class="fas fa-upload"></i> Upload Documents
                                                                        </button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="indicatorId" name="indicator_id">
                    
                    <div class="mb-3">
                        <label for="documentFolder" class="form-label">Select Folder</label>
                        <select class="form-select" id="documentFolder" name="folder_id" onchange="loadRequirements()" required>
                            <option value="">-- Choose a folder --</option>
                        </select>
                        <div class="form-text">Select which folder this document belongs to</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="documentRequirement" class="form-label">Select Requirement</label>
                        <select class="form-select" id="documentRequirement" name="requirement_id" required>
                            <option value="">-- Choose a requirement --</option>
                        </select>
                        <div class="form-text">Select which specific requirement this document fulfills</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Document Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="files" class="form-label">Select Files (Multiple files allowed)</label>
                        <input type="file" class="form-control" id="files" name="files[]" multiple required>
                        <div class="form-text">Supported formats: PDF, DOC, DOCX, JPG, PNG (Max: 10MB per file)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Document Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe id="documentViewer" src="" style="width: 100%; height: 70vh; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function uploadDocument(indicatorId) {
    document.getElementById('indicatorId').value = indicatorId;
    
    const folderSelect = document.getElementById('documentFolder');
    const requirementSelect = document.getElementById('documentRequirement');
    folderSelect.innerHTML = '<option value="">-- Loading folders --</option>';
    requirementSelect.innerHTML = '<option value="">-- Choose a requirement --</option>';
    
    // Fetch folders for this indicator
    fetch(`../api/folder_requirements_manager.php?action=get_folders_by_indicator&indicator_id=${indicatorId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            folderSelect.innerHTML = '<option value="">-- Choose a folder --</option>';
            
            if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                data.data.forEach(folder => {
                    const option = document.createElement('option');
                    option.value = folder.id;
                    option.textContent = folder.folder_name;
                    folderSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.disabled = true;
                option.textContent = 'No folders available - Admin needs to create folders first';
                folderSelect.appendChild(option);
            }
        })
        .catch(error => {
            console.error('Error loading folders:', error);
            folderSelect.innerHTML = '<option value="">-- Error loading folders --</option>';
            const option = document.createElement('option');
            option.value = '';
            option.disabled = true;
            option.textContent = 'Failed to load folders. Please try again.';
            folderSelect.appendChild(option);
        });
    
    new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

function loadRequirements() {
    const folderId = document.getElementById('documentFolder').value;
    const requirementSelect = document.getElementById('documentRequirement');
    
    requirementSelect.innerHTML = '<option value="">-- Loading requirements --</option>';
    
    if (!folderId) {
        requirementSelect.innerHTML = '<option value="">-- Choose a requirement --</option>';
        return;
    }
    
    fetch(`../api/folder_requirements_manager.php?action=get_requirements_by_folder&folder_id=${folderId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            requirementSelect.innerHTML = '<option value="">-- Choose a requirement --</option>';
            
            if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                data.data.forEach(req => {
                    const option = document.createElement('option');
                    option.value = req.id;
                    option.textContent = `${req.requirement_code} - ${req.requirement_name}`;
                    requirementSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.disabled = true;
                option.textContent = 'No requirements in this folder';
                requirementSelect.appendChild(option);
            }
        })
        .catch(error => {
            console.error('Error loading requirements:', error);
            requirementSelect.innerHTML = '<option value="">-- Error loading requirements --</option>';
        });
}

function viewDocument(path, filename) {
    document.getElementById('viewModalTitle').textContent = filename;
    document.getElementById('documentViewer').src = path;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

function deleteDocument(docId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../api/upload.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&document_id=${docId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        'Your document has been deleted.',
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        'Error: ' + data.message,
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'An error occurred while deleting the document',
                    'error'
                );
            });
        }
    });
}

function archiveDocument(docId) {
    Swal.fire({
        title: 'Archive Document?',
        text: "You can restore it later from the Archives section.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6b7280',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, archive it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../api/archive_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ document_id: docId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Archived!',
                        'Document archived successfully',
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        'Error: ' + data.message,
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'An error occurred while archiving the document',
                    'error'
                );
            });
        }
    });
}

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'upload');
    
    // Validate folder and requirement are selected
    const folderId = document.getElementById('documentFolder').value;
    const requirementId = document.getElementById('documentRequirement').value;
    
    if (!folderId) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select a folder first'
        });
        return;
    }
    
    if (!requirementId) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select a requirement'
        });
        return;
    }
    
    // Show Loading Animation
    Swal.fire({
        title: 'Uploading...',
        html: 'Please wait while we upload your documents.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('../api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show Success Message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Documents uploaded successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: 'Error: ' + data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while uploading'
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>