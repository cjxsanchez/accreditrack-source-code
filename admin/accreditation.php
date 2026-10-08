<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Accreditation Documents';

$database = new Database();
$db = $database->getConnection();

// Get all uploaded documents with related information
$query = "SELECT d.*, u.full_name as uploader_name, i.name as indicator_name, i.indicator_type,
                 p.name as parameter_name, p.parameter_order,
                 a.name as area_name, a.area_number,
                 l.name as level_name, l.level_type,
                 pr.name as program_name, pr.code as program_code
          FROM documents d
          JOIN users u ON d.uploaded_by = u.id
          JOIN indicators i ON d.indicator_id = i.id
          JOIN parameters p ON i.parameter_id = p.id
          JOIN areas a ON p.area_id = a.id
          JOIN levels l ON a.level_id = l.id
          JOIN programs pr ON l.program_id = pr.id
          ORDER BY d.uploaded_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle document approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $document_id = $_POST['document_id'];
    $status = $_POST['status'];
    $comment = sanitize($_POST['comment'] ?? '');
    
    $query = "UPDATE documents SET status = ?, admin_comments = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$status, $comment, $_SESSION['user_id'], $document_id])) {
        // Get document info for notification
        $query = "SELECT d.uploaded_by, d.title, u.full_name FROM documents d JOIN users u ON d.uploaded_by = u.id WHERE d.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$document_id]);
        $doc_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send notification to uploader
        $notification_title = "Document " . ucfirst($status);
        $notification_message = "Your document '{$doc_info['title']}' has been {$status}.";
        if ($comment) {
            $notification_message .= " Comment: {$comment}";
        }
        
        $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$doc_info['uploaded_by'], $notification_title, $notification_message, $status == 'approved' ? 'approval' : 'rejection']);
        
        $success = "Document {$status} successfully!";
    } else {
        $error = "Failed to update document status.";
    }
}

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Document Review</h5>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control" placeholder="Search documents..." onkeyup="filterTable(this, 'documentsTable')">
                        <select class="form-select" id="statusFilter" onchange="filterByStatus()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="edited">Edited</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="documentsTable">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Program/Level</th>
                                <th>Area/Parameter</th>
                                <th>Indicator</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
                                            <br>
                                            <small class="text-muted">Code: <?php echo htmlspecialchars($doc['codename']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($doc['program_code']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($doc['level_name']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($doc['area_number']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($doc['parameter_order']); ?>. <?php echo htmlspecialchars($doc['parameter_name']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($doc['indicator_type']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['uploader_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $doc['status'] == 'approved' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewDocument('<?php echo $doc['file_path']; ?>', '<?php echo htmlspecialchars($doc['filename']); ?>')" data-bs-toggle="tooltip" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-outline-info" download data-bs-toggle="tooltip" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($doc['status'] == 'pending'): ?>
                                                <button class="btn btn-outline-success" onclick="approveDocument(<?php echo $doc['id']; ?>)" data-bs-toggle="tooltip" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="rejectDocument(<?php echo $doc['id']; ?>)" data-bs-toggle="tooltip" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-danger" onclick="deleteDocument(<?php echo $doc['id']; ?>)" data-bs-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Document Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="document_id" id="reviewDocumentId">
                    <input type="hidden" name="status" id="reviewStatus">
                    
                    <div id="documentInfo"></div>
                    
                    <div class="mb-3">
                        <label for="comment" class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" name="comment" id="comment" rows="4" placeholder="Add your comments here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="reviewSubmitBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveDocument(documentId) {
    showReviewModal(documentId, 'approved', 'Approve Document', 'btn-success');
}

function rejectDocument(documentId) {
    showReviewModal(documentId, 'rejected', 'Reject Document', 'btn-danger');
}

function showReviewModal(documentId, status, title, btnClass) {
    document.getElementById('reviewDocumentId').value = documentId;
    document.getElementById('reviewStatus').value = status;
    document.querySelector('#reviewModal .modal-title').textContent = title;
    
    const submitBtn = document.getElementById('reviewSubmitBtn');
    submitBtn.className = `btn ${btnClass}`;
    submitBtn.textContent = status === 'approved' ? 'Approve' : 'Reject';
    
    // Load document info
    fetch(`../api/get_document.php?id=${documentId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('documentInfo').innerHTML = `
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">${data.title}</h6>
                        <p class="card-text">
                            <strong>Code:</strong> ${data.codename}<br>
                            <strong>Program:</strong> ${data.program_name}<br>
                            <strong>Area:</strong> ${data.area_name}<br>
                            <strong>Parameter:</strong> ${data.parameter_name}<br>
                            <strong>Indicator:</strong> ${data.indicator_type}<br>
                            <strong>Uploaded by:</strong> ${data.uploader_name}<br>
                            <strong>Date:</strong> ${new Date(data.uploaded_at).toLocaleDateString()}
                        </p>
                    </div>
                </div>
            `;
        });
    
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}

function viewDocument(filePath, filename) {
    const fileExtension = filename.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'pdf'].includes(fileExtension)) {
        window.open('../' + filePath, '_blank');
    } else {
        // Force download for other file types
        const link = document.createElement('a');
        link.href = '../' + filePath;
        link.download = filename;
        link.click();
    }
}

function deleteDocument(documentId) {
    if (confirm('Are you sure you want to delete this document?')) {
        deleteItem(documentId, 'document', 'Delete this document?');
    }
}

function filterByStatus() {
    const filter = document.getElementById('statusFilter').value.toLowerCase();
    const table = document.getElementById('documentsTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const statusCell = rows[i].getElementsByTagName('td')[6];
        const statusText = statusCell.textContent.toLowerCase();
        
        if (filter === '' || statusText.includes(filter)) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
