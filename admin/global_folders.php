<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Accreditation Folders & Requirements';
$db = getDB();

// Get all areas with proper roman numeral mapping
$areas = [
    1 => 'Area I - Vision, Mission, Goals and Objectives',
    2 => 'Area II - Faculty',
    3 => 'Area III - Curriculum and Instruction',
    4 => 'Area IV - Support to Students',
    5 => 'Area V - Research',
    6 => 'Area VI - Extension and Community Involvement',
    7 => 'Area VII - Library',
    8 => 'Area VIII - Physical Plant and Facilities',
    9 => 'Area IX - Laboratories',
    10 => 'Area X - Administration'
];

// Get parameters for each area from config
$areaStructure = getAreaStructure();

$selected_area = isset($_GET['area']) && $_GET['area'] !== '' ? (int)$_GET['area'] : null;
$selected_param = isset($_GET['param']) && $_GET['param'] !== '' ? $_GET['param'] : null;
$selected_indicator = isset($_GET['indicator']) && $_GET['indicator'] !== '' ? $_GET['indicator'] : null;

$folders = [];

if ($selected_area && $selected_param && $selected_indicator) {
    // Get folders from global_accreditation_tags table
    $query = "SELECT * FROM global_accreditation_tags 
              WHERE area_number = ? AND parameter_letter = ? AND indicator_type = ? AND is_active = TRUE
              ORDER BY order_index ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_area, $selected_param, $selected_indicator]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get parameters for selected area
$parameters = [];
if ($selected_area) {
    $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
    $areaRoman = $romanNumerals[$selected_area - 1];
    if (isset($areaStructure[$areaRoman]['parameters'])) {
        $parameters = $areaStructure[$areaRoman]['parameters'];
    }
}

include '../includes/header.php';
?>

