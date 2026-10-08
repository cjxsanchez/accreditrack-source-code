<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Reports & Analytics';

$database = new Database();
$db = $database->getConnection();

// --- DATA LOGIC ---

// Get overall statistics
$stats = [];
$query = "SELECT status, COUNT(*) as count FROM documents GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute();
$status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_counts = [];
foreach ($status_stats as $stat) {
    $status_counts[$stat['status']] = $stat['count'];
}

$query = "SELECT pr.id, pr.name as program_name, pr.code,
                 COUNT(d.id) as total_documents,
                 SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
                 SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                 SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents
          FROM programs pr
          LEFT JOIN levels l ON pr.id = l.program_id
          LEFT JOIN areas a ON l.id = a.level_id
          LEFT JOIN parameters p ON a.id = p.area_id
          LEFT JOIN indicators i ON p.id = i.parameter_id
          LEFT JOIN documents d ON i.id = d.indicator_id
          WHERE pr.status = 'active' 
          GROUP BY pr.id, pr.name, pr.code
          ORDER BY total_documents DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$program_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT u.id, u.full_name, u.email,
                 COUNT(DISTINCT a.id) as assigned_areas,
                 COUNT(d.id) as total_documents,
                 SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
                 SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                 SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents
          FROM users u
          LEFT JOIN areas a ON a.assigned_faculty_id = u.id
          LEFT JOIN parameters p ON a.id = p.area_id
          LEFT JOIN indicators i ON p.id = i.parameter_id
          LEFT JOIN documents d ON i.id = d.indicator_id AND d.uploaded_by = u.id
          WHERE u.role = 'staff' AND u.status = 'active'
          GROUP BY u.id, u.full_name, u.email
          ORDER BY approved_documents DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$faculty_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$faculty_area_details = [];
