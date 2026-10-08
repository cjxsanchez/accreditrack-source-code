<?php
require_once '../config/config.php';
requireStaff();

$page_title = 'Faculty Dashboard';

$database = new Database();
$db = $database->getConnection();

// --- DATA LOGIC ---
$userQuery = "SELECT full_name FROM users WHERE id = ?";
$userStmt = $db->prepare($userQuery);
$userStmt->execute([$_SESSION['user_id']]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);
$userName = $userData['full_name'] ?? 'Faculty';

$query = "SELECT a.*, l.name as level_name, l.level_type, p.name as program_name, p.code as program_code
          FROM areas a
          JOIN levels l ON a.level_id = l.id
          JOIN programs p ON l.program_id = p.id
          WHERE a.assigned_faculty_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$assigned_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
$area_analytics = [];

function convertAreaNumberToNumeric($areaNumber) {
    $romanMap = [
        'I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4', 'V' => '5',
        'VI' => '6', 'VII' => '7', 'VIII' => '8', 'IX' => '9', 'X' => '10'
    ];
    if (preg_match('/Area\s+([IVX]+)/i', $areaNumber, $matches)) {
        $roman = strtoupper($matches[1]);
        return $romanMap[$roman] ?? '1';
    }
    $upper = strtoupper(trim($areaNumber));
    if (isset($romanMap[$upper])) return $romanMap[$upper];
    if (is_numeric($areaNumber)) return $areaNumber;
    if (preg_match('/(\d+)/', $areaNumber, $matches)) return $matches[1];
    return '1';
}

