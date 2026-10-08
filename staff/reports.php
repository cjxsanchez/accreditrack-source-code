<?php
/**
 * Staff Reports - Comprehensive document summary report
 */
require_once '../config/config.php';
requireStaff();

$page_title = 'Reports & Analytics';

$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user_id'];

// Get user info
$userQuery = "SELECT full_name, email FROM users WHERE id = ?";
$userStmt = $db->prepare($userQuery);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Get assigned areas
$areasQuery = "SELECT a.*, l.level_type, p.name as program_name
                FROM areas a
                JOIN levels l ON a.level_id = l.id
                JOIN programs p ON l.program_id = p.id
                WHERE a.assigned_faculty_id = ?";
$areasStmt = $db->prepare($areasQuery);
$areasStmt->execute([$userId]);
$assigned_areas = $areasStmt->fetchAll(PDO::FETCH_ASSOC);

// Get document statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM documents d
    WHERE d.uploaded_by = ?";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get recent approved documents
$approvedQuery = "SELECT d.*, a.name as area_name, gat.folder_name, gr.requirement_name
                  FROM documents d
                  JOIN indicators i ON d.indicator_id = i.id
                  JOIN parameters p ON i.parameter_id = p.id
                  JOIN areas a ON p.area_id = a.id
                  LEFT JOIN global_accreditation_tags gat ON d.folder_id = gat.id
                  LEFT JOIN global_requirements gr ON d.requirement_id = gr.id
                  WHERE d.uploaded_by = ? AND d.status = 'approved'
                  ORDER BY d.updated_at DESC LIMIT 10";
$approvedStmt = $db->prepare($approvedQuery);
$approvedStmt->execute([$userId]);
$approved_docs = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent rejected documents with comments
$rejectedQuery = "SELECT d.*, a.name as area_name, d.admin_comment, gat.folder_name, gr.requirement_name
                  FROM documents d
                  JOIN indicators i ON d.indicator_id = i.id
                  JOIN parameters p ON i.parameter_id = p.id
                  JOIN areas a ON p.area_id = a.id
                  LEFT JOIN global_accreditation_tags gat ON d.folder_id = gat.id
                  LEFT JOIN global_requirements gr ON d.requirement_id = gr.id
                  WHERE d.uploaded_by = ? AND d.status = 'rejected'
                  ORDER BY d.updated_at DESC LIMIT 10";
$rejectedStmt = $db->prepare($rejectedQuery);
$rejectedStmt->execute([$userId]);
$rejected_docs = $rejectedStmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly upload statistics
$monthlyQuery = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM documents 
    WHERE uploaded_by = ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC LIMIT 6";
$monthlyStmt = $db->prepare($monthlyQuery);
$monthlyStmt->execute([$userId]);
$monthly_stats = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Screen Styles */
.report-header {
    /* UPDATED: Matches Sidebar Gradient defined in header.php */
    background: var(--primary-gradient); 
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 30px;
}

.report-header h2 {
    margin: 0;
    font-weight: 700;
}

.report-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    text-align: center;
    height: 100%;
}

