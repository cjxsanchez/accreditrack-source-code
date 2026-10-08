<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'References';
$database = new Database();
$db = $database->getConnection();

// Get all references
$query = "SELECT r.*, u.full_name 
          FROM reference_files r 
          LEFT JOIN users u ON r.uploaded_by = u.id 
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$references = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
.reference-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reference-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.reference-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.reference-description {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 12px;
    line-height: 1.5;
}

.reference-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: #9ca3af;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.reference-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.reference-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.empty-state-icon {
    font-size: 64px;
    display: block;
    margin-bottom: 16px;
    color: #d1d5db;
}
</style>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-book"></i> Accreditation Reference Documents
        </h3>
        <p class="text-muted" style="margin: 8px 0 0 0; font-size: 14px;">
            Here are the reference documents provided for accreditation preparation
        </p>
    </div>
    <div class="card-body">
        <?php if (empty($references)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt empty-state-icon"></i>
                <p style="font-size: 16px; font-weight: 500;">No reference documents available yet</p>
                <p style="font-size: 14px;">Check back later for accreditation reference materials</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($references as $ref): ?>
                    <div class="col-lg-6 col-md-12">
                        <div class="reference-card">
                            <div class="reference-title">
                                <i class="fas fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($ref['title']); ?>
                            </div>
                            
                            <?php if (!empty($ref['description'])): ?>
                                <div class="reference-description">
                                    <?php echo htmlspecialchars($ref['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reference-meta">
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y H:i', strtotime($ref['created_at'])); ?>
                                </span>
                                <span>
                                    <i class="fas fa-file"></i>
                                    <?php echo htmlspecialchars($ref['filename']); ?>
                                </span>
                            </div>
                            
                            <div class="reference-actions">
                                <a href="<?php echo $ref['file_path']; ?>" 
                                   class="btn btn-sm btn-primary" 
                                   target="_blank">
                                    <i class="fas fa-external-link-alt"></i> View in New Tab
                                </a>
                                <a href="<?php echo $ref['file_path']; ?>" 
                                   class="btn btn-sm btn-info" 
                                   download>
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