if (!empty($assigned_areas)) {
    $area_ids = array_column($assigned_areas, 'id');
    $placeholders = str_repeat('?,', count($area_ids) - 1) . '?';
    
    // Stats Queries
    $query = "SELECT COUNT(*) as count FROM documents d
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              WHERE p.area_id IN ($placeholders) AND d.uploaded_by = ?";
    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($area_ids, [$_SESSION['user_id']]));
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM documents d
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              WHERE p.area_id IN ($placeholders) AND d.uploaded_by = ? AND d.status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($area_ids, [$_SESSION['user_id']]));
    $stats['approved'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM documents d
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              WHERE p.area_id IN ($placeholders) AND d.uploaded_by = ? AND d.status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($area_ids, [$_SESSION['user_id']]));
    $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM documents d
              JOIN indicators i ON d.indicator_id = i.id
              JOIN parameters p ON i.parameter_id = p.id
              WHERE p.area_id IN ($placeholders) AND d.uploaded_by = ? AND d.status = 'rejected'";
    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($area_ids, [$_SESSION['user_id']]));
    $stats['rejected'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    foreach ($assigned_areas as $area) {
        $area_id = $area['id'];
        $numericAreaNumber = convertAreaNumberToNumeric($area['area_number'] ?? '');
        
        $area_data = [
            'id' => $area_id,
            'name' => $area['name'],
            'area_number' => $area['area_number'] ?? '',
            'program_name' => $area['program_name'],
            'level_name' => $area['level_name'],
            'parameters' => [],
            'overall_approved' => 0,
            'overall_total' => 0,
            'overall_percentage' => 0
        ];
        
        $paramQuery = "SELECT p.id, p.parameter_letter, p.name as parameter_name
                       FROM parameters p WHERE p.area_id = ? ORDER BY p.parameter_letter ASC";
        $paramStmt = $db->prepare($paramQuery);
        $paramStmt->execute([$area_id]);
        $parameters = $paramStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($parameters as $param) {
            $param_data = [
                'letter' => $param['parameter_letter'],
                'name' => $param['parameter_name'],
                'approved' => 0,
                'total_requirements' => 0,
                'percentage' => 0,
                'indicators' => [
                    'S' => ['approved' => 0, 'total' => 0, 'percentage' => 0],
                    'I' => ['approved' => 0, 'total' => 0, 'percentage' => 0],
                    'O' => ['approved' => 0, 'total' => 0, 'percentage' => 0]
                ]
            ];
            
            $indQuery = "SELECT i.id, i.indicator_type FROM indicators i WHERE i.parameter_id = ?";
            $indStmt = $db->prepare($indQuery);
            $indStmt->execute([$param['id']]);
            $indicators = $indStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($indicators as $ind) {
                $indicator_type = $ind['indicator_type'];
                
                $reqQuery = "SELECT COUNT(gr.id) as total 
                             FROM global_requirements gr
                             JOIN global_accreditation_tags gat ON gr.global_folder_id = gat.id
                             WHERE gat.area_number = ? 
                             AND gat.parameter_letter = ? 
                             AND gat.indicator_type = ?
                             AND gat.is_active = TRUE 
                             AND gr.is_active = TRUE";
                $reqStmt = $db->prepare($reqQuery);
                $reqStmt->execute([$numericAreaNumber, $param['parameter_letter'], $indicator_type]);
                $total_req = (int)($reqStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
                
                if ($total_req == 0) {
                    $fallbackQuery = "SELECT required_documents FROM indicators WHERE id = ?";
                    $fallbackStmt = $db->prepare($fallbackQuery);
                    $fallbackStmt->execute([$ind['id']]);
                    $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                    $total_req = (int)($fallbackResult['required_documents'] ?? 0);
                }
                
                $appQuery = "SELECT COUNT(*) as approved FROM documents d
                             WHERE d.indicator_id = ? AND d.uploaded_by = ? AND d.status = 'approved'";
                $appStmt = $db->prepare($appQuery);
                $appStmt->execute([$ind['id'], $_SESSION['user_id']]);
                $approved_count = (int)($appStmt->fetch(PDO::FETCH_ASSOC)['approved'] ?? 0);
                
                $indicator_percentage = $total_req > 0 ? round(($approved_count / $total_req) * 100, 1) : 0;
                
                $param_data['indicators'][$indicator_type] = [
                    'approved' => $approved_count,
                    'total' => $total_req,
                    'percentage' => $indicator_percentage
                ];
                
                $param_data['approved'] += $approved_count;
                $param_data['total_requirements'] += $total_req;
                
                $area_data['overall_approved'] += $approved_count;
                $area_data['overall_total'] += $total_req;
            }
            
            $param_data['percentage'] = $param_data['total_requirements'] > 0 
                ? round(($param_data['approved'] / $param_data['total_requirements']) * 100, 1) 
                : 0;
            
            $area_data['parameters'][] = $param_data;
        }
        
        $area_data['overall_percentage'] = $area_data['overall_total'] > 0 
            ? round(($area_data['overall_approved'] / $area_data['overall_total']) * 100, 1) 
            : 0;
        
        $area_analytics[] = $area_data;
    }
}

$query = "SELECT d.*, i.name as indicator_name, i.indicator_type
          FROM documents d
          JOIN indicators i ON d.indicator_id = i.id
          JOIN parameters p ON i.parameter_id = p.id
          JOIN areas a ON p.area_id = a.id
          WHERE a.assigned_faculty_id = ? AND d.uploaded_by = ?
          ORDER BY d.id DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* --- DASHBOARD DESIGN SYSTEM --- */
:root {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #EFF6FF;
    --success: #10B981;
    --success-bg: #D1FAE5;
    --success-text: #065F46;
    --warning: #F59E0B;
    --warning-bg: #FEF3C7;
    --warning-text: #92400E;
    --danger: #EF4444;
    --danger-bg: #FEE2E2;
    --danger-text: #991B1B;
    --text-main: #1E293B;
    --text-muted: #64748B;
    --bg-surface: #FFFFFF;
    --bg-app: #F8FAFC;
    --border: #E5E7EB;
    --radius-md: 12px;
    --radius-lg: 16px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

body {
    background-color: var(--bg-app);
    color: var(--text-main);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

h1, h2, h3, h4, h5, h6 { letter-spacing: -0.025em; font-weight: 600; }

.dashboard-card {
    background: var(--bg-surface);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.dashboard-card:hover { box-shadow: var(--shadow-md); }

.card-header-clean {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
}

.card-body-clean { padding: 24px; }

/* Parameter Breakdown Specific Styles */
.param-card {
    background: #F8F9FA; 
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.param-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #E9ECEF;
    padding-bottom: 15px;
}

.indicator-card {
    background: #FFFFFF;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    border: 1px solid #F1F3F5;
    height: 100%;
}

.indicator-title {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
    margin-bottom: 15px;
    text-transform: uppercase;
}

.indicator-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 10px;
    line-height: 1;
}

.indicator-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 5px;
}

/* Charts */
.doughnut-wrapper {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.overall-chart-container {
    position: relative;
    width: 100%;
    height: 300px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Stat Cards */
.stat-card-modern {
    padding: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
}
.stat-icon-wrapper {
    width: 56px; height: 56px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
}
.stat-primary .stat-icon-wrapper { background: var(--primary-light); color: var(--primary); }
.stat-success .stat-icon-wrapper { background: var(--success-bg); color: var(--success-text); }
.stat-warning .stat-icon-wrapper { background: var(--warning-bg); color: var(--warning-text); }
.stat-danger .stat-icon-wrapper { background: var(--danger-bg); color: var(--danger-text); }

/* Quick Actions */
.action-card-horizontal {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    background: white;
    text-decoration: none;
    color: var(--text-main);
    height: 100%;
    transition: transform 0.2s ease;
}
.action-card-horizontal:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-sm);
    background: #F8FAFC;
}

/* Recent Uploads */
.doc-item-modern {
    display: flex; align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
}
.doc-item-modern:last-child { border-bottom: none; }
.doc-icon {
    width: 40px; height: 40px;
    border-radius: 8px;
    background: #FEE2E2; color: #EF4444;
    display: flex; align-items: center; justify-content: center;
    margin-right: 16px;
}

/* Badge Status */
.badge-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-success-soft { background: var(--success-bg); color: var(--success-text); }
.badge-warning-soft { background: var(--warning-bg); color: var(--warning-text); }
.badge-danger-soft { background: var(--danger-bg); color: var(--danger-text); }

/* Welcome Hero */
.welcome-hero {
    background: white;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 32px;
    border: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: visible;
}
.welcome-hero::before {
    content: ''; position: absolute;
    top: 0; left: 0; bottom: 0; width: 6px;
    background: var(--primary);
    border-radius: 16px 0 0 16px;
}

/* Real-time Clock */
.clock-container {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.realtime-date {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-bottom: 2px;
}
.realtime-time {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}

</style>

<div class="welcome-hero">
    <div class="d-flex align-items-center">
        <div>
            <h2 class="mb-1">Hello, <?php echo htmlspecialchars($userName); ?>!</h2>
            <p class="mb-0 text-muted">Here is the accreditation status overview.</p>
        </div>
    </div>
    
    <div class="clock-container d-none d-md-flex">
        <div id="realDateDisplay" class="realtime-date">Loading...</div>
        <div id="realTimeDisplay" class="realtime-time">00:00:00</div>
    </div>
</div>

<?php if (empty($assigned_areas)): ?>
    <div class="alert alert-warning shadow-sm border-0 rounded-3 mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i> You have not been assigned to any areas yet.
    </div>
<?php endif; ?>

<div class="row g-4 mb-5">
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card stat-card-modern mb-0 stat-primary">
            <div class="stat-content">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Documents</p>
            </div>
            <div class="stat-icon-wrapper"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card stat-card-modern mb-0 stat-success">
            <div class="stat-content">
                <h3><?php echo $stats['approved']; ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-icon-wrapper"><i class="fas fa-check"></i></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card stat-card-modern mb-0 stat-warning">
            <div class="stat-content">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-icon-wrapper"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dashboard-card stat-card-modern mb-0 stat-danger">
            <div class="stat-content">
                <h3><?php echo $stats['rejected']; ?></h3>
                <p>Needs Revision</p>
            </div>
            <div class="stat-icon-wrapper"><i class="fas fa-times"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        
        <?php if (!empty($area_analytics)): ?>
        <?php foreach ($area_analytics as $index => $area_data): ?>
            <div class="dashboard-card">
                <div class="card-header-clean">
                    <div>
                        <h5 class="mb-1 text-primary"><?php echo htmlspecialchars($area_data['name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($area_data['program_name']); ?> • <?php echo htmlspecialchars($area_data['level_name']); ?></small>
                    </div>
                    <!-- <div class="text-end">
                        <h3 class="mb-0 text-dark"><?php echo $area_data['overall_percentage']; ?>%</h3>
                    </div> -->
                </div>
                
                <div class="card-body-clean">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Overall Completion</span>
                            <span><?php echo $area_data['overall_approved']; ?>/<?php echo $area_data['overall_total']; ?> Requirements</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 5px; background: #E9ECEF;">
                            <div class="progress-bar bg-primary" style="width: <?php echo max($area_data['overall_percentage'], 2); ?>%; border-radius: 5px;"></div>
                        </div>
                    </div>

                    <div class="row mb-5 align-items-center">
                        <div class="col-lg-8 border-end">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Parameter Progress</h6>
                            <div style="height: 300px;">
                                <?php if (!empty($area_data['parameters'])): ?>
                                    <canvas id="mainChart<?php echo $index; ?>"></canvas>
                                <?php else: ?>
                                    <div class="h-100 d-flex align-items-center justify-content-center bg-light rounded text-muted">No Data</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3 text-center">Overall Breakdown</h6>
                            <div class="overall-chart-container">
                                <canvas id="overallPieChart<?php echo $index; ?>"></canvas>
                            </div>
                            <div class="text-center mt-3">
                                <span class="badge bg-light text-dark border me-2"><i class="fas fa-circle text-success small me-1"></i> Completed</span>
                                <span class="badge bg-light text-dark border"><i class="fas fa-circle text-secondary small me-1" style="color:#E9ECEF!important"></i> Remaining</span>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted small fw-bold mb-3"><i class="fas fa-list me-2"></i>Parameter Detailed Breakdown</h6>
                    
                    <?php foreach ($area_data['parameters'] as $pIdx => $param): ?>
                    <div class="param-card">
                        <div class="param-header">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Parameter <?php echo htmlspecialchars($param['letter']); ?>: <?php echo htmlspecialchars($param['name']); ?></h6>
                            </div>
                            <span class="badge bg-primary text-white rounded-pill px-3 py-2"><?php echo $param['percentage']; ?>% Complete</span>
                        </div>

                        <div class="row align-items-center g-4">
                            <div class="col-md-3 text-center border-end">
                                <h2 class="text-primary fw-bold mb-0">
                                    <?php echo $param['approved']; ?>
                                    <span class="text-muted fs-6 fw-normal">/ <?php echo $param['total_requirements']; ?></span>
                                </h2>
                                <small class="text-muted d-block mb-3">Requirements Completed</small>
                                <div class="progress" style="height: 6px; background: #E9ECEF; border-radius: 3px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $param['percentage']; ?>%"></div>
                                </div>
                            </div>

                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="indicator-card">
                                            <div class="indicator-title">System - Inputs & Processes</div>
                                            <div class="doughnut-wrapper">
                                                <canvas id="sysPie<?php echo $index; ?>_<?php echo $pIdx; ?>"></canvas>
                                            </div>
                                            <div class="indicator-value" style="color: #3B82F6;">
                                                <?php echo $param['indicators']['S']['percentage']; ?>%
                                            </div>
                                            <div class="indicator-sub">
                                                <?php echo $param['indicators']['S']['approved']; ?>/<?php echo $param['indicators']['S']['total']; ?> completed
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="indicator-card">
                                            <div class="indicator-title">Implementation</div>
                                            <div class="doughnut-wrapper">
                                                <canvas id="impPie<?php echo $index; ?>_<?php echo $pIdx; ?>"></canvas>
                                            </div>
                                            <div class="indicator-value" style="color: #10B981;">
                                                <?php echo $param['indicators']['I']['percentage']; ?>%
                                            </div>
                                            <div class="indicator-sub">
                                                <?php echo $param['indicators']['I']['approved']; ?>/<?php echo $param['indicators']['I']['total']; ?> completed
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="indicator-card">
                                            <div class="indicator-title">Outcome/s</div>
                                            <div class="doughnut-wrapper">
                                                <canvas id="outPie<?php echo $index; ?>_<?php echo $pIdx; ?>"></canvas>
                                            </div>
                                            <div class="indicator-value" style="color: #EF4444;">
                                                <?php echo $param['indicators']['O']['percentage']; ?>%
                                            </div>
                                            <div class="indicator-sub">
                                                <?php echo $param['indicators']['O']['approved']; ?>/<?php echo $param['indicators']['O']['total']; ?> completed
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="card-header-clean">
                <h5 class="mb-0">Assigned Areas</h5>
                <a href="assigned_areas.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
            </div>
            <div class="card-body-clean">
                <?php if (!empty($assigned_areas)): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($assigned_areas as $area): ?>
                            <a href="assigned_areas.php?area_id=<?php echo $area['id']; ?>" class="d-flex p-3 text-decoration-none text-dark align-items-center border rounded bg-light hover-bg-white">
                                <div class="me-3 bg-white rounded p-2 text-primary shadow-sm"><i class="fas fa-layer-group fa-lg"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($area['name']); ?></h6>
                                    <small class="text-muted fw-semibold"><?php echo htmlspecialchars($area['program_code']); ?></small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">No assigned areas found.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header-clean">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body-clean">
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="assigned_areas.php" class="action-card-horizontal">
                            <div class="rounded-circle p-3 bg-primary bg-opacity-10 me-3 text-primary"><i class="fas fa-cloud-upload-alt fa-lg"></i></div>
                            <div><div class="fw-bold">Upload Documents</div><small class="text-muted">Add evidence</small></div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="notifications.php" class="action-card-horizontal">
                            <div class="rounded-circle p-3 bg-warning bg-opacity-10 me-3 text-warning"><i class="fas fa-bell fa-lg"></i></div>
                            <div><div class="fw-bold">Notifications</div><small class="text-muted">Check alerts</small></div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="profile.php" class="action-card-horizontal">
                            <div class="rounded-circle p-3 bg-secondary bg-opacity-10 me-3 text-secondary"><i class="fas fa-user-cog fa-lg"></i></div>
                            <div><div class="fw-bold">My Profile</div><small class="text-muted">Account details</small></div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header-clean">
                <h5 class="mb-0">Recent Uploads</h5>
            </div>
            <div class="card-body-clean p-0">
                <?php if (empty($recent_documents)): ?>
                    <div class="p-5 text-center text-muted"><i class="fas fa-folder-open mb-3 d-block fa-3x opacity-25"></i>No documents yet</div>
                <?php else: ?>
                    <?php foreach ($recent_documents as $doc): ?>
                    <div class="doc-item-modern">
                        <div class="doc-icon"><i class="fas fa-file-pdf"></i></div>
                        <div class="flex-grow-1 overflow-hidden pe-3">
                            <h6 class="mb-1 text-truncate text-dark small fw-bold"><?php echo htmlspecialchars($doc['title']); ?></h6>
                            <small class="text-muted d-block" style="font-size: 0.75rem"><?php echo date('M j, g:i a', strtotime($doc['created_at'])); ?></small>
                        </div>
                        <span class="badge-status <?php echo $doc['status'] == 'approved' ? 'badge-success-soft' : ($doc['status'] == 'rejected' ? 'badge-danger-soft' : 'badge-warning-soft'); ?>">
                            <?php echo ucfirst($doc['status']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- REAL-TIME CLOCK ---
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const dateString = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        const timeEl = document.getElementById('realTimeDisplay');
        const dateEl = document.getElementById('realDateDisplay');
        
        if (timeEl) timeEl.textContent = timeString;
        if (dateEl) dateEl.textContent = dateString;
    }
    
    // Update immediately then every second
    updateClock();
    setInterval(updateClock, 1000);

    // --- CHART LOGIC ---
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748B';
    
    // Exact colors provided in requirements
    function getColor(val) {
        if (val >= 75) return 'rgba(40, 167, 69, 0.8)'; // Green
        if (val >= 50) return 'rgba(23, 162, 184, 0.8)'; // Cyan
        if (val >= 25) return 'rgba(255, 193, 7, 0.8)'; // Yellow
        return 'rgba(220, 53, 69, 0.8)'; // Red
    }

    const chartRegistry = {};

    <?php if (!empty($area_analytics)): ?>
        <?php foreach ($area_analytics as $index => $area_data): ?>
            
            // 1. Overall Completion Pie Chart (Big one on right)
            const overallCompleted<?php echo $index; ?> = <?php echo $area_data['overall_percentage']; ?>;
            const overallRemaining<?php echo $index; ?> = Math.max(0, 100 - overallCompleted<?php echo $index; ?>);
            
            chartRegistry['overallPieChart<?php echo $index; ?>'] = {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Remaining'],
                    datasets: [{
                        data: [overallCompleted<?php echo $index; ?>, overallRemaining<?php echo $index; ?>],
                        backgroundColor: ['#50CD89', '#E9ECEF'], // Green for completed, Light gray for remaining
                        hoverOffset: 4,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 2000,
                        delay: 300 // Delayed rotation
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + '%';
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'textCenter',
                    beforeDraw: function(chart) {
                        var width = chart.width,
                            height = chart.height,
                            ctx = chart.ctx;
                        
                        ctx.restore();
                        var fontSize = (height / 114).toFixed(2);
                        ctx.font = "bold " + fontSize + "em sans-serif";
                        ctx.textBaseline = "middle";
                        ctx.fillStyle = "#333";
                        
                        var text = overallCompleted<?php echo $index; ?> + "%",
                            textX = Math.round((width - ctx.measureText(text).width) / 2),
                            textY = height / 2;
                        
                        ctx.fillText(text, textX, textY);
                        ctx.save();
                    }
                }]
            };

            <?php if (!empty($area_data['parameters'])): ?>
                
                // 2. Main Bar Chart (Scroll Trigger + Staggered Delay)
                const rawData<?php echo $index; ?> = <?php echo json_encode(array_column($area_data['parameters'], 'percentage')); ?>;
                const bgColors<?php echo $index; ?> = rawData<?php echo $index; ?>.map(value => getColor(value));
                
                chartRegistry['mainChart<?php echo $index; ?>'] = {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($area_data['parameters'], 'letter')); ?>,
                        datasets: [{
                            label: 'Completion %',
                            data: rawData<?php echo $index; ?>,
                            backgroundColor: bgColors<?php echo $index; ?>,
                            borderRadius: 4,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            delay: (context) => {
                                let delay = 0;
                                // Delay each bar by 200ms * index
                                if (context.type === 'data' && context.mode === 'default' && !context.dropped) {
                                    delay = context.dataIndex * 200; 
                                    context.dropped = true;
                                }
                                return delay;
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { 
                                backgroundColor: '#1E293B',
                                padding: 12, cornerRadius: 8, displayColors: false,
                                callbacks: { label: (c) => c.parsed.y + '% Completed' }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true, max: 100,
                                grid: { color: '#F1F5F9', drawBorder: false },
                                ticks: { font: { size: 11 }, padding: 10 }
                            },
                            x: {
                                grid: { display: false, drawBorder: false },
                                ticks: { font: { weight: '600', size: 12 } }
                            }
                        }
                    }
                };
                
                // 3. Mini Breakdown Pie Charts (Rotate Animation)
                const pieOpts = {
                    responsive: true, maintainAspectRatio: false, cutout: '75%',
                    animation: { 
                        animateRotate: true, 
                        animateScale: true, 
                        duration: 2000, // Slow rotation
                        delay: 500 // Wait 0.5s after scroll
                    },
                    plugins: { legend: { display: false }, tooltip: { enabled: false } }
                };

                <?php foreach ($area_data['parameters'] as $pIdx => $param): ?>
                    // System (Blue)
                    chartRegistry['sysPie<?php echo $index; ?>_<?php echo $pIdx; ?>'] = {
                        type: 'doughnut',
                        data: {
                            datasets: [{
                                data: [<?php echo $param['indicators']['S']['percentage']; ?>, <?php echo 100 - $param['indicators']['S']['percentage']; ?>],
                                backgroundColor: ['#3B82F6', '#E9ECEF'], borderWidth: 0
                            }]
                        }, options: pieOpts
                    };
                    // Implementation (Teal)
                    chartRegistry['impPie<?php echo $index; ?>_<?php echo $pIdx; ?>'] = {
                        type: 'doughnut',
                        data: {
                            datasets: [{
                                data: [<?php echo $param['indicators']['I']['percentage']; ?>, <?php echo 100 - $param['indicators']['I']['percentage']; ?>],
                                backgroundColor: ['#10B981', '#E9ECEF'], borderWidth: 0
                            }]
                        }, options: pieOpts
                    };
                    // Outcome (Red)
                    chartRegistry['outPie<?php echo $index; ?>_<?php echo $pIdx; ?>'] = {
                        type: 'doughnut',
                        data: {
                            datasets: [{
                                data: [<?php echo $param['indicators']['O']['percentage']; ?>, <?php echo 100 - $param['indicators']['O']['percentage']; ?>],
                                backgroundColor: ['#EF4444', '#E9ECEF'], borderWidth: 0
                            }]
                        }, options: pieOpts
                    };
                <?php endforeach; ?>

            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    // --- INTERSECTION OBSERVER ---
    // This waits for the chart canvas to be visible on screen before creating the chart
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const canvas = entry.target;
                const id = canvas.id;
                if (chartRegistry[id]) {
                    new Chart(canvas.getContext('2d'), chartRegistry[id]);
                    delete chartRegistry[id]; // Prevent re-render
                    obs.unobserve(canvas); // Stop watching
                }
            }
        });
    }, { threshold: 0.1 }); // Trigger when 10% visible

    // Start observing all potential charts
    Object.keys(chartRegistry).forEach(id => {
        const el = document.getElementById(id);
        if(el) observer.observe(el);
    });
});
</script>

<?php include '../includes/footer.php'; ?>

