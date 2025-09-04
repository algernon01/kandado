<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    // Generate reset token and expiration
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Update user record
    $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $update->execute([$token, $expires, $email]);

    // Send reset email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lockerkandado01@gmail.com';
        $mail->Password = 'xgzhnjxyapnphnco'; // Use App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('lockerkandado01@gmail.com', 'Kandado');
        $mail->addAddress($email, $user['first_name']);

        $resetLink = "https://longhorn-settling-precisely.ngrok-free.app/kandado/auth/reset_password.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Reset your Kandado Password';
        $mail->Body = "
          <div style='font-family: Arial, sans-serif; background-color: #f8f8f8; padding: 30px;'>
            <h2 style='color: #333;'>Password Reset Request</h2>
            <p style='font-size: 15px;'>Hi {$user['first_name']},</p>
            <p style='font-size: 15px;'>We received a request to reset your Kandado password. Click the button below to proceed. This link will expire in 30 minutes.</p>
            <a href='$resetLink' style='
              display: inline-block;
              background-color: #3353bb;
              color: #fff;
              padding: 12px 20px;
              border-radius: 5px;
              text-decoration: none;
              font-weight: bold;
              margin-top: 10px;
            '>Reset Password</a>
            <p style='font-size: 12px; color: #999; margin-top: 30px;'>If you didn't request this, you can safely ignore this email.</p>
            <hr style='margin-top: 30px;'>
            <p style='font-size: 12px; color: #aaa;'>Kandado Support</p>
          </div>
        ";

        $mail->send();

        // Success message (plain, not styled HTML)
        $_SESSION['forgot_success'] = 'Check your email for the password reset link.';
    } catch (Exception $e) {
        $_SESSION['forgot_error'] = 'Error sending email: ' . htmlspecialchars($mail->ErrorInfo);
    }

    header('Location: ../public/forgot_success.php');
    exit;
}
