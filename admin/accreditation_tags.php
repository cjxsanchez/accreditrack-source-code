<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Accreditation Tags Management';
$db = getDB();

$query = "SELECT DISTINCT p.id, p.name, p.code FROM programs p WHERE p.status = 'active' ORDER BY p.name";
$stmt = $db->prepare($query);
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected program, level, area if provided
$selected_program = $_GET['program_id'] ?? null;
$selected_level = $_GET['level_id'] ?? null;
$selected_area = $_GET['area_id'] ?? null;

$area_structure = [];
$area_info = null;

if ($selected_area) {
    // Get area details
    $query = "SELECT a.*, l.id as level_id, l.level_type, p.id as program_id, p.name as program_name
              FROM areas a
              JOIN levels l ON a.level_id = l.id
              JOIN programs p ON l.program_id = p.id
              WHERE a.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_area]);
    $area_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($area_info) {
        // Get all parameters and indicators with tags
        $query = "SELECT p.id as param_id, p.parameter_letter, p.name as param_name,
                         i.id as indicator_id, i.indicator_type, i.name as indicator_name,
                         i.required_documents,
                         COUNT(DISTINCT at.id) as tag_count
                  FROM parameters p
                  LEFT JOIN indicators i ON p.id = i.parameter_id
                  LEFT JOIN accreditation_tags at ON i.id = at.indicator_id AND at.is_active = TRUE
                  WHERE p.area_id = ?
                  GROUP BY p.id, i.id
                  ORDER BY p.parameter_letter, i.indicator_type";
        $stmt = $db->prepare($query);
        $stmt->execute([$selected_area]);
        $area_structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/header.php';
?>

<main class="content-area">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-4">Accreditation Tags Management</h1>
                
                <!-- Program Selection -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Step 1: Select Program & Area</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Choose Program</label>
                                <select id="selectProgram" class="form-select" onchange="loadLevels()">
                                    <option value="">-- Select Program --</option>
                                    <?php foreach ($programs as $prog): ?>
                                        <option value="<?php echo $prog['id']; ?>" <?php echo $selected_program == $prog['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prog['code']); ?> - <?php echo htmlspecialchars($prog['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Choose Level</label>
                                <!-- Added id to track level selection and proper error handling -->
                                <select id="selectLevel" class="form-select" onchange="loadAreas()">
                                    <option value="">-- Select Level --</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label">Choose Area</label>
                                <!-- Added id to track area selection for tag management -->
                                <select id="selectArea" class="form-select" onchange="loadAreaTags()">
                                    <option value="">-- Select Area --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($area_info && !empty($area_structure)): ?>
        <!-- Area Tags Management -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <?php echo htmlspecialchars($area_info['area_number']); ?>: Manage Tags for All Indicators
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    // Group by indicator type
                    $grouped = [];
                    foreach ($area_structure as $item) {
                        if ($item['indicator_id']) {
                            $type = $item['indicator_type'] == 'S' ? 'System - Inputs and Processes' : 
                                    ($item['indicator_type'] == 'I' ? 'Implementation' : 'Outcome/s');
                            $grouped[$type][] = $item;
                        }
                    }
                    
                    foreach ($grouped as $type_name => $items): 
                    ?>
                        <div class="col-md-4 mb-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><?php echo $type_name; ?></h6>
                                </div>
                                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                    <?php foreach ($items as $item): ?>
                                        <div class="mb-3 p-3 border rounded" id="indicator-<?php echo $item['indicator_id']; ?>">
                                            <h6 class="mb-2">
                                                <?php echo htmlspecialchars($item['param_name']); ?>
                                                <span class="badge bg-secondary float-end">
                                                    <?php echo $item['tag_count']; ?> tags
                                                </span>
                                            </h6>
                                            
                                            <div id="tags-list-<?php echo $item['indicator_id']; ?>" class="mb-2">
                                                <!-- Tags will be loaded here -->
                                            </div>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="openAddTagModal(<?php echo $item['indicator_id']; ?>)">
                                                <i class="fas fa-plus"></i> Add Tag
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="editTags(<?php echo $item['indicator_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit Tags
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add/Edit Tag Modal -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tagModalTitle">Add New Tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="tagForm">
                <div class="modal-body">
                    <input type="hidden" id="indicatorIdInput" name="indicator_id">
                    <input type="hidden" id="tagIdInput" name="tag_id">
                    
                    <div class="mb-3">
                        <label for="tagName" class="form-label">Tag Name/Requirement</label>
                        <input type="text" class="form-control" id="tagName" name="tag_name" required>
                        <div class="form-text">Example: Board of Trustees Meeting Minutes, Organizational Chart, etc.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagCode" class="form-label">Tag Code (Short Reference)</label>
                        <input type="text" class="form-control" id="tagCode" name="tag_code" required>
                        <div class="form-text">Example: BOT-MINUTES, ORG-CHART, etc.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="tagDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Tag
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadLevels() {
    const programId = document.getElementById('selectProgram').value;
    const levelSelect = document.getElementById('selectLevel');
    const areaSelect = document.getElementById('selectArea');
    
    levelSelect.innerHTML = '<option value="">-- Select Level --</option>';
    areaSelect.innerHTML = '<option value="">-- Select Area --</option>';
    
    if (!programId) return;
    
    fetch(`../api/get_levels.php?program_id=${programId}`)
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            console.log("[v0] Levels loaded:", data);
            if (data.data && Array.isArray(data.data)) {
                data.data.forEach(level => {
                    const option = document.createElement('option');
                    option.value = level.id;
                    option.textContent = level.level_type || level.name;
                    levelSelect.appendChild(option);
                });
            }
        })
        .catch(e => {
            console.error('Error loading levels:', e);
            levelSelect.innerHTML = '<option value="" disabled>Error loading levels</option>';
        });
}

