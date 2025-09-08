<?php
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Manila');

$message = '';
$status  = '';

try {
    $token = filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW) ?? '';

    if ($token === '') {
        $message = 'No verification token provided.';
        $status  = 'error';
    } else {
        // Find user by token
        $stmt = $pdo->prepare('SELECT id, verification_expires_at FROM users WHERE verification_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'Invalid or expired verification token.';
            $status  = 'error';
        } else {
            $tzManila = new DateTimeZone('Asia/Manila');
            $now      = new DateTimeImmutable('now', $tzManila);
            $expiresAt = !empty($user['verification_expires_at'])
                ? new DateTimeImmutable($user['verification_expires_at'], $tzManila)
                : null;

            if ($expiresAt !== null && $expiresAt < $now) {
                // EXPIRED: release lockers (if any) then delete the user so the email can be reused
                $pdo->beginTransaction();

                // Safe no-op if the user never held a locker
                $release = $pdo->prepare(
                    'UPDATE locker_qr
                     SET user_id = NULL, code = NULL, status = "available",
                         expires_at = NULL, duration_minutes = NULL
                     WHERE user_id = ?'
                );
                $release->execute([$user['id']]);

                // Delete only if still unverified (has a token)
                $del = $pdo->prepare(
                    'DELETE FROM users
                     WHERE id = ?
                       AND verification_token IS NOT NULL
                       AND verification_expires_at IS NOT NULL'
                );
                $del->execute([$user['id']]);

                $pdo->commit();

                $message = 'Your verification link expired, so your pending registration was removed. Please sign up again.';
                $status  = 'error';
            } else {
                // VALID: mark verified by clearing the token+expiry
                $clear = $pdo->prepare(
                    'UPDATE users
                     SET verification_token = NULL, verification_expires_at = NULL
                     WHERE id = ?'
                );
                $clear->execute([$user['id']]);

                $message = 'Your email has been successfully verified!';
                $status  = 'success';
            }
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // log $e->getMessage() in real apps
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
