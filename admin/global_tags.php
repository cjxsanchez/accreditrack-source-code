<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Global Accreditation Tags Management';
$db = getDB();

// Get all areas (from Area I to Area X)
$areas = [];
for ($i = 1; $i <= 10; $i++) {
    $areas[] = 'Area ' . ($i === 1 ? 'I' : ($i === 2 ? 'II' : ($i === 3 ? 'III' : ($i === 4 ? 'IV' : ($i === 5 ? 'V' : ($i === 6 ? 'VI' : ($i === 7 ? 'VII' : ($i === 8 ? 'VIII' : ($i === 9 ? 'IX' : 'X')))))))));
}

$selected_area = $_GET['area'] ?? null;
$area_tags = [];

if ($selected_area) {
    $query = "SELECT * FROM global_accreditation_tags 
              WHERE area_number = ? AND is_active = TRUE
              ORDER BY parameter_letter, indicator_type, order_index";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_area]);
    $area_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
?>

<main class="content-area">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-4">Global Accreditation Tags Management</h1>
                <p class="text-muted">Manage global file requirement tags that are automatically applied to all programs and levels</p>
            </div>
        </div>

        <!-- Area Selection -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Select Area to Manage Tags</h5>
            </div>
            <div class="card-body">
                <div class="btn-group" role="group">
                    <?php foreach ($areas as $area): ?>
                        <a href="?area=<?php echo urlencode($area); ?>" 
                           class="btn btn-outline-primary <?php echo $selected_area === $area ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($area); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($selected_area): ?>
        <!-- Tags Display -->
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Tags for <?php echo htmlspecialchars($selected_area); ?></h5>
                <button type="button" class="btn btn-sm btn-light" onclick="openAddTagModal()">
                    <i class="fas fa-plus"></i> Add New Global Tag
                </button>
            </div>
            <div class="card-body">
                <?php 
                // Group by parameter and indicator type
                $grouped = [];
                foreach ($area_tags as $tag) {
                    $key = $tag['parameter_letter'] . '-' . $tag['indicator_type'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'parameter' => $tag['parameter_letter'],
                            'indicator_type' => $tag['indicator_type'],
                            'indicator_name' => $tag['indicator_type'] === 'S' ? 'System - Inputs & Processes' : 
                                              ($tag['indicator_type'] === 'I' ? 'Implementation' : 'Outcome/s'),
                            'tags' => []
                        ];
                    }
                    $grouped[$key]['tags'][] = $tag;
                }
                ?>
                
                <?php if (empty($grouped)): ?>
                    <div class="alert alert-info">
                        No global tags defined for this area yet. 
                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddTagModal()">
                            Add First Tag
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($grouped as $group): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">
                                            Parameter <?php echo htmlspecialchars($group['parameter']); ?> - 
                                            <?php echo htmlspecialchars($group['indicator_name']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                        <div class="list-group">
                                            <?php foreach ($group['tags'] as $tag): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($tag['tag_name']); ?></h6>
                                                        <p class="mb-1"><small class="text-muted">Code: <code><?php echo htmlspecialchars($tag['tag_code']); ?></code></small></p>
                                                        <?php if ($tag['description']): ?>
                                                            <p class="mb-0"><small><?php echo htmlspecialchars($tag['description']); ?></small></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="btn-group btn-group-sm ms-2">
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="editTag(<?php echo $tag['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteTag(<?php echo $tag['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
                <h5 class="modal-title" id="tagModalTitle">Add Global Tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="tagForm">
                <div class="modal-body">
                    <input type="hidden" id="tagIdInput" name="tag_id">
                    
                    <div class="mb-3">
                        <label for="tagArea" class="form-label">Area *</label>
                        <input type="text" class="form-control" id="tagArea" value="<?php echo htmlspecialchars($selected_area ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagParameter" class="form-label">Parameter Letter *</label>
                        <select class="form-select" id="tagParameter" name="parameter_letter" required>
                            <option value="">-- Select Parameter --</option>
                            <?php for ($i = 0; $i < 10; $i++): ?>
                                <option value="<?php echo chr(65 + $i); ?>"><?php echo chr(65 + $i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagIndicator" class="form-label">Indicator Type *</label>
                        <select class="form-select" id="tagIndicator" name="indicator_type" required>
                            <option value="S">System - Inputs & Processes</option>
                            <option value="I">Implementation</option>
                            <option value="O">Outcome/s</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagName" class="form-label">Tag Name/Requirement *</label>
                        <input type="text" class="form-control" id="tagName" name="tag_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagCode" class="form-label">Tag Code *</label>
                        <input type="text" class="form-control" id="tagCode" name="tag_code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tagDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="tagDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Global Tag
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openAddTagModal() {
    document.getElementById('tagIdInput').value = '';
    document.getElementById('tagForm').reset();
    document.getElementById('tagArea').value = '<?php echo $selected_area; ?>';
    document.getElementById('tagModalTitle').textContent = 'Add Global Tag';
    new bootstrap.Modal(document.getElementById('tagModal')).show();
}

function editTag(tagId) {
    // Fetch tag data
    fetch(`../api/global_tags_manager.php?action=get_global_tags&tag_id=${tagId}`)
        .then(r => r.json())
        .then(data => {
            if (data.data) {
                const tag = Array.isArray(data.data) ? data.data[0] : data.data;
                document.getElementById('tagIdInput').value = tag.id;
                document.getElementById('tagParameter').value = tag.parameter_letter;
                document.getElementById('tagIndicator').value = tag.indicator_type;
                document.getElementById('tagName').value = tag.tag_name;
                document.getElementById('tagCode').value = tag.tag_code;
                document.getElementById('tagDescription').value = tag.description || '';
                document.getElementById('tagModalTitle').textContent = 'Edit Global Tag';
                new bootstrap.Modal(document.getElementById('tagModal')).show();
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to load tag');
        });
}

function deleteTag(tagId) {
    if (confirm('Delete this global tag from all programs?')) {
        const formData = new FormData();
        formData.append('action', 'delete_global_tag');
        formData.append('tag_id', tagId);
        
        fetch('../api/global_tags_manager.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message || 'Tag deleted');
            location.reload();
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Failed to delete tag');
        });
    }
}

document.getElementById('tagForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const tagId = document.getElementById('tagIdInput').value;
    const action = tagId ? 'edit_global_tag' : 'add_global_tag';
    const formData = new FormData(this);
    formData.append('action', action);
    formData.append('area_number', '<?php echo $selected_area; ?>');
    
    fetch('../api/global_tags_manager.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.data || data.status === 200 || data.status === 201) {
            alert(data.message || 'Tag saved successfully');
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
</script>

<?php include '../includes/footer.php'; ?>
