<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$level_id = $input['level_id'] ?? null;

if (!$level_id) {
    echo json_encode(['success' => false, 'message' => 'Level ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Define area structure with parameters only (no hardcoded doc counts)
$areaStructure = [
    'Area I' => [
        'name' => 'VISION, MISSION, GOALS AND OBJECTIVES',
        'parameters' => [
            'A' => ['name' => 'Statement of Vision, Mission, Goals and Objectives'],
            'B' => ['name' => 'Dissemination and Acceptability']
        ]
    ],
    'Area II' => [
        'name' => 'FACULTY',
        'parameters' => [
            'A' => ['name' => 'Academic Qualifications and Professional Experience'],
            'B' => ['name' => 'Recruitment, Selection and Orientation'],
            'C' => ['name' => 'Faculty Adequacy and Loading'],
            'D' => ['name' => 'Rank and Tenure'],
            'E' => ['name' => 'Faculty Development'],
            'F' => ['name' => 'Professional Performance and Scholarly Works'],
            'G' => ['name' => 'Salaries, Fringe Benefits and Incentives'],
            'H' => ['name' => 'Professionalism']
        ]
    ],
    'Area III' => [
        'name' => 'CURRICULUM AND INSTRUCTION',
        'parameters' => [
            'A' => ['name' => 'Curriculum and Program of Studies'],
            'B' => ['name' => 'Instructional Processes, Methodologies and Learning Opportunities'],
            'C' => ['name' => 'Assessment of Academic Performance'],
            'D' => ['name' => 'Management of Learning'],
            'E' => ['name' => 'Graduation Requirements'],
            'F' => ['name' => 'Administrative Support for Effective Instruction']
        ]
    ],
    'Area IV' => [
        'name' => 'SUPPORT TO STUDENTS',
        'parameters' => [
            'A' => ['name' => 'Student Services Program (SSP)'],
            'B' => ['name' => 'Student Welfare'],
            'C' => ['name' => 'Student Development'],
            'D' => ['name' => 'Institutional Student Programs and Services'],
            'E' => ['name' => 'Research, Monitoring and Evaluation']
        ]
    ],
    'Area V' => [
        'name' => 'RESEARCH',
        'parameters' => [
            'A' => ['name' => 'Priorities and Relevance'],
            'B' => ['name' => 'Funding and Other Resources'],
            'C' => ['name' => 'Implementation, Monitoring Evaluation and Utilization of Research Results/Outputs'],
            'D' => ['name' => 'Publication and Dissemination']
        ]
    ],
    'Area VI' => [
        'name' => 'EXTENSION AND COMMUNITY INVOLVEMENT',
        'parameters' => [
            'A' => ['name' => 'Priorities and Relevance'],
            'B' => ['name' => 'Planning, Implementation, Monitoring and Evaluation'],
            'C' => ['name' => 'Funding and Other Resources'],
            'D' => ['name' => 'Community Involvement and Participation']
        ]
    ],
    'Area VII' => [
        'name' => 'LIBRARY',
        'parameters' => [
            'A' => ['name' => 'Administration'],
            'B' => ['name' => 'Personnel'],
            'C' => ['name' => 'Collection Development, Organization and Preservation'],
            'D' => ['name' => 'Services and Utilization'],
            'E' => ['name' => 'Physical Set-up and Facilities'],
            'F' => ['name' => 'Financial Support'],
            'G' => ['name' => 'Linkages']
        ]
    ],
    'Area VIII' => [
        'name' => 'PHYSICAL PLANT AND FACILITIES',
        'parameters' => [
            'A' => ['name' => 'Campus'],
            'B' => ['name' => 'Buildings'],
            'C' => ['name' => 'Classrooms'],
            'D' => ['name' => 'Offices, Staff and Function Rooms'],
            'E' => ['name' => 'Assembly and Athletic Facilities'],
            'F' => ['name' => 'Medical and Dental Clinic'],
            'G' => ['name' => 'Student Center'],
            'H' => ['name' => 'Food Services Unit/Canteen'],
            'I' => ['name' => 'Accreditation Center'],
            'J' => ['name' => 'Housing (Optional)']
        ]
    ],
    'Area IX' => [
        'name' => 'LABORATORIES',
        'parameters' => [
            'A' => ['name' => 'Laboratories, Shops/Facilities'],
            'B' => ['name' => 'Equipment and Supplies'],
            'C' => ['name' => 'Maintenance'],
            'D' => ['name' => 'Special Provisions']
        ]
    ],
    'Area X' => [
        'name' => 'ADMINISTRATION',
        'parameters' => [
            'A' => ['name' => 'Organization'],
            'B' => ['name' => 'Academic Administration'],
            'C' => ['name' => 'Student Administration'],
            'D' => ['name' => 'Financial Management'],
            'E' => ['name' => 'Supply Management'],
            'F' => ['name' => 'Records Management'],
            'G' => ['name' => 'Institutional Planning and Development'],
            'H' => ['name' => 'Performance of Administrative Personnel']
        ]
    ]
];

function getDynamicRequirementCount($db, $areaNumber, $paramLetter, $indicatorType) {
    // Convert area name to number (e.g., "Area I" -> 1, "Area X" -> 10)
    $romanNumerals = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 
                      'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10];
    $parts = explode(' ', $areaNumber);
    $numericArea = $romanNumerals[$parts[1]] ?? 0;
    
    // Map indicator type to code
    $indicatorCode = '';
    if (strpos($indicatorType, 'System') !== false) $indicatorCode = 'S';
    else if (strpos($indicatorType, 'Implementation') !== false) $indicatorCode = 'I';
    else if (strpos($indicatorType, 'Outcome') !== false) $indicatorCode = 'O';
    
    $query = "SELECT COUNT(gr.id) as total 
              FROM global_requirements gr
              JOIN global_accreditation_tags gat ON gr.global_folder_id = gat.id
              WHERE gat.area_number = ? AND gat.parameter_letter = ? AND gat.indicator_type = ?
              AND gat.is_active = TRUE AND gr.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute([$numericArea, $paramLetter, $indicatorCode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ?? 0;
}

try {
    $db->beginTransaction();
    
    // Create areas and their structure
    foreach ($areaStructure as $areaNumber => $areaData) {
        // Insert area
        $query = "INSERT INTO areas (level_id, name, area_number) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$level_id, $areaData['name'], $areaNumber]);
        $area_id = $db->lastInsertId();
        
        // Create parameters for this area
        foreach ($areaData['parameters'] as $paramOrder => $paramData) {
            $query = "INSERT INTO parameters (area_id, name, parameter_letter) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$area_id, $paramData['name'], $paramOrder]);
            $parameter_id = $db->lastInsertId();
            
            // Create indicators for this parameter
            $indicatorTypes = [
                'S' => 'System – Inputs and Processes',
                'I' => 'Implementation',
                'O' => 'Outcome/s'
            ];
            
            foreach ($indicatorTypes as $key => $indicatorType) {
                $requiredDocs = getDynamicRequirementCount($db, $areaNumber, $paramOrder, $indicatorType);
                
                $query = "INSERT INTO indicators (parameter_id, name, indicator_type, required_documents) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$parameter_id, $indicatorType, $key, $requiredDocs]);
            }
        }
    }
    
    // The system now directly references global_accreditation_tags and global_requirements
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Level structure created successfully with all 10 areas']);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to create structure: ' . $e->getMessage()]);
}
?>
