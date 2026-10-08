<?php
/**
 * Staff Archives - View and manage archived documents
 */
require_once '../config/config.php';
requireStaff();

// Keeping the page title for the browser tab/main header
$page_title = 'Archives';

$database = new Database();
$db = $database->getConnection();

// Get archived documents for the current user
$query = "SELECT ad.*, 
          gat.folder_name,
          gr.requirement_name, gr.requirement_code,
          i.indicator_type,
          p.name as parameter_name,
          a.name as area_name,
          prg.name as program_name,
          l.level_type
          FROM archived_documents ad
          LEFT JOIN global_accreditation_tags gat ON ad.folder_id = gat.id
          LEFT JOIN global_requirements gr ON ad.requirement_id = gr.id
          LEFT JOIN indicators i ON ad.indicator_id = i.id
          LEFT JOIN parameters p ON i.parameter_id = p.id
          LEFT JOIN areas a ON p.area_id = a.id
          LEFT JOIN levels l ON a.level_id = l.id
          LEFT JOIN programs prg ON l.program_id = prg.id
          WHERE ad.archived_by = ?
          ORDER BY ad.archived_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$archived_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Library Theme Variables */
    :root {
        --bg-canvas: #f8fafc;
        --card-bg: #ffffff;
        --border-color: #e2e8f0;
        --text-primary: #334155;
        --text-secondary: #64748b;
        --text-muted: #94a3b8;
        --accent-color: #475569;
        --danger-muted: #ef4444;
    }

    body {
        background-color: var(--bg-canvas);
    }

    /* Header Styling */
    .page-header {
        padding: 1.5rem 0 2rem 0;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 2rem;
    }

    .page-title {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        font-weight: 600;
        color: var(--text-primary);
        letter-spacing: -0.02em;
        font-size: 1.75rem;
        margin: 0;
    }

    .btn-back {
        color: var(--text-secondary);
        background: transparent;
        border: 1px solid var(--border-color);
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        border-radius: 6px;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-back:hover {
        background: white;
        color: var(--text-primary);
        border-color: #cbd5e1;
    }

    /* Archive Card - "Library Index Card" Style */
    .archive-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1.5rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: border-color 0.2s ease;
    }

    .archive-card:hover {
        border-color: #94a3b8;
    }

    /* Status Indicators */
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }

    .status-approved { background-color: #10b981; }
    .status-pending { background-color: #f59e0b; }
    .status-rejected { background-color: #ef4444; }

    .meta-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }

    .doc-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    /* Contextual Breadcrumbs inside card */
    .context-path {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 1.25rem;
        line-height: 1.5;
    }
    
    .context-path i {
        color: var(--text-muted);
        width: 16px;
        text-align: center;
    }

    /* Tags */
    .tag-container {
        margin-bottom: 1.5rem;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .archive-tag {
        font-size: 0.75rem;
        background: #f1f5f9;
        color: var(--text-secondary);
        padding: 4px 10px;
        border-radius: 4px;
    }

    /* Action Area */
    .card-footer-actions {
        margin-top: auto;
        padding-top: 1.25rem;
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-restore {
        font-size: 0.85rem;
        color: var(--text-primary);
        font-weight: 500;
        padding: 0.4rem 0.8rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: white;
        transition: all 0.2s;
        cursor: pointer;
    }

    .btn-restore:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .btn-delete-text {
        font-size: 0.85rem;
        color: var(--text-muted);
        text-decoration: none;
        transition: color 0.2s;
        border: none;
        background: none;
        padding: 0;
        cursor: pointer;
    }

    .btn-delete-text:hover {
        color: var(--danger-muted);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 5rem 1rem;
        color: var(--text-muted);
    }
    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center page-header">
        <div>
            <h2 class="page-title">Archived Documents</h2>
            <small class="text-muted">Repository of files removed from active areas</small>
        </div>
        <a href="assigned_areas.php" class="btn-back">
            <i class="fas fa-chevron-left me-1"></i> Return to Areas
        </a>
    </div>
    
    <?php if (empty($archived_documents)): ?>
        <div class="empty-state">
            <i class="far fa-folder-open"></i>
            <h5>The archives are empty</h5>
            <p class="small">Documents you remove from your active areas will be stored here.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($archived_documents as $doc): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="archive-card">
                        <div class="meta-top">
                            <span title="Date Archived">
                                <?php echo date('M d, Y', strtotime($doc['archived_at'])); ?>
                            </span>
                            <span class="d-flex align-items-center" title="Status: <?php echo ucfirst($doc['status']); ?>">
                                <span class="status-dot status-<?php echo $doc['status']; ?>"></span>
                                <?php echo ucfirst($doc['status']); ?>
                            </span>
                        </div>

                        <h5 class="doc-title">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </h5>

                        <div class="context-path">
                            <div class="text-truncate">
                                <i class="fas fa-layer-group me-2"></i>
                                <?php echo htmlspecialchars($doc['program_name'] ?? ''); ?> 
                                <span class="text-muted mx-1">/</span>
                                <?php echo htmlspecialchars($doc['area_name'] ?? 'General'); ?>
                            </div>
                            <div class="text-truncate mt-1">
                                <i class="fas fa-list-ul me-2"></i>
                                <?php echo htmlspecialchars($doc['parameter_name'] ?? 'No Parameter'); ?>
                            </div>
                        </div>

                        <div class="tag-container">
                            <?php if (!empty($doc['requirement_code'])): ?>
                                <span class="archive-tag">
                                    <?php echo htmlspecialchars($doc['requirement_code']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($doc['folder_name'])): ?>
                                <span class="archive-tag">
                                    <i class="far fa-folder me-1"></i><?php echo htmlspecialchars($doc['folder_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer-actions">
                            <button class="btn-restore" onclick="restoreDocument(<?php echo $doc['id']; ?>)">
                                <i class="fas fa-undo-alt me-2 text-muted"></i>Restore
                            </button>
                            
                            <button class="btn-delete-text" onclick="permanentlyDelete(<?php echo $doc['id']; ?>)">
                                Delete permanently
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function restoreDocument(archiveId) {
    Swal.fire({
        title: 'Restore Document?',
        text: "This will move the document back to your active workspace.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981', // Emerald Green matches Approved status
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, restore it',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Restoring...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('../api/restore_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ archive_id: archiveId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Restored!',
                        text: 'The document has been restored successfully.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error',
                        'Unable to restore: ' + data.message,
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'System Error',
                    'An unexpected error occurred.',
                    'error'
                );
            });
        }
    });
}

function permanentlyDelete(archiveId) {
    Swal.fire({
        title: 'Delete Permanently?',
        text: "You won't be able to recover this document!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444', // Red for danger
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('../api/delete_archived_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ archive_id: archiveId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Your file has been permanently deleted.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error',
                        'Unable to delete: ' + data.message,
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'System Error',
                    'An unexpected error occurred.',
                    'error'
                );
            });
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>