<style>
    :root {
        --primary: #4f46e5;       /* Indigo 600 */
        --primary-hover: #4338ca; /* Indigo 700 */
        --primary-light: #eef2ff; /* Indigo 50 */
        --text-main: #111827;     /* Gray 900 */
        --text-secondary: #6b7280;/* Gray 500 */
        --border-color: #e5e7eb;  /* Gray 200 */
        --bg-surface: #ffffff;
        --bg-body: #f9fafb;       /* Gray 50 */
        --danger: #ef4444;
        --warning: #f59e0b;
        --folder-color: #FFC107;  /* Amber color matching the screenshot */
    }

    body {
        background-color: var(--bg-body);
        color: var(--text-main);
    }

    /* Page Header - Clean & Minimal */
    .page-header {
        background: transparent;
        padding: 0 0 24px 0;
        margin-bottom: 0;
        border: none;
        box-shadow: none;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.025em;
        margin-bottom: 4px;
    }
    
    .page-header p {
        color: var(--text-secondary);
        font-size: 14px;
    }

    /* Filter Toolbar - Floating Bar Style */
    .selection-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 32px;
    }

    .selection-card .card-body {
        padding: 20px;
    }

    .filter-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        font-weight: 600;
        margin-bottom: 6px;
        display: block;
    }

    .form-select-modern {
        border: 1px solid var(--border-color);
        background-color: #fff;
        font-size: 14px;
        color: var(--text-main);
        padding: 10px 12px;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        cursor: pointer;
    }

    .form-select-modern:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light);
        outline: none;
    }

    /* Main Content Area */
    .folders-container {
        background: transparent;
        box-shadow: none;
        border-radius: 0;
    }

    .folders-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        background: transparent;
        padding: 0;
        color: var(--text-main);
    }

    .context-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .badge-soft {
        background-color: var(--primary-light);
        color: var(--primary);
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 99px;
        font-weight: 600;
    }

    /* Refined Accordion */
    .folder-accordion .accordion-item {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 8px !important;
        margin-bottom: 12px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }

    .folder-accordion .accordion-button {
        background: var(--bg-surface);
        padding: 16px 20px;
        border: none;
        box-shadow: none !important;
        color: var(--text-main);
    }

    .folder-accordion .accordion-button:not(.collapsed) {
        background-color: #fafafa;
        border-bottom: 1px solid var(--border-color);
    }

    /* Vertical accent line for active state instead of full background */
    .folder-accordion .accordion-button:not(.collapsed)::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background-color: var(--primary);
    }

    /* UPDATED FOLDER ICON STYLE */
    .folder-icon {
        width: 36px;
        height: 36px;
        background: transparent; /* Removed gray background */
        color: var(--folder-color); /* Set to Amber/Yellow */
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px; /* Increased size for filled icon */
        transition: all 0.2s;
    }

    /* Keep it yellow even when expanded */
    .folder-accordion .accordion-button:not(.collapsed) .folder-icon {
        background: transparent;
        color: var(--folder-color);
    }

    .folder-meta {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .folder-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
        margin: 0;
        line-height: 1.2;
    }

    .system-code {
        font-family: 'SF Mono', 'Roboto Mono', Menlo, monospace;
        font-size: 11px;
        color: var(--text-secondary);
        background: #f3f4f6;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 4px;
        width: fit-content;
    }

    .accordion-body {
        padding: 24px;
        background-color: #fff;
    }

    /* Requirement List */
    .requirement-list {
        list-style: none;
        padding: 0;
        margin: 0 0 16px 0;
    }

    .requirement-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s;
    }
    
    .requirement-item:last-child {
        border-bottom: none;
    }

    .requirement-item:hover {
        background-color: #fafafa;
        margin-left: -16px;
        margin-right: -16px;
        padding-left: 16px;
        padding-right: 16px;
    }

    .req-icon-small {
        color: var(--text-secondary);
        margin-top: 3px;
        opacity: 0.5;
    }

    .requirement-content {
        flex: 1;
        margin-left: 12px;
        margin-right: 16px;
    }

    .req-name {
        font-size: 14px;
        color: var(--text-main);
        font-weight: 500;
        margin: 0;
        line-height: 1.4;
    }

    .req-desc {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 2px;
        display: block;
        line-height: 1.4;
    }

    /* Actions */
    .action-group {
        display: flex;
        gap: 4px;
        opacity: 0.6;
        transition: opacity 0.2s;
    }

    .requirement-item:hover .action-group,
    .folder-accordion .accordion-item:hover .action-group {
        opacity: 1;
    }

    .btn-icon-subtle {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        background: transparent;
        border-radius: 6px;
        color: var(--text-secondary);
        transition: all 0.2s;
    }

    .btn-icon-subtle:hover {
        background: #f3f4f6;
        color: var(--text-main);
    }

    .btn-icon-subtle.delete:hover {
        background: #fef2f2;
        color: var(--danger);
    }

    /* Buttons */
    .btn-primary-soft {
        background-color: var(--primary);
        color: white;
        border: none;
        padding: 8px 16px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);
    }

    .btn-primary-soft:hover {
        background-color: var(--primary-hover);
        color: white;
    }

    .add-requirement-btn {
        width: 100%;
        padding: 12px;
        border: 1px dashed var(--border-color);
        background: #fafafa;
        border-radius: 8px;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .add-requirement-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 48px 24px;
        background: white;
        border-radius: 12px;
        border: 1px dashed var(--border-color);
    }
    
    .empty-state-icon {
        font-size: 32px;
        color: #d1d5db;
        margin-bottom: 16px;
    }

    /* Modal Tweaks */
    .modal-content {
        border: none;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-radius: 12px;
    }
    
    .modal-header {
        border-bottom: 1px solid var(--border-color);
        background: white !important; /* Override gradients */
        color: var(--text-main) !important;
        padding: 16px 24px;
    }

    .modal-title {
        font-weight: 600;
        font-size: 18px;
        display: flex;
        align-items: center;
    }

    .modal-footer {
        border-top: 1px solid var(--border-color);
        background: #f9fafb;
    }
    
    .folder-desc-text {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }
    
    /* Utility for destructive actions in header */
    .folder-header-actions {
        display: flex;
        gap: 8px;
        position: relative;
        z-index: 5; /* Ensure clicks don't toggle accordion */
    }
</style>