function loadAreas() {
    const levelId = document.getElementById('selectLevel').value;
    const areaSelect = document.getElementById('selectArea');
    
    areaSelect.innerHTML = '<option value="">-- Select Area --</option>';
    
    if (!levelId) return;
    
    fetch(`../api/get_program_structure.php?level_id=${levelId}`)
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            console.log("[v0] Areas loaded:", data);
            if (data.data && Array.isArray(data.data)) {
                data.data.forEach(area => {
                    const option = document.createElement('option');
                    option.value = area.id;
                    option.textContent = `${area.area_number}: ${area.name || ''}`;
                    areaSelect.appendChild(option);
                });
            } else {
                console.error('Unexpected data format:', data);
                areaSelect.innerHTML = '<option value="" disabled>No areas found</option>';
            }
        })
        .catch(e => {
            console.error('Error loading areas:', e);
            areaSelect.innerHTML = '<option value="" disabled>Error loading areas</option>';
        });
}

function loadAreaTags() {
    const programId = document.getElementById('selectProgram').value;
    const levelId = document.getElementById('selectLevel').value;
    const areaId = document.getElementById('selectArea').value;
    
    if (!areaId) return;
    
    window.location.href = `?program_id=${programId}&level_id=${levelId}&area_id=${areaId}`;
}

function openAddTagModal(indicatorId) {
    document.getElementById('tagIdInput').value = '';
    document.getElementById('indicatorIdInput').value = indicatorId;
    document.getElementById('tagModalTitle').textContent = 'Add New Tag';
    document.getElementById('tagForm').reset();
    new bootstrap.Modal(document.getElementById('tagModal')).show();
}

function editTags(indicatorId) {
    // Load tags for this indicator
    fetch(`../api/manage_tags.php?action=get_tags&indicator_id=${indicatorId}`)
        .then(r => r.json())
        .then(data => {
            if (data.data && data.data.length > 0) {
                // Show first tag for editing (or create interface for editing multiple)
                const tag = data.data[0];
                document.getElementById('tagIdInput').value = tag.id;
                document.getElementById('indicatorIdInput').value = indicatorId;
                document.getElementById('tagName').value = tag.tag_name;
                document.getElementById('tagCode').value = tag.tag_code;
                document.getElementById('tagDescription').value = tag.description || '';
                document.getElementById('tagModalTitle').textContent = 'Edit Tag';
                new bootstrap.Modal(document.getElementById('tagModal')).show();
            }
        })
        .catch(e => console.error('Error loading tags:', e));
}

document.getElementById('tagForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const tagId = document.getElementById('tagIdInput').value;
    const action = tagId ? 'edit_tag' : 'add_tag';
    const formData = new FormData(this);
    formData.append('action', action);
    
    fetch('../api/manage_tags.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success || data.status === 200 || data.status === 201) {
            alert('Tag saved successfully!');
            bootstrap.Modal.getInstance(document.getElementById('tagModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to save tag'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Error saving tag');
    });
});

window.addEventListener('load', function() {
    const areaId = new URLSearchParams(window.location.search).get('area_id');
    if (areaId) {
        // Pre-populate dropdowns from URL parameters
        const programId = new URLSearchParams(window.location.search).get('program_id');
        const levelId = new URLSearchParams(window.location.search).get('level_id');
        
        if (programId) {
            document.getElementById('selectProgram').value = programId;
            loadLevels();
            
            setTimeout(() => {
                if (levelId) {
                    document.getElementById('selectLevel').value = levelId;
                    loadAreas();
                    
                    setTimeout(() => {
                        document.getElementById('selectArea').value = areaId;
                    }, 100);
                }
            }, 100);
        }
        
        // Load and display tags for all indicators
        fetch(`../api/manage_tags.php?action=get_area_tags&area_id=${areaId}`)
            .then(r => r.json())
            .then(data => {
                if (data.data) {
                    data.data.forEach(indicator => {
                        const container = document.getElementById(`tags-list-${indicator.indicator_id}`);
                        if (container) {
                            if (indicator.tags.length > 0) {
                                container.innerHTML = indicator.tags.map(tag => 
                                    `<div class="badge bg-primary me-1 mb-1">
                                        ${tag.tag_name}
                                        <button type="button" class="btn-close btn-close-white" onclick="deleteTag(${tag.id})" style="font-size: 0.6rem;"></button>
                                    </div>`
                                ).join('');
                            } else {
                                container.innerHTML = '<small class="text-muted">No tags added yet</small>';
                            }
                        }
                    });
                }
            })
            .catch(e => console.error('Error loading tags:', e));
    }
});

function deleteTag(tagId) {
    if (confirm('Are you sure you want to delete this tag?')) {
        const formData = new FormData();
        formData.append('action', 'delete_tag');
        formData.append('tag_id', tagId);
        
        fetch('../api/manage_tags.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success || data.status === 200) {
                alert('Tag deleted successfully!');
                location.reload();
            }
        })
        .catch(e => console.error('Error:', e));
    }
}
</script>

<?php include '../includes/footer.php'; ?>
