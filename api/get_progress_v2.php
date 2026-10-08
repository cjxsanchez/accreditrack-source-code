<?php
/**
 * Get progress data for a level - Updated to include dynamic tags
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$level_id = $_GET['level_id'] ?? null;

if (!$level_id) {
    jsonResponse([], 400, 'Level ID required');
}

try {
    $db = getDB();
    
    $query = "SELECT a.id, a.area_number, a.assigned_faculty_id, u.full_name 
              FROM areas a 
              LEFT JOIN users u ON a.assigned_faculty_id = u.id 
              WHERE a.level_id = ? 
              ORDER BY a.area_number";
    $stmt = $db->prepare($query);
    $stmt->execute([$level_id]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($areas as &$area) {
        $indicatorProgress = [];
        
        // Get all indicators for this area
        $query2 = "SELECT i.id, i.indicator_type, i.name,
                          COUNT(DISTINCT at.id) as total_tags
                   FROM indicators i
                   JOIN parameters p ON i.parameter_id = p.id
                   LEFT JOIN accreditation_tags at ON i.id = at.indicator_id AND at.is_active = TRUE
                   WHERE p.area_id = ?
                   GROUP BY i.id, i.indicator_type, i.name";
        $stmt2 = $db->prepare($query2);
        $stmt2->execute([$area['id']]);
        $indicators = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $totalRequired = 0;
        $totalApproved = 0;
        
        foreach ($indicators as $indicator) {
            // Get total required tags (or fallback to required_documents if no tags)
            $totalTags = $indicator['total_tags'];
            
            if ($totalTags == 0) {
                // Use old system: required_documents column
                $query3 = "SELECT required_documents FROM indicators WHERE id = ?";
                $stmt3 = $db->prepare($query3);
                $stmt3->execute([$indicator['id']]);
                $ind_data = $stmt3->fetch(PDO::FETCH_ASSOC);
                $totalTags = $ind_data['required_documents'] ?? 0;
            }
            
            // Get approved documents for this indicator
            $query3 = "SELECT COUNT(DISTINCT d.id) as approved_count 
                       FROM documents d 
                       WHERE d.indicator_id = ? AND d.status = 'approved'";
            $stmt3 = $db->prepare($query3);
            $stmt3->execute([$indicator['id']]);
            $result = $stmt3->fetch(PDO::FETCH_ASSOC);
            $approvedCount = $result['approved_count'] ?? 0;
            
            $totalRequired += $totalTags;
            $totalApproved += $approvedCount;
            
            $indicatorProgress[$indicator['indicator_type']] = [
                'total' => $totalTags,
                'approved' => $approvedCount,
                'percentage' => $totalTags > 0 ? round(($approvedCount / $totalTags) * 100, 1) : 0
            ];
        }
        
        // Calculate area progress
        $area['progress'] = $totalRequired > 0 ? round(($totalApproved / $totalRequired) * 100, 1) : 0;
        $area['total_required'] = $totalRequired;
        $area['total_approved'] = $totalApproved;
        $area['indicator_breakdown'] = $indicatorProgress;
    }
    
    // Calculate overall program progress
    $overallProgress = 0;
    $totalProgramRequired = 0;
    $totalProgramApproved = 0;
    
    foreach ($areas as $area) {
        $totalProgramRequired += $area['total_required'];
        $totalProgramApproved += $area['total_approved'];
    }
    
    $overallProgress = $totalProgramRequired > 0 ? round(($totalProgramApproved / $totalProgramRequired) * 100, 1) : 0;

    jsonResponse([
        'areas' => $areas,
        'overall_progress' => $overallProgress,
        'total_required' => $totalProgramRequired,
        'total_approved' => $totalProgramApproved
    ], 200, 'Progress data retrieved with tag calculations');
} catch (Exception $e) {
    logMessage('Error fetching progress: ' . $e->getMessage(), 'error');
    jsonResponse([], 500, 'Error fetching progress');
}
?>