<main class="content-area">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Accreditation Folders</h1>
            <p>Manage requirements and folder structures applied to all academic programs.</p>
        </div>

        <div class="selection-card">
            <div class="card-body">
                <form id="selectionForm" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="filter-label">Area</label>
                            <select id="selectArea" name="area" class="form-select-modern w-100" onchange="updateParameters()">
                                <option value="">Select Area...</option>
                                <?php foreach ($areas as $area_num => $area_name): ?>
                                    <option value="<?php echo $area_num; ?>" <?php echo $selected_area === $area_num ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="filter-label">Parameter</label>
                            <select id="selectParam" name="param" class="form-select-modern w-100" onchange="loadIndicators()">
                                <option value="">Select Parameter...</option>
                                <?php if ($selected_area && !empty($parameters)): ?>
                                    <?php foreach ($parameters as $letter => $name): ?>
                                        <option value="<?php echo $letter; ?>" <?php echo $selected_param === $letter ? 'selected' : ''; ?>>
                                            <?php echo $letter; ?> - <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="filter-label">Indicator Type</label>
                            <select id="selectIndicator" name="indicator" class="form-select-modern w-100" onchange="loadFolders()">
                                <option value="">Select Type...</option>
                                <option value="S" <?php echo $selected_indicator === 'S' ? 'selected' : ''; ?>>System - Inputs & Processes</option>
                                <option value="I" <?php echo $selected_indicator === 'I' ? 'selected' : ''; ?>>Implementation</option>
                                <option value="O" <?php echo $selected_indicator === 'O' ? 'selected' : ''; ?>>Outcome/s</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_area && $selected_param && $selected_indicator): ?>
        
        <div class="folders-container">
            <div class="folders-header">
                <div class="context-title">
                    <?php 
                    $indicatorNames = ['S' => 'System - Inputs & Processes', 'I' => 'Implementation', 'O' => 'Outcome/s'];
                    echo "Parameter {$selected_param} <span class='text-muted mx-2'>/</span> " . ($indicatorNames[$selected_indicator] ?? '');
                    ?>
                    <span class="badge-soft"><?php echo count($folders); ?> Folders</span>
                </div>
                <button type="button" class="btn btn-primary-soft" onclick="openAddFolderModal()">
                    <i class="fas fa-plus me-2"></i>New Folder
                </button>
            </div>

            <div class="folders-body">
                <?php if (empty($folders)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="far fa-folder-open"></i>
                        </div>
                        <h6 class="text-dark mb-1">No Folders Defined</h6>
                        <p class="text-muted small mb-3">Create a folder to begin organizing requirements for this parameter.</p>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openAddFolderModal()">
                            Create First Folder
                        </button>
                    </div>
                <?php else: ?>
                    <div class="accordion folder-accordion" id="foldersAccordion">
                        <?php foreach ($folders as $index => $folder): ?>
                            <div class="accordion-item" id="folder-item-<?php echo $folder['id']; ?>">
                                <h2 class="accordion-header" id="heading-<?php echo $folder['id']; ?>">
                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $folder['id']; ?>" 
                                            aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                        <div class="d-flex align-items-center gap-3 w-100 me-3">
                                            <div class="folder-icon">
                                                <i class="fas fa-folder"></i>
                                            </div>
                                            <div class="folder-meta flex-grow-1 text-start">
                                                <p class="folder-name"><?php echo htmlspecialchars($folder['folder_name']); ?></p>
                                                <code class="system-code"><?php echo htmlspecialchars($folder['folder_code']); ?></code>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $folder['id']; ?>" 
                                     class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                     aria-labelledby="heading-<?php echo $folder['id']; ?>" 
                                     data-bs-parent="#foldersAccordion">
                                    <div class="accordion-body">
                                        
                                        <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-3">
                                            <div class="text-muted small pe-4">
                                                <?php echo !empty($folder['folder_description']) ? htmlspecialchars($folder['folder_description']) : 'No description provided.'; ?>
                                            </div>
                                            <div class="action-group">
                                                <button type="button" class="btn-icon-subtle" onclick="editFolder(<?php echo $folder['id']; ?>)" title="Edit Folder Details">
                                                    <i class="fas fa-pen" style="font-size: 12px;"></i>
                                                </button>
                                                <button type="button" class="btn-icon-subtle delete" onclick="deleteFolder(<?php echo $folder['id']; ?>)" title="Delete Folder">
                                                    <i class="fas fa-trash" style="font-size: 12px;"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div id="requirements-container-<?php echo $folder['id']; ?>">
                                            <div class="text-center py-3">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            </div>
                                        </div>
                                        
                                        <button type="button" class="add-requirement-btn" 
                                                onclick="openAddRequirementModal(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars(addslashes($folder['folder_name'])); ?>')">
                                            <i class="fas fa-plus"></i> Add Requirement
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state mt-4">
            <div class="empty-state-icon">
                <i class="fas fa-filter"></i>
            </div>
            <h6>Ready to Configure</h6>
            <p class="text-muted small">Select an Area, Parameter, and Indicator Type above to manage folders.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal fade" id="folderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="folderModalTitle">Folder Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="folderForm">
                <div class="modal-body">
                    <input type="hidden" id="folderIdInput" name="folder_id">
                    
                    <div class="mb-3">
                        <label for="folderName" class="form-label small fw-bold text-uppercase text-muted">Folder Name</label>
                        <input type="text" class="form-control" id="folderName" name="folder_name" required placeholder="e.g., Vision and Mission Documents">
                    </div>
                    
                    <div class="mb-3">
                        <label for="folderCode" class="form-label small fw-bold text-uppercase text-muted">System Code</label>
                        <input type="text" class="form-control font-monospace" id="folderCode" name="folder_code" required placeholder="e.g., VMG-01">
                        <div class="form-text">Unique identifier used for sorting.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="folderDescription" class="form-label small fw-bold text-uppercase text-muted">Description</label>
                        <textarea class="form-control" id="folderDescription" name="folder_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-soft">Save Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="requirementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requirementModalTitle">Requirement Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="requirementForm">
                <div class="modal-body">
                    <input type="hidden" id="requirementIdInput" name="requirement_id">
                    <input type="hidden" id="requirementFolderId" name="global_folder_id">
                    
                    <div class="alert alert-light border small text-muted mb-4" id="folderNameDisplay">
                        Adding to: <strong class="text-dark"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requirementName" class="form-label small fw-bold text-uppercase text-muted">Requirement Name</label>
                        <input type="text" class="form-control" id="requirementName" name="requirement_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requirementCode" class="form-label small fw-bold text-uppercase text-muted">System Code</label>
                        <input type="text" class="form-control font-monospace" id="requirementCode" name="requirement_code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requirementDescription" class="form-label small fw-bold text-uppercase text-muted">Description</label>
                        <textarea class="form-control" id="requirementDescription" name="requirement_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-soft">Save Requirement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
const areaStructure = <?php echo json_encode($areaStructure); ?>;
const romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

function updateParameters() {
    const areaSelect = document.getElementById('selectArea');
    const paramSelect = document.getElementById('selectParam');
    const indicatorSelect = document.getElementById('selectIndicator');
    
    const areaNum = parseInt(areaSelect.value);
    paramSelect.innerHTML = '<option value="">Select Parameter...</option>';
    indicatorSelect.value = '';
    
    if (areaNum && areaNum >= 1 && areaNum <= 10) {
        const areaRoman = romanNumerals[areaNum - 1];
        const params = areaStructure[areaRoman]?.parameters || {};
        
        for (const [letter, name] of Object.entries(params)) {
            const option = document.createElement('option');
            option.value = letter;
            option.textContent = `${letter} - ${name}`;
            paramSelect.appendChild(option);
        }
    }
}

function loadIndicators() {
    // Indicators are static, no need to load
}

function loadFolders() {
    const area = document.getElementById('selectArea').value;
    const param = document.getElementById('selectParam').value;
    const indicator = document.getElementById('selectIndicator').value;
    
    if (area && param && indicator) {
        window.location.href = `?area=${area}&param=${param}&indicator=${indicator}`;
    }
}

function openAddFolderModal() {
    document.getElementById('folderIdInput').value = '';
    document.getElementById('folderForm').reset();
    document.getElementById('folderModalTitle').innerText = 'New Folder';
    new bootstrap.Modal(document.getElementById('folderModal')).show();
}

function editFolder(folderId) {
    // Added timestamp to prevent caching
    fetch(`../api/folder_requirements_manager.php?action=get_global_folder&folder_id=${folderId}&t=${new Date().getTime()}`)
        .then(r => r.json())
        .then(data => {
            if (data.data) {
                const folder = data.data;
                document.getElementById('folderIdInput').value = folder.id;
                document.getElementById('folderName').value = folder.folder_name;
                document.getElementById('folderCode').value = folder.folder_code;
                document.getElementById('folderDescription').value = folder.folder_description || '';
                document.getElementById('folderModalTitle').innerText = 'Edit Folder';
                new bootstrap.Modal(document.getElementById('folderModal')).show();
            } else {
                alert('Failed to load folder: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to load folder');
        });
}

function deleteFolder(folderId) {
    if (confirm('Delete this folder? This will remove all contained requirements from all programs.')) {
        const formData = new FormData();
        formData.append('action', 'delete_global_folder');
        formData.append('folder_id', folderId);
        
        fetch('../api/folder_requirements_manager.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status == 200) {
                window.location.reload(); 
            } else {
                alert('Error: ' + (data.message || 'Failed to delete folder'));
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to delete folder');
        });
    }
}

function openAddRequirementModal(folderId, folderName) {
    document.getElementById('requirementIdInput').value = '';
    document.getElementById('requirementFolderId').value = folderId;
    document.getElementById('requirementForm').reset();
    document.getElementById('requirementFolderId').value = folderId; 
    document.getElementById('folderNameDisplay').querySelector('strong').textContent = folderName;
    document.getElementById('folderNameDisplay').style.display = 'block';
    document.getElementById('requirementModalTitle').innerText = 'New Requirement';
    new bootstrap.Modal(document.getElementById('requirementModal')).show();
}

document.getElementById('folderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const folderId = document.getElementById('folderIdInput').value;
    const action = folderId ? 'edit_global_folder' : 'add_global_folder';
    const formData = new FormData(this);
    formData.append('action', action);
    formData.append('area_number', '<?php echo $selected_area; ?>');
    formData.append('parameter_letter', '<?php echo $selected_param; ?>');
    formData.append('indicator_type', '<?php echo $selected_indicator; ?>');
    
    fetch('../api/folder_requirements_manager.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status == 200 || data.status == 201) {
            bootstrap.Modal.getInstance(document.getElementById('folderModal')).hide();
            location.reload(); 
        } else {
            alert('Error: ' + (data.message || 'Failed to save folder'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Error saving folder');
    });
});

document.getElementById('requirementForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const requirementId = document.getElementById('requirementIdInput').value;
    const action = requirementId ? 'edit_global_requirement' : 'add_global_requirement';
    const formData = new FormData(this);
    formData.append('action', action);
    
    fetch('../api/folder_requirements_manager.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status == 200 || data.status == 201) {
            bootstrap.Modal.getInstance(document.getElementById('requirementModal')).hide();
            const folderId = document.getElementById('requirementFolderId').value;
            loadRequirements(folderId); 
        } else {
            alert('Error: ' + (data.message || 'Failed to save requirement'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Error saving requirement');
    });
});

function loadRequirements(folderId) {
    const container = document.getElementById(`requirements-container-${folderId}`);
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div><span class="ms-2 text-muted small">Loading...</span></div>';
    
    fetch(`../api/folder_requirements_manager.php?action=get_global_requirements&folder_id=${folderId}&t=${new Date().getTime()}`)
        .then(r => r.json())
        .then(data => {
            if (data.data && Array.isArray(data.data) && data.data.length > 0) {
                let html = '<ul class="requirement-list">';
                data.data.forEach(req => {
                    html += `
                        <li class="requirement-item" id="requirement-item-${req.id}">
                            <i class="fas fa-file-alt req-icon-small"></i>
                            <div class="requirement-content">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="req-name">${escapeHtml(req.requirement_name)}</p>
                                        <span class="system-code mt-1">${escapeHtml(req.requirement_code)}</span>
                                        ${req.requirement_description ? `<span class="req-desc">${escapeHtml(req.requirement_description)}</span>` : ''}
                                    </div>
                                    <div class="action-group">
                                        <button class="btn-icon-subtle" onclick="editRequirement(${req.id})" title="Edit">
                                            <i class="fas fa-pen" style="font-size: 11px;"></i>
                                        </button>
                                        <button class="btn-icon-subtle delete" onclick="deleteRequirement(${req.id}, ${folderId})" title="Delete">
                                            <i class="fas fa-trash" style="font-size: 11px;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p class="text-muted text-center small py-2 mb-0">No requirements added yet</p>';
            }
        })
        .catch(e => {
            console.error('Error loading requirements:', e);
            container.innerHTML = '<p class="text-danger text-center small py-2 mb-0">Error loading requirements</p>';
        });
}

function editRequirement(requirementId) {
    fetch(`../api/folder_requirements_manager.php?action=get_global_requirement&requirement_id=${requirementId}&t=${new Date().getTime()}`)
        .then(r => r.json())
        .then(data => {
            if (data.data) {
                const req = data.data;
                document.getElementById('requirementIdInput').value = req.id;
                document.getElementById('requirementFolderId').value = req.global_folder_id;
                document.getElementById('requirementName').value = req.requirement_name;
                document.getElementById('requirementCode').value = req.requirement_code;
                document.getElementById('requirementDescription').value = req.requirement_description || '';
                document.getElementById('requirementModalTitle').innerText = 'Edit Requirement';
                document.getElementById('folderNameDisplay').style.display = 'none';
                new bootstrap.Modal(document.getElementById('requirementModal')).show();
            } else {
                alert('Failed to load requirement');
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to load requirement');
        });
}

function deleteRequirement(requirementId, folderId) {
    if (confirm('Delete this requirement?')) {
        const formData = new FormData();
        formData.append('action', 'delete_global_requirement');
        formData.append('requirement_id', requirementId);
        
        fetch('../api/folder_requirements_manager.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status == 200) {
                loadRequirements(folderId);
            } else {
                alert('Error: ' + (data.message || 'Failed to delete requirement'));
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to delete requirement');
        });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load requirements for all folders on page load
window.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($folders as $folder): ?>
    loadRequirements(<?php echo $folder['id']; ?>);
    <?php endforeach; ?>
});
</script>