<?php
// verify.php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// Use Manila for app logic / messages
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
        $stmt = $pdo->prepare(
            'SELECT id, verification_expires_at
               FROM users
              WHERE verification_token = ?
              LIMIT 1'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'Invalid or expired verification token.';
            $status  = 'error';
        } else {
            $tzManila  = new DateTimeZone('Asia/Manila');
            $now       = new DateTimeImmutable('now', $tzManila);
            $expiresAt = !empty($user['verification_expires_at'])
                ? new DateTimeImmutable($user['verification_expires_at'], $tzManila)
                : null;

            if ($expiresAt !== null && $expiresAt < $now) {
                // ----- EXPIRED FLOW -----
                // Release lockers and delete the still-unverified user
                $pdo->beginTransaction();

                // Release any lockers this user might hold
                $release = $pdo->prepare(
                    'UPDATE locker_qr
                        SET user_id = NULL,
                            code = NULL,
                            status = "available",
                            expires_at = NULL,
                            duration_minutes = NULL
                      WHERE user_id = ?'
                );
                $release->execute([$user['id']]);

                // Delete user only if still unverified
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
                // ----- VALID FLOW -----
                // Clear token and give a one-time ₱5 welcome bonus safely
                $pdo->beginTransaction();

                // 1) Mark verified
                $clear = $pdo->prepare(
                    'UPDATE users
                        SET verification_token = NULL,
                            verification_expires_at = NULL
                      WHERE id = ?'
                );
                $clear->execute([$user['id']]);

                // 2) Ensure the wallet row exists
                $initWallet = $pdo->prepare(
                    'INSERT INTO user_wallets (user_id, balance)
                          VALUES (?, 0.00)
                     ON DUPLICATE KEY UPDATE user_id = user_id'
                );
                $initWallet->execute([$user['id']]);

                // 3) Credit one-time welcome bonus using a unique reference
                $ref  = 'WELCOMEBONUS-' . $user['id'];
                $meta = json_encode([
                    'reason'   => 'welcome_bonus',
                    'source'   => 'email_verification',
                    'amount'   => 5.00,
                    'currency' => 'PHP',
                    'awarded_at_tz' => 'Asia/Manila',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                try {
                    // Insert a unique transaction; if repeated, this throws due to UNIQUE constraints
                    $tx = $pdo->prepare(
                        'INSERT INTO wallet_transactions (user_id, type, method, amount, reference_no, notes, meta)
                              VALUES (?, "topup", "Admin", 5.00, ?, "Welcome bonus on verification", ?)'
                    );
                    $tx->execute([$user['id'], $ref, $meta]);

                    // Only update balance if the insert above succeeded
                    $upd = $pdo->prepare(
                        'UPDATE user_wallets
                            SET balance = balance + 5.00
                          WHERE user_id = ?'
                    );
                    $upd->execute([$user['id']]);
                } catch (Throwable $dup) {
                    // If the reference already exists, the bonus was already granted on a prior attempt.
                    // No balance change; keep things idempotent.
                    // Optionally log $dup->getMessage()
                }

                $pdo->commit();

                $message = 'Your email has been successfully verified! A ₱5 welcome bonus was added to your wallet.';
                $status  = 'success';
            }
        }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In production, log the error: error_log($e->getMessage());
    $message = 'Something went wrong while verifying your email.';
    $status  = 'error';
}

// Redirect to confirmation page
$location = sprintf(
    '../public/verify_confirm.php?status=%s&msg=%s',
    rawurlencode($status),
    rawurlencode($message)
);
header('Location: ' . $location, true, 303);
exit;
