<?php
require_once '../config/db.php';

$message = "";
$status = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if the user with this token exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Mark as verified by clearing the token
        $update = $pdo->prepare("UPDATE users SET verification_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);

        $message = "Your email has been successfully verified!";
        $status = "success";
    } else {
        $message = "Invalid or expired verification token.";
        $status = "error";
    }
} else {
    $message = "No verification token provided.";
    $status = "error";
}

// Redirect to verify_confirm.php
header("Location: ../public/verify_confirm.php?status=$status&msg=" . urlencode($message));
exit;
