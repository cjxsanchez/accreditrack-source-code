<?php
/**
 * AccrediTrack Configuration File
 * Central configuration for all constants, database, and helper functions
 */

// Prevent multiple includes
if (defined('APP_NAME')) {
    return;
}

// Load environment variables from .env file
function loadEnvFile() {
    $envFile = dirname(__DIR__) . '/.env';
    
    if (!file_exists($envFile)) {
        $envFile = dirname(__DIR__) . '/.env.example';
    }

    if (!file_exists($envFile)) {
        // Warning only, do not die in production if configured via server env vars
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (empty(trim($line))) continue;
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

loadEnvFile();

// ... [EXISTING CONSTANTS REMAIN UNCHANGED - APP, DB, PATHS, FILE UPLOAD, SECURITY, SUPABASE] ...
// (Retaining all existing code up to the end of the file provided in context)

define('APP_NAME', getenv('APP_NAME') ?: 'AccrediTrack');
define('SITE_NAME', 'AccrediTrack System');
define('SITE_URL', 'http://localhost:8000/');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'accreditrack');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', intval(getenv('DB_PORT') ?: 3306));

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

define('UPLOAD_PATH', STORAGE_PATH . '/uploads/');
define('PROFILE_PATH', STORAGE_PATH . '/profiles/');
define('REFERENCE_PATH', STORAGE_PATH . '/references/');
define('TEMP_PATH', STORAGE_PATH . '/temp/');
define('LOG_PATH', STORAGE_PATH . '/logs/');

$requiredDirs = [STORAGE_PATH, UPLOAD_PATH, PROFILE_PATH, REFERENCE_PATH, TEMP_PATH, LOG_PATH];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg', 'image/png', 'image/gif', 'application/zip', 'application/x-rar-compressed'
]);

define('SESSION_TIMEOUT', 3600);
define('CSRF_TOKEN_EXPIRY', 7200);
define('PASSWORD_RESET_TOKEN_EXPIRY', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_TIMEOUT', 900);

define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('SUPABASE_BUCKET', getenv('SUPABASE_BUCKET') ?: 'accredidocs');

function isSupabaseConfigured() {
    return !empty(SUPABASE_URL) && !empty(SUPABASE_SERVICE_KEY) && !empty(SUPABASE_BUCKET);
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

require_once CONFIG_PATH . '/database.php';
$GLOBALS['_database'] = null;
function getDB() {
    if ($GLOBALS['_database'] === null) $GLOBALS['_database'] = new Database();
    return $GLOBALS['_database'];
}

function isLoggedIn() { return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); }
function isAdmin() { return isLoggedIn() && $_SESSION['role'] === 'admin'; }
function isStaff() { return isLoggedIn() && $_SESSION['role'] === 'staff'; }
function requireLogin() { if (!isLoggedIn()) redirect('index.php'); }
function requireAdmin() { requireLogin(); if (!isAdmin()) die('Access Denied: Admin access required'); }
function requireStaff() { requireLogin(); if (!isStaff()) die('Access Denied: Staff access required'); }

function redirect($path, $permanent = false) {
    if (empty($path)) $path = 'index.php';
    if (strpos($path, 'http') === 0) {
        header('Location: ' . $path, true, $permanent ? 301 : 302);
    } else {
        $path = ltrim($path, '/');
        $redirectUrl = SITE_URL . $path;
        header('Location: ' . $redirectUrl, true, $permanent ? 301 : 302);
    }
    exit();
}

function redirectWithMessage($path, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    redirect($path);
}

function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    return ['message' => $message, 'type' => $type];
}

