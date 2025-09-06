<?php
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Manila'); // ensure PHP's date()/header times are PH time

$message = '';
$status  = '';

try {
    $token = filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW) ?? '';

    if ($token !== '') {
        // Fetch token + its expiry
        $stmt = $pdo->prepare('SELECT id, verification_expires_at FROM users WHERE verification_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $tzManila  = new DateTimeZone('Asia/Manila');
            $now       = new DateTimeImmutable('now', $tzManila);

            // Treat stored DATETIME as PH local time
            $expiresAt = !empty($user['verification_expires_at'])
                ? new DateTimeImmutable($user['verification_expires_at'], $tzManila)
                : null;

            if ($expiresAt !== null && $expiresAt < $now) {
                // Expired: clear token immediately
                $clear = $pdo->prepare('UPDATE users SET verification_token = NULL, verification_expires_at = NULL WHERE id = ?');
                $clear->execute([$user['id']]);

                $message = 'Your verification link has expired. Please request a new one.';
                $status  = 'error';
            } else {
                // Valid: clear token and confirm
                $clear = $pdo->prepare('UPDATE users SET verification_token = NULL, verification_expires_at = NULL WHERE id = ?');
                $clear->execute([$user['id']]);

                $message = 'Your email has been successfully verified!';
                $status  = 'success';
            }
        } else {
            $message = 'Invalid or expired verification token.';
            $status  = 'error';
        }
    } else {
        $message = 'No verification token provided.';
        $status  = 'error';
    }
} catch (Throwable $e) {
    // Optionally log $e->getMessage()
    $message = 'Something went wrong while verifying your email.';
    $status  = 'error';
}

$location = sprintf(
    '../public/verify_confirm.php?status=%s&msg=%s',
    rawurlencode($status),
    rawurlencode($message)
);
header('Location: ' . $location, true, 303);
exit;