foreach ($faculty_stats as $faculty) {
    $query = "SELECT a.name as area_name, a.area_number, 
                      pr.name as program_name, l.level_type,
                      COUNT(d.id) as total_docs,
                      SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) as approved_docs
              FROM areas a
              JOIN levels l ON a.level_id = l.id
              JOIN programs pr ON l.program_id = pr.id
              LEFT JOIN parameters p ON a.id = p.area_id
              LEFT JOIN indicators i ON p.id = i.parameter_id
              LEFT JOIN documents d ON i.id = d.indicator_id AND d.uploaded_by = ?
              WHERE a.assigned_faculty_id = ?
              GROUP BY a.id, a.name, a.area_number, pr.name, l.level_type";
    $stmt = $db->prepare($query);
    $stmt->execute([$faculty['id'], $faculty['id']]);
    $faculty_area_details[$faculty['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Recent activity
$query = "SELECT d.created_at as activity_date, u.full_name, d.title, d.status,
                 pr.name as program_name, a.name as area_name, d.admin_comment
          FROM documents d
          JOIN users u ON d.uploaded_by = u.id
          JOIN indicators i ON d.indicator_id = i.id
          JOIN parameters p ON i.parameter_id = p.id
          JOIN areas a ON p.area_id = a.id
          JOIN levels l ON a.level_id = l.id
          JOIN programs pr ON l.program_id = pr.id
          ORDER BY d.created_at DESC
          LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly document trends
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                 COUNT(*) as total,
                 SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                 SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                 SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
          FROM documents
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
    /* --- Modern Theme Styles --- */
    :root {
        --primary-brand: #4f46e5;
        --primary-soft: #eef2ff;
        --success-text: #059669;
        --success-bg: #ecfdf5;
        --warning-text: #d97706;
        --warning-bg: #fffbeb;
        --info-text: #2563eb;
        --info-bg: #eff6ff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --text-light: #94a3b8;
        --border-subtle: #f1f5f9;
        --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02), 0 1px 2px 0 rgba(0, 0, 0, 0.01);
    }

    body {
        background-color: #f8fafc;
        color: var(--text-main);
    }

    /* Header Section */
    .report-header {
        background: transparent;
        padding: 20px 0 35px 0;
        border: none;
        box-shadow: none;
        color: var(--text-main);
    }
    
    .report-header h2 {
        font-weight: 700;
        letter-spacing: -0.5px;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .report-header p {
        color: var(--text-muted);
        font-size: 1.05rem;
    }

    /* Top Stats Cards */
    .stat-card-modern {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid #f1f5f9;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .stat-card-modern .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .icon-box.primary { background: var(--primary-soft); color: var(--primary-brand); }
    .icon-box.success { background: var(--success-bg); color: var(--success-text); }
    .icon-box.warning { background: var(--warning-bg); color: var(--warning-text); }
    .icon-box.info    { background: var(--info-bg); color: var(--info-text); }

    .stat-card-modern h3 {
        font-size: 36px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1;
        letter-spacing: -1px;
    }

    .stat-card-modern p {
        color: var(--text-muted);
        font-weight: 500;
        margin: 0;
        font-size: 0.95rem;
    }

    /* Standard Card Container */
    .report-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        border: 1px solid #f1f5f9;
        margin-bottom: 24px;
        height: 100%;
    }

    .report-card .card-header {
        background: transparent;
        border-bottom: 1px solid var(--border-subtle);
        padding: 20px 24px;
    }

    .report-card .card-header h5 {
        margin: 0;
        font-weight: 700;
        color: #334155;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }

    .report-card .card-body {
        padding: 24px;
    }

    /* Progress Bars with Animation */
    .progress-bar-modern {
        height: 8px;
        border-radius: 4px;
        background: #f1f5f9;
        overflow: hidden;
    }

    .progress-bar-modern .fill {
        height: 100%;
        border-radius: 4px;
        width: 0; /* Start at 0 for animation */
        transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); /* Animation easing */
    }

    /* Faculty List */
    .faculty-list-item {
        padding: 16px 0;
        border-bottom: 1px solid var(--border-subtle);
    }
    .faculty-list-item:last-child { border-bottom: none; }

    .faculty-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #f1f5f9;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* Table Styling */
    .table thead th {
        border-bottom: 2px solid #f1f5f9;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding-bottom: 12px;
    }
    
    .table td {
        vertical-align: middle;
        padding: 16px 12px;
        border-bottom: 1px solid #f8fafc;
        color: #475569;
        font-size: 0.9rem;
    }

    .chart-container {
        position: relative;
        height: 320px;
    }

    .print-btn {
        background: white;
        color: var(--text-main);
        border: 1px solid #e2e8f0;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: var(--card-shadow);
        transition: all 0.2s ease;
    }
    
    .print-btn:hover {
        background: #f8fafc;
        color: var(--primary-brand);
    }

    /* --- PRINT STYLES --- */
    @media print {
        /* Hide UI elements not needed for print */
        .no-print, 
        .print-btn,
        .sidebar, 
        .main-sidebar, 
        .nav-sidebar, 
        header, 
        .navbar, 
        #sidebar { 
            display: none !important; 
        }

        /* Adjust Layout for Paper */
        body, 
        .main-content, 
        .content-wrapper, 
        .container-fluid {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Card Adjustments */
        .report-card, 
        .stat-card-modern {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            break-inside: avoid; /* Prevent card splitting across pages */
            margin-bottom: 20px;
        }

        .chart-container {
            /* Fix chart sizing for print */
            height: auto !important;
            min-height: 300px; 
            width: 100% !important;
        }

        /* Ensure text contrast */
        * {
            color: black !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div class="container-fluid px-4 mt-4">
    <div class="report-header d-flex justify-content-between align-items-end">
        <div>
            <h4>Dashboard Overview</h4>
            <p>Accreditation progress and faculty performance summary</p>
        </div>
        <div class="no-print">
            <button class="print-btn me-2" onclick="window.print()">
                <i class="fas fa-print me-2 text-muted"></i>Print
            </button>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card-modern">
                <div class="stat-header">
                    <div>
                        <p>Total Documents</p>
                        <h3><?php echo number_format(array_sum($status_counts)); ?></h3>
                    </div>
                    <div class="icon-box primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="progress-bar-modern mt-3">
                    <div class="fill" style="width: 0%; background: var(--primary-brand);" data-width="100%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card-modern">
                <div class="stat-header">
                    <div>
                        <p>Approved</p>
                        <h3><?php echo number_format($status_counts['approved'] ?? 0); ?></h3>
                    </div>
                    <div class="icon-box success">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                 <?php 
                   $total = array_sum($status_counts) > 0 ? array_sum($status_counts) : 1; 
                   $app_pct = (($status_counts['approved'] ?? 0) / $total) * 100;
                 ?>
                <div class="progress-bar-modern mt-3">
                    <div class="fill" style="width: 0%; background: var(--success-text);" data-width="<?php echo $app_pct; ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card-modern">
                <div class="stat-header">
                    <div>
                        <p>Pending Review</p>
                        <h3><?php echo number_format($status_counts['pending'] ?? 0); ?></h3>
                    </div>
                    <div class="icon-box warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="progress-bar-modern mt-3">
                    <div class="fill" style="width: 0%; background: var(--warning-text); opacity: 0.5;" data-width="50%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card-modern">
                <div class="stat-header">
                    <div>
                        <p>Active Faculty</p>
                        <h3><?php echo count($faculty_stats); ?></h3>
                    </div>
                    <div class="icon-box info">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="progress-bar-modern mt-3">
                    <div class="fill" style="width: 0%; background: var(--info-text);" data-width="100%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="report-card h-100">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i> Document Submission Trends</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="report-card h-100">
                <div class="card-header">
                    <h5><i class="fas fa-building me-2"></i> Breakdown by Program</h5>
                </div>
                <div class="card-body pt-2">
                    <?php foreach ($program_stats as $program): 
                        $total = $program['total_documents'] ?: 1;
                        $approved_pct = round(($program['approved_documents'] / $total) * 100);
                        $color = $approved_pct > 80 ? '#059669' : ($approved_pct > 50 ? '#4f46e5' : '#d97706');
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($program['program_name']); ?></span>
                                <small class="text-muted ms-2">(<?php echo $program['code']; ?>)</small>
                            </div>
                            <span class="fw-bold" style="color: <?php echo $color; ?>; font-size: 0.9rem;"><?php echo $approved_pct; ?>%</span>
                        </div>
                        <div class="progress-bar-modern">
                            <div class="fill" style="width: 0%; background: <?php echo $color; ?>;" data-width="<?php echo $approved_pct; ?>%"></div>
                        </div>
                        <div class="d-flex mt-2 gap-3" style="font-size: 0.75rem; color: #94a3b8;">
                            <span><?php echo $program['total_documents']; ?> Uploaded</span>
                            <span>&bull;</span>
                            <span><?php echo $program['pending_documents']; ?> Pending</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="card-header border-0 pb-0">
            <h5><i class="fas fa-user-graduate me-2"></i> Faculty Performance</h5>
        </div>
        <div class="card-body pt-1">
            <div class="faculty-list">
                <?php foreach ($faculty_stats as $faculty): 
                    $initials = '';
                    foreach (explode(' ', $faculty['full_name']) as $word) {
                        $initials .= strtoupper(substr($word, 0, 1));
                    }
                    $initials = substr($initials, 0, 2);
                ?>
                <div class="faculty-list-item">
                    <div class="row align-items-center gy-3">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="faculty-avatar me-3"><?php echo $initials; ?></div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($faculty['full_name']); ?></div>
                                    <small class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($faculty['email']); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-around">
                                <div class="text-center">
                                    <span class="d-block text-muted text-uppercase" style="font-size: 0.7rem;">Total</span>
                                    <span class="fw-bold text-dark"><?php echo $faculty['total_documents']; ?></span>
                                </div>
                                <div class="text-center">
                                    <span class="d-block text-muted text-uppercase" style="font-size: 0.7rem;">Appr.</span>
                                    <span class="fw-bold text-success"><?php echo $faculty['approved_documents']; ?></span>
                                </div>
                                <div class="text-center">
                                    <span class="d-block text-muted text-uppercase" style="font-size: 0.7rem;">Pend.</span>
                                    <span class="fw-bold text-warning"><?php echo $faculty['pending_documents']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex flex-wrap gap-1 justify-content-md-end">
                                <?php if (!empty($faculty_area_details[$faculty['id']])): ?>
                                    <?php 
                                    $count = 0; 
                                    foreach ($faculty_area_details[$faculty['id']] as $area): 
                                        if($count < 3):
                                    ?>
                                        <span class="badge bg-light text-muted border fw-normal">
                                            <?php echo htmlspecialchars($area['program_name']); ?> 
                                            (Area <?php echo $area['area_number']; ?>)
                                        </span>
                                    <?php 
                                        endif;
                                        $count++;
                                    endforeach; 
                                    if($count > 3):
                                    ?>
                                        <span class="badge bg-light text-muted border fw-normal">+<?php echo $count - 3; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-muted fst-italic">No areas assigned</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="card-header border-0 pb-0">
            <h5><i class="fas fa-history me-2"></i> Recent Activity Log</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 15%">Date</th>
                            <th style="width: 20%">Faculty</th>
                            <th style="width: 25%">Document</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 30%">Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td class="text-muted">
                                    <?php echo date('M j, Y', strtotime($activity['activity_date'])); ?>
                                </td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($activity['full_name']); ?></td>
                                <td>
                                    <div class="fw-normal text-dark"><?php echo htmlspecialchars($activity['title']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($activity['program_name']); ?> - <?php echo htmlspecialchars($activity['area_name']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = match($activity['status']) {
                                            'approved' => 'bg-success',
                                            'pending' => 'bg-warning text-dark',
                                            'rejected' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> bg-opacity-75 rounded-pill">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($activity['admin_comment']): ?>
                                        <small class="text-muted fst-italic">"<?php echo htmlspecialchars(substr($activity['admin_comment'], 0, 50)); ?>..."</small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthly_trends); ?>;

// Chart.js Configuration
new Chart(trendsCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => {
            const date = new Date(d.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [
            {
                label: 'Approved',
                data: monthlyData.map(d => d.approved),
                backgroundColor: '#10b981', 
                borderRadius: 4
            },
            {
                label: 'Pending',
                data: monthlyData.map(d => d.pending),
                backgroundColor: '#fbbf24',
                borderRadius: 4
            },
            {
                label: 'Rejected',
                data: monthlyData.map(d => d.rejected),
                backgroundColor: '#f87171',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        // --- ADDED ANIMATION CONFIGURATION ---
        animation: {
            y: {
                duration: 2000,
                from: 0, // Explicitly tell the chart to grow from y=0
                easing: 'easeOutQuart'
            },
            delay: (context) => {
                let delay = 0;
                // Simple delay logic based on data index and dataset index
                if (context.type === 'data' && context.mode === 'default') {
                    // 300ms delay per month + 100ms delay per stack type
                    delay = context.dataIndex * 300 + context.datasetIndex * 100;
                }
                return delay;
            }
        },
        // -------------------------------------
        plugins: {
            legend: { position: 'top', align: 'end' }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9', drawBorder: false },
                ticks: { color: '#94a3b8' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#64748b' }
            }
        }
    }
});

// Trigger CSS Animation for Progress Bars after load
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(() => {
        const bars = document.querySelectorAll('.progress-bar-modern .fill');
        bars.forEach(bar => {
            const width = bar.getAttribute('data-width');
            if(width) {
                bar.style.width = width;
            }
        });
    }, 200); // 200ms delay to ensure transition triggers
});

</script>

<?php include '../includes/footer.php'; ?>