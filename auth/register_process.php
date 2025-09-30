<?php
declare(strict_types=1);

require_once '../config/db.php';
require '../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/kandado/lib/email_lib.php';

date_default_timezone_set('Asia/Manila'); // keep app-local time consistent with PH
session_start();

$errors = [];

// === Config ===
const VERIFICATION_TTL_ISO8601 = 'PT1H'; // <-- change this to adjust the window (e.g., PT30M, PT24H)
const NGROK_BASE = 'https://longhorn-settling-precisely.ngrok-free.app';
const LAN_BASE   = 'http://192.168.207.238'; // switch to https:// if you have a valid cert

/**
 * Determine if an IP is private (RFC1918, loopback, link-local).
 */
function is_private_ip(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/**
 * Decide whether this request is from LAN based on host header and client IPs.
 */
function request_is_lan(): bool {
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $hostOnly = preg_replace('/:\d+$/', '', strtolower($hostHeader ?? ''));

    // If host is an IP, check if it is private; also allow localhost.
    if ($hostOnly === 'localhost' || $hostOnly === '127.0.0.1') return true;
    if (filter_var($hostOnly, FILTER_VALIDATE_IP) && is_private_ip($hostOnly)) return true;

    // Fall back to client IP check
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $firstForwarded = '';
    if ($xff !== '') {
        $parts = explode(',', $xff);
        $firstForwarded = trim($parts[0] ?? '');
    }
    $clientIp = $firstForwarded ?: ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($clientIp && is_private_ip($clientIp)) return true;

    return false;
}

if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST") {
    $firstName = trim($_POST["first_name"] ?? '');
    $lastName  = trim($_POST["last_name"] ?? '');
    $email     = trim($_POST["email"] ?? '');
    $password  = $_POST["password"] ?? '';
    $confirmPassword = $_POST["confirm_password"] ?? '';

    // Keep non-sensitive inputs so the form stays filled on error
    $oldInput = [
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
    ];

    // === Required Fields ===
    if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $errors[] = "All fields are required.";
    }

    // === Email Validation ===
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if domain has MX records
        $domain = substr(strrchr($email, "@"), 1);
        if ($domain === false || !checkdnsrr($domain, "MX")) {
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

    try {
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

            // Expiry in 1 hour, explicitly using PH time (Asia/Manila)
            $tzManila  = new DateTimeZone('Asia/Manila');
            $expiresAt = (new DateTimeImmutable('now', $tzManila))
                ->add(new DateInterval(VERIFICATION_TTL_ISO8601))
                ->format('Y-m-d H:i:s'); // stored as naive local PH time

            // Insert user with token + expiry
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    first_name, last_name, email, password, role,
                    verification_token, verification_expires_at
                )
                VALUES (?, ?, ?, ?, 'user', ?, ?)
            ");
            $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $token, $expiresAt]);

            // === Dynamic link selection ===
            $useLan = request_is_lan();
            $primaryBase = $useLan ? LAN_BASE : NGROK_BASE;
            $altBase     = $useLan ? NGROK_BASE : LAN_BASE;

            $tokenParam = rawurlencode($token);
            $verificationLinkPrimary = rtrim($primaryBase, '/') . '/kandado/auth/verify_process.php?token=' . $tokenParam;
            $verificationLinkAlt     = rtrim($altBase,   '/') . '/kandado/auth/verify_process.php?token=' . $tokenParam;

            // === Send Verification Email (supports helper with optional alt link) ===
            $emailSent = false;
            try {
                // Preferred: helper supports a 5th parameter for an alternate link
                $emailSent = email_verify_account(
                    $email,
                    $firstName,
                    $lastName,
                    $verificationLinkPrimary,
                    $verificationLinkAlt // optional (if your email_lib supports it)
                );
            } catch (ArgumentCountError $e) {
                // Fallback if helper only accepts 4 params
                if (function_exists('email_verify_account_multi')) {
                    // Optional alternate helper that accepts an array of links
                    $emailSent = email_verify_account_multi(
                        $email,
                        $firstName,
                        $lastName,
                        [$verificationLinkPrimary, $verificationLinkAlt]
                    );
                } else {
                    // Last resort: send with primary link only (still works)
                    $emailSent = email_verify_account(
                        $email,
                        $firstName,
                        $lastName,
                        $verificationLinkPrimary
                    );
                }
            }

            if ($emailSent) {
                // Clear any old input on success
                unset($_SESSION['register_old'], $_SESSION['register_errors']);
                $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account. The link expires in 1 hour.";
                header("Location: ../public/verify.php");
                exit;
            } else {
                // If sending fails, surface error and keep old input
                $errors[] = "Email could not be sent. Please try again.";
            }
        }
    } catch (Throwable $t) {
        // Generic safe error; avoid leaking internal details
        $errors[] = "Something went wrong while creating your account. Please try again.";
        // Optional: log $t->getMessage() to your server logs
        // error_log($t);
    }

    // Store errors + old input in session and redirect back
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old']    = $oldInput;

    header("Location: ../public/register.php");
    exit;
}

// If not POST, optionally redirect to register page
header("Location: ../public/register.php");
exit;
