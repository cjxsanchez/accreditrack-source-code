<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');

try {
    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $json_input = file_get_contents('php://input');
    $input = json_decode($json_input, true);

    if (!$input) {
        // Try POST data
        $program_id = $_POST['program_id'] ?? null;
        $level_type = $_POST['level_type'] ?? null;
    } else {
        $program_id = $input['program_id'] ?? null;
        $level_type = $input['level_type'] ?? null;
    }

    if (!$program_id || !$level_type) {
        throw new Exception('Missing required fields: program_id and level_type');
    }

    $db = getDB();

    // Check if program exists
    $query = "SELECT id FROM programs WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$program_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Program not found');
    }

    // Check if level already exists
    $query = "SELECT COUNT(*) as count FROM levels WHERE program_id = ? AND level_type = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$program_id, $level_type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        throw new Exception('This level already exists for this program');
    }

    // Insert level
    $query = "INSERT INTO levels (program_id, name, level_type) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);

    if (!$stmt->execute([$program_id, $level_type, $level_type])) {
        throw new Exception('Failed to create level');
    }

    $level_id = $db->lastInsertId();

    $areaStructure = [
        1 => [
            'name' => 'Area I: VISION, MISSION, GOALS AND OBJECTIVES',
            'parameters' => [
                'A' => ['name' => 'Statement of Vision, Mission, Goals and Objectives'],
                'B' => ['name' => 'Dissemination and Acceptability']
            ]
        ],
        2 => [
            'name' => 'Area II: FACULTY',
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
        3 => [
            'name' => 'Area III: CURRICULUM AND INSTRUCTION',
            'parameters' => [
                'A' => ['name' => 'Curriculum and Program of Studies'],
                'B' => ['name' => 'Instructional Processes, Methodologies and Learning Opportunities'],
                'C' => ['name' => 'Assessment of Academic Performance'],
                'D' => ['name' => 'Management of Learning'],
                'E' => ['name' => 'Graduation Requirements'],
                'F' => ['name' => 'Administrative Support for Effective Instruction']
            ]
        ],
        4 => [
            'name' => 'Area IV: SUPPORT TO STUDENTS',
            'parameters' => [
                'A' => ['name' => 'Student Services Program (SSP)'],
                'B' => ['name' => 'Student Welfare'],
                'C' => ['name' => 'Student Development'],
                'D' => ['name' => 'Institutional Student Programs and Services'],
                'E' => ['name' => 'Research, Monitoring and Evaluation']
            ]
        ],
        5 => [
            'name' => 'Area V: RESEARCH',
            'parameters' => [
                'A' => ['name' => 'Priorities and Relevance'],
                'B' => ['name' => 'Funding and Other Resources'],
                'C' => ['name' => 'Implementation, Monitoring Evaluation and Utilization of Research Results/Outputs'],
                'D' => ['name' => 'Publication and Dissemination']
            ]
        ],
        6 => [
            'name' => 'Area VI: EXTENSION AND COMMUNITY INVOLVEMENT',
            'parameters' => [
                'A' => ['name' => 'Priorities and Relevance'],
                'B' => ['name' => 'Planning, Implementation, Monitoring and Evaluation'],
                'C' => ['name' => 'Funding and Other Resources'],
                'D' => ['name' => 'Community Involvement and Participation in the Institution\'s Activities']
            ]
        ],
        7 => [
            'name' => 'Area VII: LIBRARY',
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
        8 => [
            'name' => 'Area VIII: PHYSICAL PLANT AND FACILITIES',
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
        9 => [
            'name' => 'Area IX: LABORATORIES',
            'parameters' => [
                'A' => ['name' => 'Laboratories, Shops/Facilities'],
                'B' => ['name' => 'Equipment and Supplies'],
                'C' => ['name' => 'Maintenance'],
                'D' => ['name' => 'Special Provisions']
            ]
        ],
        10 => [
            'name' => 'Area X: ADMINISTRATION',
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

    // Create areas with parameters and indicators
    foreach ($areaStructure as $areaNumber => $areaData) {
        // Insert area
        $query = "INSERT INTO areas (level_id, area_number, name) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$level_id, $areaNumber, $areaData['name']]);
        $area_id = $db->lastInsertId();

        // Create parameters
        if (isset($areaData['parameters']) && is_array($areaData['parameters'])) {
            foreach ($areaData['parameters'] as $paramLetter => $paramData) {
                if (!is_array($paramData)) {
                    continue;
                }

                $paramName = $paramData['name'] ?? '';

                // Insert parameter
                $query = "INSERT INTO parameters (area_id, parameter_letter, name) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$area_id, $paramLetter, $paramName]);
                $parameter_id = $db->lastInsertId();

                // Document counts are now dynamic from global_requirements table
                $indicatorTypes = [
                    'S' => 'System - Inputs and Processes',
                    'I' => 'Implementation',
                    'O' => 'Outcome/s'
                ];

                foreach ($indicatorTypes as $indicatorType => $indicatorName) {
                    // Insert indicator with required_documents = 0 (dynamic count from global_requirements)
                    $query = "INSERT INTO indicators (parameter_id, indicator_type, name, required_documents) VALUES (?, ?, ?, 0)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$parameter_id, $indicatorType, $indicatorName]);
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Level created successfully with all 10 areas',
        'level_id' => $level_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
