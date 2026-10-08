<?php
/**
 * Login Process Handler
 */

require_once dirname(__DIR__) . '/config/config.php';

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('index.php', 'Security token invalid. Please try again.', 'danger');
}

// Get form data
$username = sanitize($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    redirectWithMessage('index.php', 'Username and password are required.', 'warning');
}

// Attempt login
$auth = new Auth();
$user = $auth->login($username, $password);

if ($user) {
    // Redirect to appropriate dashboard
    if ($user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('staff/dashboard.php');
    }
} else {
    redirectWithMessage('index.php', 'Invalid username or password.', 'danger');
}

?>
