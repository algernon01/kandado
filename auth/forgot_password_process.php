<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/kandado/lib/email_lib.php';

// Ensure all date()/strtotime() use PH time
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['forgot_error'] = 'Invalid email address.';
        header('Location: ../public/forgot_password.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['forgot_error'] = 'No account found with that email.';
        header('Location: ../public/forgot_password.php');
        exit;
    }

    // Generate reset token and PH-time expiration (30 minutes from now)
    $token = bin2hex(random_bytes(32));
    $tzManila = new DateTimeZone('Asia/Manila');
    $expires  = (new DateTimeImmutable('now', $tzManila))
                    ->add(new DateInterval('PT30M'))
                    ->format('Y-m-d H:i:s'); // store as naive PH local time

    // Update user record
    $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $update->execute([$token, $expires, $email]);

    // Send reset email via centralized helper (same design/text)
    $resetLink = "https://192.168.1.104/kandado/auth/reset_password.php?token=" . rawurlencode($token);

    if (email_reset_password($email, $user['first_name'], $resetLink)) {
        $_SESSION['forgot_success'] = 'Check your email for the password reset link.';
    } else {
        $_SESSION['forgot_error'] = 'Error sending email. Please try again.';
    }

    header('Location: ../public/forgot_success.php');
    exit;
}

//longhorn-settling-precisely.ngrok-free.app
