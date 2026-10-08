<?php
/**
 * Get progress data for a level
 * Fixed: Handles missing requirement definitions by using upload count as fallback
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

header('Content-Type: application/json');

$level_id = $_GET['level_id'] ?? null;

if (!$level_id) {
    echo json_encode(['success' => false, 'message' => 'Level ID required']);
    exit;
}

try {
    $db = getDB();
    
    // Get all areas for this level
    $query = "SELECT id, area_number, name as area_name FROM areas WHERE level_id = ? ORDER BY area_number";
    $stmt = $db->prepare($query);
    $stmt->execute([$level_id]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalProgramRequired = 0;
    $totalProgramApproved = 0;
    $processedAreas = [];

    foreach ($areas as $area) {
        $areaId = $area['id'];
        $areaNum = $area['area_number']; // e.g. "1" or "Area 1"

        // Get Parameters for this Area
        $pQuery = "SELECT id, parameter_letter FROM parameters WHERE area_id = ?";
        $pStmt = $db->prepare($pQuery);
        $pStmt->execute([$areaId]);
        $params = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $areaRequired = 0;
        $areaApproved = 0;

        foreach ($params as $param) {
            // Get Indicators for this Parameter
            $iQuery = "SELECT id, indicator_type FROM indicators WHERE parameter_id = ?";
            $iStmt = $db->prepare($iQuery);
            $iStmt->execute([$param['id']]);
            $indicators = $iStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($indicators as $ind) {
                // 1. Get Actual Uploaded Counts
                $docQuery = "SELECT 
                                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                                COUNT(*) as total_uploaded
                             FROM documents 
                             WHERE indicator_id = ?";
                $dStmt = $db->prepare($docQuery);
                $dStmt->execute([$ind['id']]);
                $docStats = $dStmt->fetch(PDO::FETCH_ASSOC);
                
                $uploadedTotal = (int)($docStats['total_uploaded'] ?? 0);
                $approvedCount = (int)($docStats['approved_count'] ?? 0);

                // 2. Get DB Requirements (if any)
                // Try checking global requirements or program requirements
                // Simplified: We assume requirements are set in 'program_requirements' or 'global_requirements'
                // For simplicity/robustness, we check if any 'required' flags exist. 
                // If not, we fall back to uploaded count.
                
                // Let's assume a strictly defined requirement count query here:
                $reqQuery = "SELECT COUNT(*) as total FROM global_requirements gr 
                             JOIN global_accreditation_tags gat ON gr.global_folder_id = gat.id
                             WHERE gat.indicator_type = ? AND gat.parameter_letter = ? AND gat.area_number = ?
                             AND gr.is_active = 1";
                // Note: You might need to clean $areaNum to just be the number if it's "Area 1"
                $cleanAreaNum = str_replace('Area ', '', $areaNum);
                
                $rStmt = $db->prepare($reqQuery);
                $rStmt->execute([$ind['indicator_type'], $param['parameter_letter'], $cleanAreaNum]);
                $dbReqCount = (int)$rStmt->fetchColumn();

                // 3. LOGIC FIX:
                // If DB says 0 requirements, but we have uploads, use uploads as the total.
                // Otherwise use the DB count.
                $finalTotal = ($dbReqCount > 0) ? $dbReqCount : $uploadedTotal;
                
                // Prevent division by zero
                if ($finalTotal == 0) $finalTotal = 1; 
                // If 0 uploads and 0 requirements, we treat it as 0 progress but valid total 1 to avoid NaN.
                // However, better logic: if both 0, ignore from summation? 
                // Let's stick to accumulating.
                
                // If actual uploads > 0 but requirements was 0, calculate percentage based on uploads.
                if ($uploadedTotal > 0 && $dbReqCount == 0) {
                     // If we are falling back, usually we consider total = uploaded.
                     // But if some are pending, approved < total.
                     $finalTotal = $uploadedTotal;
                }

                $areaRequired += $finalTotal;
                $areaApproved += $approvedCount;
            }
        }

        // Calculate Area Progress
        // If nothing required and nothing uploaded, progress is 0.
        // If we defaulted total to 1 above to avoid div/0, and approved is 0, result is 0%.
        $areaProgress = ($areaRequired > 0) ? round(($areaApproved / $areaRequired) * 100, 1) : 0;

        $processedAreas[] = [
            'area_number' => $areaNum,
            'area_name' => $area['area_name'],
            'progress' => $areaProgress,
            'approved' => $areaApproved,
            'required' => $areaRequired
        ];

        $totalProgramRequired += $areaRequired;
        $totalProgramApproved += $areaApproved;
    }

    $overallProgress = ($totalProgramRequired > 0) ? round(($totalProgramApproved / $totalProgramRequired) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'areas' => $processedAreas,
            'overall_progress' => $overallProgress,
            'total_approved' => $totalProgramApproved,
            'total_required' => $totalProgramRequired
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>