<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Bulk Import Accreditation Tags';
$db = getDB();

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $csv_data = array_map('str_getcsv', file($file['tmp_name']));
        $headers = array_shift($csv_data); // Get headers
        
        $imported_count = 0;
        $errors = [];
        
        try {
            $db->beginTransaction();
            
            foreach ($csv_data as $row_num => $row) {
                if (empty($row[0])) continue; // Skip empty rows
                
                $area_number = trim($row[0] ?? '');
                $parameter_letter = trim($row[1] ?? '');
                $indicator_type = trim($row[2] ?? '');
                $tag_name = trim($row[3] ?? '');
                $tag_code = trim($row[4] ?? '');
                $description = trim($row[5] ?? '');
                
                if (!$area_number || !$parameter_letter || !$indicator_type || !$tag_name) {
                    $errors[] = "Row " . ($row_num + 2) . ": Missing required fields";
                    continue;
                }
                
                // Find the indicator
                $query = "SELECT i.id FROM indicators i
                          JOIN parameters p ON i.parameter_id = p.id
                          JOIN areas a ON p.area_id = a.id
                          WHERE a.area_number = ? AND p.parameter_letter = ? AND i.indicator_type = ?
                          LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$area_number, $parameter_letter, $indicator_type]);
                $indicator = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$indicator) {
                    $errors[] = "Row " . ($row_num + 2) . ": Indicator not found";
                    continue;
                }
                
                // Check if tag already exists
                $query = "SELECT id FROM accreditation_tags WHERE indicator_id = ? AND tag_code = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$indicator['id'], $tag_code]);
                if ($stmt->fetch()) {
                    continue; // Skip duplicate
                }
                
                // Insert tag
                $query = "INSERT INTO accreditation_tags (indicator_id, tag_name, tag_code, description, order_index)
                          VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(order_index), -1) + 1 FROM accreditation_tags WHERE indicator_id = ?))";
                $stmt = $db->prepare($query);
                $stmt->execute([$indicator['id'], $tag_name, $tag_code, $description, $indicator['id']]);
                $imported_count++;
            }
            
            $db->commit();
            $success_message = "Successfully imported $imported_count tags!";
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Import failed: " . $e->getMessage();
        }
    } else {
        $error_message = "File upload error";
    }
}

include '../includes/header.php';
?>

<main class="content-area">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-4">Bulk Import Accreditation Tags</h1>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-warning">
                        <h6>Import Warnings:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Upload CSV File</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="csvFile" class="form-label">CSV File Format</label>
                                <input type="file" class="form-control" id="csvFile" name="csv_file" accept=".csv" required>
                                <div class="form-text">
                                    Required columns (in order):
                                    <br>1. Area Number (e.g., "Area I")
                                    <br>2. Parameter Letter (e.g., "A")
                                    <br>3. Indicator Type (S/I/O)
                                    <br>4. Tag Name
                                    <br>5. Tag Code
                                    <br>6. Description (optional)
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Import Tags
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Sample CSV Format</h5>
                    </div>
                    <div class="card-body">
                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">Area,Parameter,Indicator Type,Tag Name,Tag Code,Description
Area I,A,S,Institutional Mission Statement,MISSION-001,Official mission statement
Area I,A,S,Strategic Goals and Objectives,GOALS-001,Strategic planning documents
Area I,B,S,Board Resolution and Charter,BOARD-001,Governing board documentation
Area I,B,S,Organizational Chart,ORG-CHART-001,Administrative structure
Area II,A,S,Academic Program Description,PROG-DESC-001,Official program documentation
Area II,A,I,Curriculum Mapping,CURRICULUM-001,Course alignment documentation
Area II,A,O,Learning Outcomes Assessment,OUTCOMES-001,Student achievement data</pre>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Current Tags Overview</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get statistics
                        $query = "SELECT 
                                    COUNT(DISTINCT a.id) as total_areas,
                                    COUNT(DISTINCT p.id) as total_parameters,
                                    COUNT(DISTINCT i.id) as total_indicators,
                                    COUNT(DISTINCT at.id) as total_tags
                                  FROM areas a
                                  LEFT JOIN parameters p ON a.id = p.area_id
                                  LEFT JOIN indicators i ON p.id = i.parameter_id
                                  LEFT JOIN accreditation_tags at ON i.id = at.indicator_id AND at.is_active = TRUE";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        
                        <div class="mb-3">
                            <h6>Statistics</h6>
                            <ul class="list-unstyled">
                                <li><strong>Total Areas:</strong> <?php echo $stats['total_areas']; ?></li>
                                <li><strong>Total Parameters:</strong> <?php echo $stats['total_parameters']; ?></li>
                                <li><strong>Total Indicators:</strong> <?php echo $stats['total_indicators']; ?></li>
                                <li><strong>Total Tags Added:</strong> <span class="badge bg-success"><?php echo $stats['total_tags']; ?></span></li>
                            </ul>
                        </div>
                        
                        <hr>
                        
                        <h6>Tags by Area</h6>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Area</th>
                                        <th>Tags</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "SELECT a.area_number, COUNT(DISTINCT at.id) as tag_count
                                              FROM areas a
                                              LEFT JOIN parameters p ON a.id = p.area_id
                                              LEFT JOIN indicators i ON p.id = i.parameter_id
                                              LEFT JOIN accreditation_tags at ON i.id = at.indicator_id AND at.is_active = TRUE
                                              GROUP BY a.id, a.area_number
                                              ORDER BY a.area_number";
                                    $stmt = $db->prepare($query);
                                    $stmt->execute();
                                    $area_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($area_stats as $stat):
                                    ?>
                                        <tr>
                                            <td><?php echo $stat['area_number']; ?></td>
                                            <td><span class="badge bg-info"><?php echo $stat['tag_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
