<?php
require_once '../config/db.php';
require '../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/kandado/lib/email_lib.php';

date_default_timezone_set('Asia/Manila'); // ensure all date()/strtotime() use PH time

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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already registered.";
        }
    }

    // === Proceed if no errors ===
    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Expiry in 30 minutes, explicitly using PH time (Asia/Manila)
        $tzManila  = new DateTimeZone('Asia/Manila');
        $expiresAt = (new DateTimeImmutable('now', $tzManila))
                        ->add(new DateInterval('PT30M'))
                        ->format('Y-m-d H:i:s'); // stored as naive local PH time

        // Insert user with token + expiry
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, verification_token, verification_expires_at)
            VALUES (?, ?, ?, ?, 'user', ?, ?)
        ");
        $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $token, $expiresAt]);

        // === Send Verification Email (via centralized helper) ===
        $verificationLink = "https://192.168.1.104/kandado/auth/verify_process.php?token=" . rawurlencode($token);

        if (email_verify_account($email, $firstName, $lastName, $verificationLink)) {
            $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account. The link expires in 30 minutes.";
            header("Location: ../public/verify.php");
            exit;
        } else {
            $errors[] = "Email could not be sent. Please try again.";
        }
    }

    // Store errors in session and redirect back
    $_SESSION['register_errors'] = $errors;
    header("Location: ../public/register.php");
    exit;
}