function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function validatePhone($phone) { return preg_match('/^[0-9\-\+\s]+$/', $phone) === 1; }
function hashPassword($password) { return password_hash($password, PASSWORD_DEFAULT); }
function verifyPassword($password, $hash) { return password_verify($password, $hash); }
function generateToken($length = 32) { return bin2hex(random_bytes($length)); }

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}
function getCSRFToken() { return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">'; }
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) return false;
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION['csrf_token']); unset($_SESSION['csrf_token_time']);
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function jsonResponse($data = [], $code = 200, $message = 'Success') {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => ($code >= 200 && $code < 300), 'code' => $code, 'message' => $message, 'data' => $data, 'timestamp' => date('Y-m-d H:i:s')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

function logMessage($message, $level = 'info', $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    $logLine = "[$timestamp] [$level] $message$contextStr\n";
    $logFile = LOG_PATH . date('Y-m-d') . '.log';
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    if (APP_DEBUG) error_log($logLine);
}

function getAreaStructure() {
    // [Area Structure Array from original file]
    return [
        'I' => ['name' => 'VISION, MISSION, GOALS AND OBJECTIVES', 'parameters' => ['A' => 'Statement of Vision, Mission, Goals and Objectives', 'B' => 'Dissemination and Acceptability']],
        'II' => ['name' => 'FACULTY', 'parameters' => ['A' => 'Academic Qualifications and Professional Experience', 'B' => 'Recruitment, Selection and Orientation', 'C' => 'Faculty Adequacy and Loading', 'D' => 'Rank and Tenure', 'E' => 'Faculty Development', 'F' => 'Professional Performance and Scholarly Works', 'G' => 'Salaries, Fringe Benefits and Incentives', 'H' => 'Professionalism']],
        'III' => ['name' => 'CURRICULUM AND INSTRUCTION', 'parameters' => ['A' => 'Curriculum and Program of Studies', 'B' => 'Instructional Processes, Methodologies and Learning Opportunities', 'C' => 'Assessment of Academic Performance', 'D' => 'Management of Learning', 'E' => 'Graduation Requirements', 'F' => 'Administrative Support for Effective Instruction']],
        'IV' => ['name' => 'SUPPORT TO STUDENTS', 'parameters' => ['A' => 'Student Services Program (SSP)', 'B' => 'Student Welfare', 'C' => 'Student Development', 'D' => 'Institutional Student Programs and Services', 'E' => 'Research, Monitoring and Evaluation']],
        'V' => ['name' => 'RESEARCH', 'parameters' => ['A' => 'Priorities and Relevance', 'B' => 'Funding and Other Resources', 'C' => 'Implementation, Monitoring Evaluation and Utilization of Research Results/Outputs', 'D' => 'Publication and Dissemination']],
        'VI' => ['name' => 'EXTENSION AND COMMUNITY INVOLVEMENT', 'parameters' => ['A' => 'Priorities and Relevance', 'B' => 'Planning, Implementation, Monitoring and Evaluation', 'C' => 'Funding and Other Resources', 'D' => 'Community Involvement and Participation in the Institution\'s Activities']],
        'VII' => ['name' => 'LIBRARY', 'parameters' => ['A' => 'Administration', 'B' => 'Personnel', 'C' => 'Collection Development, Organization and Preservation', 'D' => 'Services and Utilization', 'E' => 'Physical Set-up and Facilities', 'F' => 'Financial Support', 'G' => 'Linkages']],
        'VIII' => ['name' => 'PHYSICAL PLANT AND FACILITIES', 'parameters' => ['A' => 'Campus', 'B' => 'Buildings', 'C' => 'Classrooms', 'D' => 'Offices, Staff and Function Rooms', 'E' => 'Assembly and Athletic Facilities', 'F' => 'Medical and Dental Clinic', 'G' => 'Student Center', 'H' => 'Food Services Unit/Canteen', 'I' => 'Accreditation Center', 'J' => 'Housing (Optional)']],
        'IX' => ['name' => 'LABORATORIES', 'parameters' => ['A' => 'Laboratories, Shops/Facilities', 'B' => 'Equipment and Supplies', 'C' => 'Maintenance', 'D' => 'Special Provisions']],
        'X' => ['name' => 'ADMINISTRATION', 'parameters' => ['A' => 'Organization', 'B' => 'Academic Administration', 'C' => 'Student Administration', 'D' => 'Financial Management', 'E' => 'Supply Management', 'F' => 'Records Management', 'G' => 'Institutional Planning and Development', 'H' => 'Performance of Administrative Personnel']]
    ];
}

function getDocumentRequirements() {
    $db = getDB();
    $counts = [];
    $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
    foreach ($romanNumerals as $roman) $counts[$roman] = [];
    
    $query = "SELECT gat.area_number, gat.parameter_letter, gat.indicator_type, COUNT(gr.id) as requirement_count
              FROM global_accreditation_tags gat
              LEFT JOIN global_requirements gr ON gr.global_folder_id = gat.id AND gr.is_active = TRUE
              WHERE gat.is_active = TRUE
              GROUP BY gat.area_number, gat.parameter_letter, gat.indicator_type";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $areaNum = (int)$row['area_number'];
        if ($areaNum >= 1 && $areaNum <= 10) {
            $areaRoman = $romanNumerals[$areaNum - 1];
            $paramLetter = $row['parameter_letter'];
            $indicatorType = $row['indicator_type'];
            if (!isset($counts[$areaRoman][$paramLetter])) $counts[$areaRoman][$paramLetter] = ['S' => 0, 'I' => 0, 'O' => 0];
            $counts[$areaRoman][$paramLetter][$indicatorType] = (int)$row['requirement_count'];
        }
    }
    
    $areaStructure = getAreaStructure();
    foreach ($areaStructure as $areaRoman => $areaData) {
        foreach ($areaData['parameters'] as $paramLetter => $paramName) {
            if (!isset($counts[$areaRoman][$paramLetter])) $counts[$areaRoman][$paramLetter] = ['S' => 0, 'I' => 0, 'O' => 0];
        }
    }
    return $counts;
}

// ============================================================================
// EMAIL CONFIGURATION
// ============================================================================

// define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
// define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
// define('SMTP_USER', getenv('SMTP_USER') ?: 'johncarlsanchez06@gmail.com');
// define('SMTP_PASS', getenv('SMTP_PASS') ?: 'edeu wcpe exyk ncfg'); // Gmail App Password
// define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: SMTP_USER);
// define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: APP_NAME . ' Notification');

// ... bottom of config/config.php ...

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'johncarlsanchez06@gmail.com'); // <--- PUT YOUR EMAIL HERE
define('SMTP_PASS', 'edeu wcpe exyk ncfg');        // <--- PUT YOUR 16-CHAR APP PASSWORD HERE
define('SMTP_FROM_EMAIL', 'no-reply@accreditrack.com');
define('SMTP_FROM_NAME', 'AccrediTrack System');

?>