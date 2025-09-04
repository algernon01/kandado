<?php
session_start();
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];

    if (empty($email) || empty($password)) {
        $errors[] = "Email and password are required.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Email not found
            $_SESSION['login_error'] = "Email is not registered.";
            header("Location: ../public/login.php");
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            // Wrong password
            $_SESSION['login_error'] = "Invalid password.";
            header("Location: ../public/login.php");
            exit;
        }

        // Check if user is verified
        if (!empty($user['verification_token'])) {
            $_SESSION['login_error'] = "Please verify your email first.";
            header("Location: ../public/login.php");
            exit;
        }

        // Success
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_image'] = $user['profile_image'] ?? '';

        if ($user['role'] === 'admin') {
            header("Location: ../public/admin/dashboard.php");
        } else {
            header("Location: ../public/user/dashboard.php");
        }
        exit;
    } else {
        $_SESSION['login_error'] = implode("<br>", $errors);
        header("Location: ../public/login.php");
        exit;
    }
}
?>
