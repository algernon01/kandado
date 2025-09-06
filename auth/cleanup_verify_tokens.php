<?php
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->prepare(
        'UPDATE users
         SET verification_token = NULL,
             verification_expires_at = NULL
         WHERE verification_expires_at IS NOT NULL
           AND verification_expires_at < NOW()'
    );
    $stmt->execute();
} catch (Throwable $e) {
    // Optionally log error
}
