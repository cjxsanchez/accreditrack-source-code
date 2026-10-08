<?php
// includes/mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ============================================================================
// PATH CORRECTION LOGIC (Based on your Screenshot)
// ============================================================================
// We are in /includes/mailer.php
// We need to go up one level (..) to reach Root
// Then go into /PHPMailer/ to find the files

$rootPath = dirname(__DIR__); // Gets the parent directory (Root)

// Define the exact paths to the files seen in your screenshot
$phpMailerPath = $rootPath . '/PHPMailer/PHPMailer.php';
$smtpPath      = $rootPath . '/PHPMailer/SMTP.php';
$exceptionPath = $rootPath . '/PHPMailer/Exception.php';

// Check if files exist before requiring to prevent crashing if moved
if (file_exists($phpMailerPath)) {
    require_once $exceptionPath;
    require_once $phpMailerPath;
    require_once $smtpPath;
} else {
    // Fallback: If you move PHPMailer inside 'includes' or 'lib' later
    error_log("PHPMailer not found at $phpMailerPath");
}

/**
 * Send a system email using PHPMailer
 * * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML content
 * @return bool True on success, False on failure
 */
function sendSystemEmail($toEmail, $toName, $subject, $htmlBody) {
    // 1. Verify Configuration exists
    if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        error_log("Email Error: SMTP constants not defined in config.php");
        return false;
    }

    // 2. Verify PHPMailer class is loaded
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("Email Error: PHPMailer class not found. Check file paths.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed to $toEmail. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>