.stat-card.approved { border-top: 4px solid #10b981; }
.stat-card.pending { border-top: 4px solid #f59e0b; }
.stat-card.rejected { border-top: 4px solid #ef4444; }

/* UPDATED: Matches new Primary Color */
.stat-card.total { border-top: 4px solid var(--primary-color); } 

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-card.approved .stat-value { color: #10b981; }
.stat-card.pending .stat-value { color: #f59e0b; }
.stat-card.rejected .stat-value { color: #ef4444; }

/* UPDATED: Matches new Primary Color */
.stat-card.total .stat-value { color: var(--primary-color); } 

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.report-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 18px 25px;
    border-bottom: 1px solid #e9ecef;
}

.section-header h5 {
    margin: 0;
    font-weight: 600;
    color: #333;
}

.section-body {
    padding: 25px;
}

.doc-item {
    padding: 15px;
    border-radius: 10px;
    background: #f8f9fa;
    margin-bottom: 12px;
    transition: background 0.2s ease;
}

.doc-item:hover {
    background: #e9ecef;
}

.doc-item:last-child {
    margin-bottom: 0;
}

.doc-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.doc-meta {
    font-size: 0.85rem;
    color: #6c757d;
}

.doc-meta span {
    margin-right: 15px;
}

.admin-feedback {
    background: #fff3cd;
    border-left: 3px solid #ffc107;
    padding: 12px 15px;
    border-radius: 0 8px 8px 0;
    margin-top: 12px;
    font-size: 0.9rem;
}

.admin-feedback strong {
    color: #856404;
}

.area-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    /* UPDATED: Matches Sidebar Gradient */
    background: var(--primary-gradient);
    color: white;
    margin-bottom: 8px;
}

.print-btn {
    /* UPDATED: Matches Sidebar Gradient */
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 500;
}

.print-btn:hover {
    opacity: 0.9;
    color: white;
}

.chart-container {
    position: relative;
    height: 300px;
}

/* PRINT SPECIFIC STYLES 
   This hides the sidebar/header and formats the report for paper
*/
@media print {
    /* 1. Hide everything on the page */
    body * {
        visibility: hidden;
    }

    /* 2. Make the specific report container visible */
    #print-container, #print-container * {
        visibility: visible;
    }

    /* 3. Position the report at the top-left of the paper */
    #print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
        background: white;
    }

    /* 4. Hide interactive elements */
    .no-print, .print-btn { 
        display: none !important; 
    }

    /* 5. Prevent page breaks inside cards/charts */
    .report-section, .stat-card { 
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #ddd; /* Add border for clarity on paper */
        box-shadow: none;
    }

    /* 6. Force background colors (gradients/badges) to print */
    .report-header, .area-badge, .stat-card.approved, .stat-card.pending, .stat-card.rejected, .stat-card.total {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    
    /* 7. Adjust header padding for print */
    .report-header {
        padding: 15px !important;
        margin-bottom: 20px;
    }
}
</style>

<div class="container-fluid" id="print-container">
    <div class="report-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4><i class="fas fa-chart-bar me-2"></i>Document Report Summary</h4>
                <p>Generated on <?php echo date('F d, Y \a\t g:i A'); ?></p>
                <p class="mb-0"><strong>Faculty:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</p>
            </div>
            <div class="no-print">
                <button class="print-btn" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card total">
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card approved">
                <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card pending">
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card rejected">
                <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="report-section">
                <div class="section-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Document Status Distribution</h5>
                </div>
                <div class="section-body">
                    <div class="chart-container">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="report-section">
                <div class="section-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Monthly Upload Trend</h5>
                </div>
                <div class="section-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="report-section">
        <div class="section-header">
            <h5><i class="fas fa-folder-open me-2"></i>Assigned Areas (<?php echo count($assigned_areas); ?>)</h5>
        </div>
        <div class="section-body">
            <?php if (empty($assigned_areas)): ?>
                <p class="text-muted text-center">No areas assigned yet.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($assigned_areas as $area): ?>
                        <div class="col-md-4 mb-3">
                            <div class="doc-item">
                                <div class="doc-title"><?php echo htmlspecialchars($area['name']); ?></div>
                                <div class="doc-meta">
                                    <span><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($area['program_name']); ?></span><br>
                                    <span><i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($area['level_type']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="report-section">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-check-circle text-success me-2"></i>Recently Approved Documents</h5>
            <span class="badge bg-success"><?php echo count($approved_docs); ?> documents</span>
        </div>
        <div class="section-body">
            <?php if (empty($approved_docs)): ?>
                <p class="text-muted text-center">No approved documents yet.</p>
            <?php else: ?>
                <?php foreach ($approved_docs as $doc): ?>
                    <div class="doc-item">
                        <span class="area-badge"><?php echo htmlspecialchars($doc['area_name']); ?></span>
                        <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                        <div class="doc-meta">
                            <?php if (!empty($doc['folder_name'])): ?>
                                <span><i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($doc['folder_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($doc['requirement_name'])): ?>
                                <span><i class="fas fa-file-alt me-1"></i><?php echo htmlspecialchars($doc['requirement_name']); ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($doc['updated_at'] ?? $doc['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="report-section">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-times-circle text-danger me-2"></i>Rejected Documents & Feedback</h5>
            <span class="badge bg-danger"><?php echo count($rejected_docs); ?> documents</span>
        </div>
        <div class="section-body">
            <?php if (empty($rejected_docs)): ?>
                <p class="text-muted text-center">No rejected documents.</p>
            <?php else: ?>
                <?php foreach ($rejected_docs as $doc): ?>
                    <div class="doc-item">
                        <span class="area-badge"><?php echo htmlspecialchars($doc['area_name']); ?></span>
                        <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                        <div class="doc-meta">
                            <?php if (!empty($doc['folder_name'])): ?>
                                <span><i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($doc['folder_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($doc['requirement_name'])): ?>
                                <span><i class="fas fa-file-alt me-1"></i><?php echo htmlspecialchars($doc['requirement_name']); ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($doc['updated_at'] ?? $doc['created_at'])); ?></span>
                        </div>
                        <?php if (!empty($doc['admin_comment'])): ?>
                            <div class="admin-feedback">
                                <strong><i class="fas fa-comment me-1"></i>Admin Feedback:</strong><br>
                                <?php echo htmlspecialchars($doc['admin_comment']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Status Distribution Pie Chart
const statusCtx = document.getElementById('statusPieChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Pending', 'Rejected'],
        datasets: [{
            data: [
                <?php echo $stats['approved'] ?? 0; ?>,
                <?php echo $stats['pending'] ?? 0; ?>,
                <?php echo $stats['rejected'] ?? 0; ?>
            ],
            backgroundColor: [
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: [
                'rgba(16, 185, 129, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(239, 68, 68, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Monthly Trend Chart
const monthlyData = <?php echo json_encode(array_reverse($monthly_stats)); ?>;
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(m => {
            const date = new Date(m.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'Total Uploads',
            data: monthlyData.map(m => m.total),
            // UPDATED: Changed from Purple to Blue to match new theme
            backgroundColor: 'rgba(59, 130, 246, 0.6)', 
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 2,
            borderRadius: 6
        }, {
            label: 'Approved',
            data: monthlyData.map(m => m.approved),
            backgroundColor: 'rgba(16, 185, 129, 0.6)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>