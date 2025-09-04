<?php
require_once '../config/db.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST["first_name"]);
    $lastName = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];

    // === Required Fields ===
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $errors[] = "All fields are required.";
    }

    // === Email Validation ===
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if domain has MX records
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            $errors[] = "Email domain does not exist or cannot receive emails.";
        }
    }

    // === Password Validation ===
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        $errors[] = "Password must include at least one special character.";
    }

    // === Password Strength Check ===
    $strengthScore = 0;
    if (preg_match('/[a-z]/', $password)) $strengthScore++;
    if (preg_match('/[A-Z]/', $password)) $strengthScore++;
    if (preg_match('/[0-9]/', $password)) $strengthScore++;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strengthScore++;
    if ($strengthScore < 2) {
        $errors[] = "Password is too weak.";
    }

    // === Check if email already exists ===
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already registered.";
        }
    }

    // === Proceed if no errors ===
    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, verification_token) VALUES (?, ?, ?, ?, 'user', ?)");
        $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $token]);

        // === Send Verification Email ===
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'lockerkandado01@gmail.com';
            $mail->Password = 'xgzhnjxyapnphnco';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('lockerkandado01@gmail.com', 'Kandado'); 
            $mail->addAddress($email, "$firstName $lastName");

            $mail->isHTML(true);
            $mail->Subject = 'Verify your Kandado account';
            $verificationLink = "https://longhorn-settling-precisely.ngrok-free.app/kandado/auth/verify_process.php?token=$token";

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;'>
                  <h2 style='color: #333;'>Welcome to Kandado</h2>
                  <p style='font-size: 16px; color: #555;'>Thank you for registering. Please verify your email address by clicking the button below:</p>
                  <a href='$verificationLink' style='
                    display: inline-block;
                    background-color: #3353bb;
                    color: #fff;
                    padding: 12px 24px;
                    margin-top: 20px;
                    font-size: 16px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: bold;
                  '>Verify Email</a>
                  <p style='font-size: 14px; color: #888; margin-top: 30px;'>If you did not register, you can safely ignore this email.</p>
                </div>
            ";

            $mail->send();
            $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account.";
            header("Location: ../public/verify.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Email could not be sent. Error: " . $mail->ErrorInfo;
        }
    }

    // Store errors in session and redirect back
    $_SESSION['register_errors'] = $errors;
    header("Location: ../public/register.php");
    exit;
}
