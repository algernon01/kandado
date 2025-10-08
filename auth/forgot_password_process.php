<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/kandado/lib/email_lib.php';

// Ensure all date()/strtotime() use PH time
date_default_timezone_set('Asia/Manila');

// --- toggle this if you want explicit errors on "email not found" etc.
const NO_ENUMERATION = true;

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
    $hostOnly = preg_replace('/:\d+$/', '', strtolower($hostHeader));
    if ($hostOnly === 'localhost' || $hostOnly === '127.0.0.1') return true;
    if (filter_var($hostOnly, FILTER_VALIDATE_IP) && is_private_ip($hostOnly)) return true;

    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $clientIp = trim(explode(',', $xff)[0] ?? '') ?: ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($clientIp && is_private_ip($clientIp)) return true;

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // If you want strict format error visible, set NO_ENUMERATION=false.
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (NO_ENUMERATION) {
            $_SESSION['forgot_success'] = 'If that email is registered, we\'ve sent a password reset link. It will expire in 30 minutes.';
            header('Location: ../public/forgot_success.php');
            exit;
        } else {
            $_SESSION['forgot_error'] = 'Invalid email address.';
            header('Location: ../public/forgot_password.php');
            exit;
        }
    }

    // Always compute these so timing looks similar even if user not found
    $tzManila   = new DateTimeZone('Asia/Manila');
    $nowPH      = new DateTimeImmutable('now', $tzManila);
    $defaultExp = $nowPH->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');
    $token      = bin2hex(random_bytes(32));
    $expires    = $defaultExp;

    // Look up user + existing token
    $stmt = $pdo->prepare("SELECT id, first_name, reset_token, reset_token_expires FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Reuse an unexpired token to reduce spam / churn
        $hasExisting = !empty($user['reset_token']) && !empty($user['reset_token_expires']);
        if ($hasExisting && strtotime($user['reset_token_expires']) > $nowPH->getTimestamp()) {
            $token   = $user['reset_token'];
            $expires = $user['reset_token_expires'];
        } else {
            $token   = bin2hex(random_bytes(32));
            $expires = $defaultExp;
            $update  = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $update->execute([$token, $expires, $user['id']]);
        }

        // === Dynamic link selection ===
        $NGROK_BASE = '``https://longhorn-settling-precisely.ngrok-free.app``';
        $LAN_BASE   = 'http://192.168.14.238'; // switch to https:// if you have a valid cert

        $useLan     = request_is_lan();
        $primaryBase= $useLan ? $LAN_BASE : $NGROK_BASE;
        $altBase    = $useLan ? $NGROK_BASE : $LAN_BASE;

        $tokenParam = rawurlencode($token);
        $resetLinkPrimary = rtrim($primaryBase, '/') . '/kandado/auth/reset_password.php?token=' . $tokenParam;
        $resetLinkAlt     = rtrim($altBase,   '/') . '/kandado/auth/reset_password.php?token=' . $tokenParam;

        // === Send reset email (supports helper with optional alt link) ===
        try {
            $emailSent = false;
            try {
                // Preferred: helper supports a 4th parameter for an alternate link
                $emailSent = email_reset_password(
                    $email,
                    $user['first_name'],
                    $resetLinkPrimary,
                    $resetLinkAlt
                );
            } catch (ArgumentCountError $e) {
                if (function_exists('email_reset_password_multi')) {
                    $emailSent = email_reset_password_multi(
                        $email,
                        $user['first_name'],
                        [$resetLinkPrimary, $resetLinkAlt]
                    );
                } else {
                    $emailSent = email_reset_password(
                        $email,
                        $user['first_name'],
                        $resetLinkPrimary
                    );
                }
            }

            // Even if email fails, DO NOT reveal it to the user if NO_ENUMERATION=true
            if (!$emailSent && !NO_ENUMERATION) {
                $_SESSION['forgot_error'] = 'Error sending email. Please try again.';
                header('Location: ../public/forgot_password.php');
                exit;
            }
        } catch (Throwable $t) {
            // Swallow or log; keep response consistent if no-enum
            if (!NO_ENUMERATION) {
                $_SESSION['forgot_error'] = 'Unexpected error occurred. Please try again.';
                header('Location: ../public/forgot_password.php');
                exit;
            }
        }
    } else {
        // If user not found: keep timing similar; optionally sleep a bit
        // usleep(150000); // 150ms
        if (!NO_ENUMERATION) {
            $_SESSION['forgot_error'] = 'No account found with that email.';
            header('Location: ../public/forgot_password.php');
            exit;
        }
    }

    // Success path (generic if NO_ENUMERATION)
    $_SESSION['forgot_success'] = 'If that email is registered, we\'ve sent a password reset link. It will expire in 30 minutes.';
    header('Location: ../public/forgot_success.php');
    exit;
}
