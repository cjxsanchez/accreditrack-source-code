<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Document Validation';
$db = getDB();

$success = '';
$error = '';

// Handle validation actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $document_id = sanitize($_POST['document_id']);
    $status = sanitize($_POST['status']);
    $comments = sanitize($_POST['comments'] ?? '');
    
    try {
        // Update document status with reviewed_at timestamp
        $query = "UPDATE documents SET status = ?, admin_comment = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$status, $comments, $_SESSION['user_id'], $document_id])) {
            // Get document info for notification
            $query = "SELECT uploaded_by, title FROM documents WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$document_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                // Send notification to faculty
                $notif_title = $status == 'approved' ? 'Document Approved' : 'Document Rejected';
                $notif_message = "Your document '{$doc['title']}' has been {$status}.";
                if ($comments) {
                    $notif_message .= " Comment: {$comments}";
                }
                
                $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $doc['uploaded_by'],
                    $notif_title,
                    $notif_message,
                    $status == 'approved' ? 'approval' : 'rejection'
                ]);
            }
            
            $success = 'Document validation completed successfully!';
        } else {
            $error = 'Failed to validate document.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch ALL documents in one optimized query - Exclude archived programs
$query = "SELECT d.*, 
                 u.full_name as uploaded_by_name, 
                 i.name as indicator_name, 
                 i.indicator_type,
                 p.name as parameter_name, 
                 p.parameter_letter, 
                 a.area_number, 
                 a.name as area_name,
                 l.level_type, 
                 pr.name as program_name, 
                 pr.code as program_code,
                 gat.folder_name,
                 gr.requirement_name
          FROM documents d
          JOIN users u ON d.uploaded_by = u.id
          JOIN indicators i ON d.indicator_id = i.id
          JOIN parameters p ON i.parameter_id = p.id
          JOIN areas a ON p.area_id = a.id
          JOIN levels l ON a.level_id = l.id
          JOIN programs pr ON l.program_id = pr.id
          LEFT JOIN global_accreditation_tags gat ON d.folder_id = gat.id
          LEFT JOIN global_requirements gr ON d.requirement_id = gr.id
          WHERE pr.status != 'archived'  -- Exclude archived programs
          ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate documents into arrays
$pending_docs = [];
$approved_docs = [];
$rejected_docs = [];
$unique_programs = []; // For filter dropdown

foreach ($all_docs as $doc) {
    if ($doc['status'] == 'pending') {
        $pending_docs[] = $doc;
    } elseif ($doc['status'] == 'approved') {
        $approved_docs[] = $doc;
    } elseif ($doc['status'] == 'rejected') {
        $rejected_docs[] = $doc;
    }
    
    // Collect unique programs for filter (excluding archived already filtered in query)
    if (!empty($doc['program_code']) && !in_array($doc['program_code'], $unique_programs)) {
        $unique_programs[] = $doc['program_code'];
    }
}
sort($unique_programs);

$pending_count = count($pending_docs);
$approved_count = count($approved_docs);
$rejected_count = count($rejected_docs);
$total_count = count($all_docs);

include dirname(__DIR__) . '/includes/header.php';
?>

<style>
    :root {
        --primary-color: #667eea;
        --secondary-color: #764ba2;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
    }

    /* Simplified Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .stat-card { 
        background: white; border-radius: 12px; padding: 15px; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.02);
        display: flex; align-items: center; gap: 15px; cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .stat-card.active-filter { border: 2px solid var(--primary-color); background: #f0f4ff; }
    
    .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; }
    .stat-icon.pending { background: var(--warning-color); }
    .stat-icon.approved { background: var(--success-color); }
    .stat-icon.rejected { background: var(--danger-color); }
    .stat-icon.total { background: #64748b; }
    
    .stat-info h3 { font-size: 1.25rem; font-weight: 700; margin: 0; color: #333; }
    .stat-info p { margin: 0; font-size: 0.8rem; color: #666; font-weight: 500; }

    /* Filter Toolbar */
    .filter-toolbar {
        background: white; padding: 15px; border-radius: 12px; margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); display: flex; gap: 15px; flex-wrap: wrap; align-items: center;
    }
    .search-box { flex-grow: 1; position: relative; min-width: 200px; }
    .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #adb5bd; }
    .search-box input { padding-left: 40px; border-radius: 8px; border: 1px solid #dee2e6; }
    .filter-select { width: auto; border-radius: 8px; border: 1px solid #dee2e6; min-width: 150px; }

    /* Navigation Tabs (Hidden visually but used for logic) */
    .nav-tabs { display: none; }

    /* Table Styles */
    .card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); overflow: hidden; background: white; }
    .table { margin: 0; }
    .table thead th { 
        background: #f8fafc; border-bottom: 2px solid #edf2f7; padding: 12px 15px; 
        font-weight: 600; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; 
    }
    .table tbody td { padding: 16px 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table tbody tr:hover { background: #f8fafc; }

    /* Row Specifics */
    .doc-primary { display: flex; flex-direction: column; gap: 4px; }
    .doc-title { font-weight: 600; color: #1e293b; font-size: 0.95rem; display: block; }
    .doc-type-badge { 
        display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; 
        border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: #e0e7ff; color: #4338ca; width: fit-content;
    }

    .location-cell { max-width: 250px; }
    .program-badge { 
        background: #475569; color: white; padding: 2px 6px; border-radius: 4px; 
        font-size: 0.7rem; font-weight: 700; margin-right: 5px; cursor: help;
    }
    .area-text { font-size: 0.85rem; color: #334155; font-weight: 500; }
    .indicator-text { font-size: 0.75rem; color: #94a3b8; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .uploader-meta { display: flex; align-items: center; gap: 10px; }
    .uploader-avatar { width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 0.8rem; font-weight: 600; }
    .meta-text { display: flex; flex-direction: column; line-height: 1.2; }
    .uploader-name { font-size: 0.85rem; font-weight: 600; color: #334155; }
    .upload-date { font-size: 0.75rem; color: #94a3b8; }

    /* Status Badges */
    .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .status-badge.pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
    .status-badge.approved { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
    .status-badge.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

    /* Action Buttons */
    .actions-cell { white-space: nowrap; }
    .btn-action-group { display: flex; justify-content: flex-end; gap: 8px; }
    .btn-custom { 
        padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; 
        display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; transition: all 0.2s;
    }
    .btn-view { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .btn-view:hover { background: #e2e8f0; color: #1e293b; }
    
    .btn-approve { background: var(--success-color); color: white; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2); }
    .btn-approve:hover { background: #059669; color: white; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3); }
    
    .btn-reject { background: white; color: var(--danger-color); border-color: #fca5a5; }
    .btn-reject:hover { background: #fef2f2; border-color: var(--danger-color); }

    .btn-icon-only { padding: 6px 10px; }
    
    @media (max-width: 1200px) {
        .btn-label { display: none; }
    }

    /* Tooltip refinement */
    .tooltip-inner { max-width: 300px; text-align: left; }

    .empty-state { 
        text-align: center; 
        padding: 60px 20px; 
        background: white; 
        border-radius: 12px; 
        box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        margin-top: 20px;
    }
    .empty-state i { 
        font-size: 4rem; 
        margin-bottom: 20px; 
        opacity: 0.3;
    }
    .empty-state.pending i { color: var(--warning-color); }
    .empty-state.approved i { color: var(--success-color); }
    .empty-state.rejected i { color: var(--danger-color); }
    .empty-state.all i { color: #64748b; }
    
    .empty-state h4 { 
        color: #334155; 
        font-weight: 600; 
        margin-bottom: 10px; 
        font-size: 1.5rem;
    }
    .empty-state p { 
        color: #64748b; 
        font-size: 1rem; 
        max-width: 500px; 
        margin: 0 auto 20px;
        line-height: 1.6;
    }
    .empty-state .cta-button {
        margin-top: 15px;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    /* Small text for review date */
    .review-date { font-size: 0.7rem; color: #94a3b8; display: block; margin-top: 2px; }
    
    /* Filtered empty state */
    .filtered-empty-state {
        text-align: center;
        padding: 40px 20px;
        background: #f8fafc;
        border-radius: 8px;
        margin: 20px 0;
        display: none;
    }
    .filtered-empty-state i {
        font-size: 2.5rem;
        color: #cbd5e1;
        margin-bottom: 15px;
    }
    .filtered-empty-state h5 {
        color: #64748b;
        margin-bottom: 10px;
    }
    .filtered-empty-state p {
        color: #94a3b8;
        font-size: 0.9rem;
    }
</style>

<div class="container-fluid">
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <div>
                    <strong>Success!</strong> <?php echo $success; ?>
                    <a href="javascript:void(0)" onclick="switchTab('all')" class="alert-link ms-2">View in All Documents</a>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="stats-grid mt-4">
        <div class="stat-card" onclick="switchTab('pending')" id="card-pending">
            <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3><?php echo $pending_count; ?></h3>
                <p>Pending Review</p>
            </div>
        </div>
        <div class="stat-card" onclick="switchTab('approved')" id="card-approved">
            <div class="stat-icon approved"><i class="fas fa-check"></i></div>
            <div class="stat-info">
                <h3><?php echo $approved_count; ?></h3>
                <p>Approved</p>
            </div>
        </div>
        <div class="stat-card" onclick="switchTab('rejected')" id="card-rejected">
            <div class="stat-icon rejected"><i class="fas fa-times"></i></div>
            <div class="stat-info">
                <h3><?php echo $rejected_count; ?></h3>
                <p>Rejected</p>
            </div>
        </div>
        <div class="stat-card" onclick="switchTab('all')" id="card-all">
            <div class="stat-icon total"><i class="fas fa-folder"></i></div>
            <div class="stat-info">
                <h3><?php echo $total_count; ?></h3>
                <p>All Documents</p>
            </div>
        </div>
    </div>

    <div class="filter-toolbar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search document, uploader, or code..." onkeyup="filterDocuments()">
        </div>
        <select class="form-select filter-select" id="programFilter" onchange="filterDocuments()">
            <option value="">All Programs</option>
            <?php foreach($unique_programs as $prog): ?>
                <option value="<?php echo htmlspecialchars($prog); ?>"><?php echo htmlspecialchars($prog); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" class="form-control filter-select" id="dateFilter" onchange="filterDocuments()" title="Filter by date">
        <button class="btn btn-outline-secondary btn-sm" onclick="resetFilters()" title="Reset Filters"><i class="fas fa-undo"></i></button>
    </div>

    <ul class="nav nav-tabs" id="validationTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">Pending</button></li>
        <li class="nav-item"><button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button">Approved</button></li>
        <li class="nav-item"><button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button">Rejected</button></li>
        <li class="nav-item"><button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">All</button></li>
    </ul>

    <div class="tab-content" id="validationTabsContent">
        
        <div class="tab-pane fade show active" id="pending">
            <?php if (empty($pending_docs)): ?>
                <div class="empty-state pending">
                    <i class="fas fa-check-double"></i>
                    <h4>All Caught Up!</h4>
                    <p>No pending documents require review at the moment. Faculty will appear here when they upload new documents for validation.</p>
                    <p class="text-muted">You can check the <strong>Approved</strong> or <strong>All Documents</strong> tabs to review previously processed documents.</p>
                    <a href="javascript:void(0)" onclick="switchTab('all')" class="btn btn-outline-primary cta-button">
                        <i class="fas fa-folder me-2"></i>View All Documents
                    </a>
                </div>
            <?php else: ?>
                <div class="filtered-empty-state" id="filtered-empty-pending">
                    <i class="fas fa-search"></i>
                    <h5>No Matching Documents</h5>
                    <p>No pending documents match your current filters. Try adjusting your search or filter criteria.</p>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table" id="table-pending">
                            <thead>
                                <tr>
                                    <th width="35%">Document Details</th>
                                    <th width="25%">Context (Location)</th>
                                    <th width="20%">Uploaded By</th>
                                    <th width="10%">Status</th>
                                    <th width="10%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_docs as $doc): 
                                    $location_tooltip = htmlspecialchars($doc['program_name'] . ' - ' . ($doc['level_type'] ?? 'N/A') . "\nArea " . $doc['area_number'] . " (" . $doc['area_name'] . ")");
                                    $ind_name = htmlspecialchars($doc['indicator_name']);
                                    $param_str = "Area " . $doc['area_number'] . " • Param " . ($doc['parameter_letter'] ?? '?');
                                ?>
                                <tr class="doc-row" data-program="<?php echo htmlspecialchars($doc['program_code']); ?>" data-date="<?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>">
                                    <td>
                                        <div class="doc-primary">
                                            <span class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></span>
                                            <div class="d-flex gap-2">
                                                <?php if (!empty($doc['folder_name'])): ?>
                                                    <span class="doc-type-badge"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($doc['requirement_name'])): ?>
                                                    <span class="doc-type-badge" style="background:#fff7ed; color:#c2410c;"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($doc['requirement_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="location-cell">
                                        <div class="mb-1">
                                            <span class="program-badge" data-bs-toggle="tooltip" title="<?php echo $location_tooltip; ?>">
                                                <?php echo htmlspecialchars($doc['program_code'] ?? 'GEN'); ?>
                                            </span>
                                            <span class="area-text"><?php echo $param_str; ?></span>
                                        </div>
                                        <span class="indicator-text" title="<?php echo $ind_name; ?>">
                                            <i class="fas fa-info-circle me-1"></i><?php echo strlen($ind_name) > 40 ? substr($ind_name, 0, 40) . '...' : $ind_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="uploader-meta">
                                            <div class="uploader-avatar"><?php echo strtoupper(substr($doc['uploaded_by_name'], 0, 1)); ?></div>
                                            <div class="meta-text">
                                                <span class="uploader-name"><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                                <span class="upload-date">
                                                    <?php echo date('M d, Y h:i A', strtotime($doc['created_at'])); ?>
                                                    <span class="review-date">Uploaded</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    </td>
                                    <td class="text-end actions-cell">
                                        <div class="btn-action-group">
                                            <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn-custom btn-view btn-icon-only" data-bs-toggle="tooltip" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn-custom btn-approve" onclick="validateDocument(<?php echo $doc['id']; ?>, 'approved')">
                                                <i class="fas fa-check"></i> <span class="btn-label">Approve</span>
                                            </button>
                                            <button class="btn-custom btn-reject btn-icon-only" onclick="validateDocument(<?php echo $doc['id']; ?>, 'rejected')" data-bs-toggle="tooltip" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="approved">
            <?php if (empty($approved_docs)): ?>
                <div class="empty-state approved">
                    <i class="fas fa-folder-open"></i>
                    <h4>No Approved Documents</h4>
                    <p>There are no approved documents in the system yet. Documents will appear here once you approve them from the Pending tab.</p>
                    <p class="text-muted">Go to the <strong>Pending</strong> tab to review and approve documents submitted by faculty members.</p>
                    <a href="javascript:void(0)" onclick="switchTab('pending')" class="btn btn-outline-success cta-button">
                        <i class="fas fa-clock me-2"></i>Review Pending Documents
                    </a>
                </div>
            <?php else: ?>
                <div class="filtered-empty-state" id="filtered-empty-approved">
                    <i class="fas fa-search"></i>
                    <h5>No Matching Documents</h5>
                    <p>No approved documents match your current filters. Try adjusting your search or filter criteria.</p>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table" id="table-approved">
                            <thead>
                                <tr>
                                    <th width="35%">Document Details</th>
                                    <th width="25%">Context (Location)</th>
                                    <th width="20%">Approved By/When</th>
                                    <th width="10%">Status</th>
                                    <th width="10%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_docs as $doc): 
                                    $location_tooltip = htmlspecialchars($doc['program_name'] . ' - ' . ($doc['level_type'] ?? 'N/A') . "\nArea " . $doc['area_number'] . " (" . $doc['area_name'] . ")");
                                    $ind_name = htmlspecialchars($doc['indicator_name']);
                                    $param_str = "Area " . $doc['area_number'] . " • Param " . ($doc['parameter_letter'] ?? '?');
                                ?>
                                <tr class="doc-row" data-program="<?php echo htmlspecialchars($doc['program_code']); ?>" data-date="<?php echo date('Y-m-d', strtotime($doc['reviewed_at'] ?: $doc['created_at'])); ?>">
                                    <td>
                                        <div class="doc-primary">
                                            <span class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></span>
                                            <div class="d-flex gap-2">
                                                <?php if (!empty($doc['folder_name'])): ?>
                                                    <span class="doc-type-badge"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($doc['requirement_name'])): ?>
                                                    <span class="doc-type-badge" style="background:#fff7ed; color:#c2410c;"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($doc['requirement_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="location-cell">
                                        <div class="mb-1">
                                            <span class="program-badge" data-bs-toggle="tooltip" title="<?php echo $location_tooltip; ?>">
                                                <?php echo htmlspecialchars($doc['program_code'] ?? 'GEN'); ?>
                                            </span>
                                            <span class="area-text"><?php echo $param_str; ?></span>
                                        </div>
                                        <span class="indicator-text" title="<?php echo $ind_name; ?>">
                                            <i class="fas fa-info-circle me-1"></i><?php echo strlen($ind_name) > 40 ? substr($ind_name, 0, 40) . '...' : $ind_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="uploader-meta">
                                            <div class="uploader-avatar"><?php echo strtoupper(substr($doc['uploaded_by_name'], 0, 1)); ?></div>
                                            <div class="meta-text">
                                                <span class="uploader-name"><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                                <span class="upload-date">
                                                    <?php echo date('M d, Y h:i A', strtotime($doc['reviewed_at'] ?: $doc['created_at'])); ?>
                                                    <span class="review-date">Approved</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge approved">
                                            <i class="fas fa-check"></i> Approved
                                        </span>
                                    </td>
                                    <td class="text-end actions-cell">
                                        <div class="btn-action-group">
                                            <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn-custom btn-view btn-icon-only" data-bs-toggle="tooltip" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../api/download.php?id=<?php echo $doc['id']; ?>" class="btn-custom btn-view btn-icon-only" data-bs-toggle="tooltip" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="rejected">
            <?php if (empty($rejected_docs)): ?>
                <div class="empty-state rejected">
                    <i class="fas fa-times-circle"></i>
                    <h4>No Rejected Documents</h4>
                    <p>There are no rejected documents in the system. Documents will appear here if you reject them during the review process.</p>
                    <p class="text-muted">All submitted documents are either pending review or have been approved.</p>
                    <div class="mt-3">
                        <a href="javascript:void(0)" onclick="switchTab('pending')" class="btn btn-outline-warning cta-button me-2">
                            <i class="fas fa-clock me-2"></i>Review Pending
                        </a>
                        <a href="javascript:void(0)" onclick="switchTab('approved')" class="btn btn-outline-success cta-button">
                            <i class="fas fa-check me-2"></i>View Approved
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="filtered-empty-state" id="filtered-empty-rejected">
                    <i class="fas fa-search"></i>
                    <h5>No Matching Documents</h5>
                    <p>No rejected documents match your current filters. Try adjusting your search or filter criteria.</p>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table" id="table-rejected">
                            <thead>
                                <tr>
                                    <th width="35%">Document Details</th>
                                    <th width="25%">Context (Location)</th>
                                    <th width="20%">Rejected By/When</th>
                                    <th width="10%">Status</th>
                                    <th width="10%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_docs as $doc): 
                                    $location_tooltip = htmlspecialchars($doc['program_name'] . ' - ' . ($doc['level_type'] ?? 'N/A') . "\nArea " . $doc['area_number'] . " (" . $doc['area_name'] . ")");
                                    $ind_name = htmlspecialchars($doc['indicator_name']);
                                    $param_str = "Area " . $doc['area_number'] . " • Param " . ($doc['parameter_letter'] ?? '?');
                                ?>
                                <tr class="doc-row" data-program="<?php echo htmlspecialchars($doc['program_code']); ?>" data-date="<?php echo date('Y-m-d', strtotime($doc['reviewed_at'] ?: $doc['created_at'])); ?>">
                                    <td>
                                        <div class="doc-primary">
                                            <span class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></span>
                                            <div class="d-flex gap-2">
                                                <?php if (!empty($doc['folder_name'])): ?>
                                                    <span class="doc-type-badge"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($doc['requirement_name'])): ?>
                                                    <span class="doc-type-badge" style="background:#fff7ed; color:#c2410c;"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($doc['requirement_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="location-cell">
                                        <div class="mb-1">
                                            <span class="program-badge" data-bs-toggle="tooltip" title="<?php echo $location_tooltip; ?>">
                                                <?php echo htmlspecialchars($doc['program_code'] ?? 'GEN'); ?>
                                            </span>
                                            <span class="area-text"><?php echo $param_str; ?></span>
                                        </div>
                                        <span class="indicator-text" title="<?php echo $ind_name; ?>">
                                            <i class="fas fa-info-circle me-1"></i><?php echo strlen($ind_name) > 40 ? substr($ind_name, 0, 40) . '...' : $ind_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="uploader-meta">
                                            <div class="uploader-avatar"><?php echo strtoupper(substr($doc['uploaded_by_name'], 0, 1)); ?></div>
                                            <div class="meta-text">
                                                <span class="uploader-name"><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                                <span class="upload-date">
                                                    <?php echo date('M d, Y h:i A', strtotime($doc['reviewed_at'] ?: $doc['created_at'])); ?>
                                                    <span class="review-date">Rejected</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge rejected">
                                            <i class="fas fa-times"></i> Rejected
                                        </span>
                                    </td>
                                    <td class="text-end actions-cell">
                                        <div class="btn-action-group">
                                            <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn-custom btn-view btn-icon-only" data-bs-toggle="tooltip" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn-custom btn-reject btn-icon-only" onclick="deleteDocument(<?php echo $doc['id']; ?>)" data-bs-toggle="tooltip" title="Delete">
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
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="all">
            <?php if (empty($all_docs)): ?>
                <div class="empty-state all">
                    <i class="fas fa-inbox"></i>
                    <h4>No Documents Found</h4>
                    <p>The system doesn't contain any documents yet. Faculty members need to upload documents for accreditation compliance.</p>
                    <p class="text-muted">Once faculty upload documents, they will appear here for your review and validation.</p>
                    <div class="mt-4">
                        <p class="text-muted mb-2">You might want to:</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check-circle text-success me-2"></i>Ensure faculty are aware of the document upload process</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Check that programs are properly configured in the system</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Verify faculty have the correct permissions to upload documents</li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <div class="filtered-empty-state" id="filtered-empty-all">
                    <i class="fas fa-search"></i>
                    <h5>No Matching Documents</h5>
                    <p>No documents match your current filters. Try adjusting your search or filter criteria.</p>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table" id="table-all">
                            <thead>
                                <tr>
                                    <th width="35%">Document Details</th>
                                    <th width="25%">Context (Location)</th>
                                    <th width="20%">Uploaded/Reviewed</th>
                                    <th width="10%">Status</th>
                                    <th width="10%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_docs as $doc): 
                                    $location_tooltip = htmlspecialchars($doc['program_name'] . ' - ' . ($doc['level_type'] ?? 'N/A') . "\nArea " . $doc['area_number'] . " (" . $doc['area_name'] . ")");
                                    $ind_name = htmlspecialchars($doc['indicator_name']);
                                    $param_str = "Area " . $doc['area_number'] . " • Param " . ($doc['parameter_letter'] ?? '?');
                                    
                                    // Determine which date to show
                                    $show_date = $doc['created_at'];
                                    $date_label = "Uploaded";
                                    
                                    if ($doc['status'] == 'approved' || $doc['status'] == 'rejected') {
                                        if (!empty($doc['reviewed_at'])) {
                                            $show_date = $doc['reviewed_at'];
                                            $date_label = $doc['status'] == 'approved' ? 'Approved' : 'Rejected';
                                        }
                                    }
                                ?>
                                <tr class="doc-row" data-program="<?php echo htmlspecialchars($doc['program_code']); ?>" data-date="<?php echo date('Y-m-d', strtotime($show_date)); ?>">
                                    <td>
                                        <div class="doc-primary">
                                            <span class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></span>
                                            <div class="d-flex gap-2">
                                                <?php if (!empty($doc['folder_name'])): ?>
                                                    <span class="doc-type-badge"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($doc['requirement_name'])): ?>
                                                    <span class="doc-type-badge" style="background:#fff7ed; color:#c2410c;"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($doc['requirement_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="location-cell">
                                        <div class="mb-1">
                                            <span class="program-badge" data-bs-toggle="tooltip" title="<?php echo $location_tooltip; ?>">
                                                <?php echo htmlspecialchars($doc['program_code'] ?? 'GEN'); ?>
                                            </span>
                                            <span class="area-text"><?php echo $param_str; ?></span>
                                        </div>
                                        <span class="indicator-text" title="<?php echo $ind_name; ?>">
                                            <i class="fas fa-info-circle me-1"></i><?php echo strlen($ind_name) > 40 ? substr($ind_name, 0, 40) . '...' : $ind_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="uploader-meta">
                                            <div class="uploader-avatar"><?php echo strtoupper(substr($doc['uploaded_by_name'], 0, 1)); ?></div>
                                            <div class="meta-text">
                                                <span class="uploader-name"><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                                <span class="upload-date">
                                                    <?php echo date('M d, Y h:i A', strtotime($show_date)); ?>
                                                    <span class="review-date"><?php echo $date_label; ?></span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $doc['status']; ?>">
                                            <?php if($doc['status'] == 'approved') echo '<i class="fas fa-check"></i>'; ?>
                                            <?php if($doc['status'] == 'rejected') echo '<i class="fas fa-times"></i>'; ?>
                                            <?php if($doc['status'] == 'pending') echo '<i class="fas fa-clock"></i>'; ?>
                                            <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end actions-cell">
                                        <div class="btn-action-group">
                                            <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn-custom btn-view btn-icon-only" data-bs-toggle="tooltip" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($doc['status'] == 'pending'): ?>
                                                <button class="btn-custom btn-approve" onclick="validateDocument(<?php echo $doc['id']; ?>, 'approved')">
                                                    <i class="fas fa-check"></i> <span class="btn-label">Approve</span>
                                                </button>
                                                <button class="btn-custom btn-reject btn-icon-only" onclick="validateDocument(<?php echo $doc['id']; ?>, 'rejected')" data-bs-toggle="tooltip" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php elseif ($doc['status'] == 'approved'): ?>
                                                <a href="../api/download.php?id=<?php echo $doc['id']; ?>" class="btn-custom btn-view btn-icon-only" data-bs-toggle="tooltip" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php elseif ($doc['status'] == 'rejected'): ?>
                                                <button class="btn-custom btn-reject btn-icon-only" onclick="deleteDocument(<?php echo $doc['id']; ?>)" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="validationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="validationModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="validate">
                    <input type="hidden" name="document_id" id="modal_document_id">
                    <input type="hidden" name="status" id="modal_status">
                    
                    <div class="d-flex align-items-center mb-4 text-muted">
                        <i id="modal_icon" class="fas fa-info-circle fa-2x me-3"></i>
                        <p id="modal_message" class="mb-0 fs-6"></p>
                    </div>
                    
                    <div class="mb-3">
                        <span class="suggestion-label text-xs fw-bold text-uppercase text-muted mb-2 d-block">Quick Feedback (Click to add):</span>
                        <div id="suggestion_chips" class="d-flex flex-wrap gap-2"></div>
                    </div>

                    <div class="mb-3">
                        <label for="comments" class="form-label fw-semibold">Admin Comments</label>
                        <textarea name="comments" id="comments" class="form-control" rows="3" placeholder="Provide feedback to the faculty..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="modal_submit_btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Tab Switching Logic including Filters
    function switchTab(tabName) {
        // Update Card Visuals
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
        document.getElementById('card-' + tabName).classList.add('active-filter');
        
        // Activate Bootstrap Tab
        var triggerEl = document.querySelector('#' + tabName + '-tab');
        var tab = new bootstrap.Tab(triggerEl);
        tab.show();

        // Re-apply filters for the new tab context
        setTimeout(filterDocuments, 50);
    }

    // Initialize default tab
    switchTab('pending');

    // Client-side Filtering
    function filterDocuments() {
        const searchText = document.getElementById('tableSearch').value.toLowerCase();
        const programFilter = document.getElementById('programFilter').value;
        const dateFilter = document.getElementById('dateFilter').value;

        // Find the active table
        const activeTabPane = document.querySelector('.tab-pane.active');
        if(!activeTabPane) return;
        
        const rows = activeTabPane.querySelectorAll('tbody tr');
        const emptyState = activeTabPane.querySelector('.filtered-empty-state');
        const tableContainer = activeTabPane.querySelector('.card');
        
        let visibleRows = 0;
        
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const prog = row.getAttribute('data-program');
            const date = row.getAttribute('data-date');
            
            let matchesSearch = text.includes(searchText);
            let matchesProgram = programFilter === "" || prog === programFilter;
            let matchesDate = dateFilter === "" || date === dateFilter;

            if (matchesSearch && matchesProgram && matchesDate) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide filtered empty state
        if (emptyState && tableContainer) {
            if (visibleRows === 0 && (searchText || programFilter || dateFilter)) {
                emptyState.style.display = 'block';
                tableContainer.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                tableContainer.style.display = 'block';
            }
        }
    }

    function resetFilters() {
        document.getElementById('tableSearch').value = '';
        document.getElementById('programFilter').value = '';
        document.getElementById('dateFilter').value = '';
        filterDocuments();
    }

    const approveSuggestions = ["Approved.", "Good work.", "Compliant with requirements.", "Verified.", "Thank You."];
    const rejectSuggestions = ["Incorrect file format.", "Incomplete data.", "Signature missing.", "Unreadable file.", "Please check the area parameter."];

    function validateDocument(docId, status) {
        document.getElementById('modal_document_id').value = docId;
        document.getElementById('modal_status').value = status;
        document.getElementById('comments').value = '';
        
        const chipsContainer = document.getElementById('suggestion_chips');
        chipsContainer.innerHTML = '';
        
        const btn = document.getElementById('modal_submit_btn');
        const icon = document.getElementById('modal_icon');
        
        let suggestions = [];
        
        if (status === 'approved') {
            document.getElementById('validationModalTitle').textContent = 'Approve Document';
            document.getElementById('modal_message').textContent = 'You are about to mark this document as valid. This action notifies the faculty member.';
            btn.className = 'btn btn-success text-white';
            btn.textContent = 'Approve Document';
            icon.className = 'fas fa-check-circle fa-2x me-3 text-success';
            suggestions = approveSuggestions;
        } else {
            document.getElementById('validationModalTitle').textContent = 'Reject Document';
            document.getElementById('modal_message').textContent = 'You are about to reject this document. Please provide a reason below.';
            btn.className = 'btn btn-danger text-white';
            btn.textContent = 'Reject Document';
            icon.className = 'fas fa-exclamation-triangle fa-2x me-3 text-danger';
            suggestions = rejectSuggestions;
        }
        
        suggestions.forEach(text => {
            const chip = document.createElement('span');
            chip.className = 'badge bg-secondary rounded-pill me-1';
            chip.style.cursor = 'pointer';
            chip.style.fontWeight = '500';
            chip.textContent = text;
            chip.onclick = function() {
                const commentBox = document.getElementById('comments');
                commentBox.value = commentBox.value ? commentBox.value + ' ' + text : text;
            };
            chipsContainer.appendChild(chip);
        });
        
        new bootstrap.Modal(document.getElementById('validationModal')).show();
    }

    function deleteDocument(docId) {
        if (confirm('Are you sure you want to delete this document? This cannot be undone.')) {
            fetch('../api/delete_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document_id: docId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 200 || data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete'));
                }
            });
        }
    }
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>