<?php
/**
 * Logout Handler
 */
require_once dirname(__DIR__) . '/config/config.php';

// Log the logout action
logMessage("User logged out: {$_SESSION['username']}", 'info', ['user_id' => $_SESSION['user_id'] ?? null]);

// Destroy session
session_destroy();

// Redirect to login page
redirect('index.php');

?>
