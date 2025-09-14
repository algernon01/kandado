<?php
session_start();
require_once '../config/db.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $error = 'Missing reset token.';
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Both password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if token is valid
        $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid or expired token.';
        } elseif (!empty($user['reset_token_expires']) && strtotime($user['reset_token_expires']) < time()) {
            $error = 'Reset token has expired.';
        } else {
            // Hash and update the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $update->execute([$hashedPassword, $user['id']]);

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Password Reset Confirmation • Kandado</title>
  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
  <style>
    /* =============== Reset & Tokens =============== */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root{
      --bg: #F4F7FB;
      --bg-accent: radial-gradient(1200px 600px at 20% -10%, #ffffff 0%, #eef3fb 50%, #e8eef8 100%);
      --card: #ffffff;
      --text: #0f172a;
      --muted: #64748b;

      --action: #2563EB;      /* primary blue */
      --action-700: #1D4ED8;  /* hover */
      --success: #16a34a;
      --error: #e11d48;

      --radius-lg: 18px;
      --radius-md: 12px;
      --shadow: 0 14px 40px rgba(15,46,79,.14);
      --border: #e9eef7;
      --focus: 0 0 0 4px rgba(37,99,235,.16);
    }

    html, body { height: 100%; }
    body{
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
      background: var(--bg);
      background-image: var(--bg-accent);
      color: var(--text);
      display: grid;
      place-items: center;
      padding: 24px;
      line-height: 1.45;
    }

    /* =============== Card =============== */
    .container{
      width: 100%;
      max-width: 560px;
      padding: 8px;
    }
    .card{
      position: relative;
      z-index: 0; /* stacking context */
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 30px 28px 22px;
      text-align: center;
      overflow: hidden;
      animation: rise .45s ease both;
    }
    /* Soft decorative flare behind content */
    .card::after{
      content: "";
      position: absolute; right: -90px; top: -90px;
      width: 220px; height: 220px;
      border-radius: 50%;
      background: conic-gradient(from 200deg at 50% 50%, #eff5ff, #eef3fb, #eff5ff);
      opacity: .7;
      pointer-events: none;
      z-index: -1;
    }

    @keyframes rise{
      from{ opacity:0; transform: translateY(8px) scale(.995) }
      to{ opacity:1; transform: translateY(0) scale(1) }
    }

    /* =============== Text =============== */
    h2{
      font-size: 30px;
      font-weight: 800;
      letter-spacing: .2px;
      margin-bottom: 8px;
    }
    .subtitle{
      color: var(--muted);
      font-size: 15.5px;
      margin-bottom: 18px;
    }

    /* =============== Messages =============== */
    .message{
      text-align: left;
      border-radius: var(--radius-md);
      padding: 12px 14px;
      margin: 10px 0 16px;
      font-size: 14px;
      border-left: 4px solid transparent;
      background: #f1f5f9;       /* neutral default */
      color: #334155;
      border-color: #cbd5e1;
    }
    .message.success{
      background: #ecfdf5;
      border-color: var(--success);
      color: #065f46;
    }
    .message.error{
      background: #ffe8ee;
      border-color: var(--error);
      color: #8f1a37;
    }

    /* =============== Button =============== */
    .btn{
      display: inline-block;
      width: 100%;
      padding: 13px 16px;
      border-radius: 14px;
      font-weight: 800;
      letter-spacing: .2px;
      border: 1px solid transparent;
      text-decoration: none;
      user-select: none;
      text-align: center;
      color: #fff;
      background: var(--action);
      box-shadow: 0 10px 22px rgba(37,99,235,.26);
      transition: transform .06s ease, box-shadow .2s ease, background .2s ease, filter .2s ease;
      margin-top: 6px;
    }
    .btn:hover{ background: var(--action-700); }
    .btn:active{ transform: translateY(1px); }
    .btn:focus-visible{ outline: none; box-shadow: var(--focus); }

    .hint{
      margin-top: 12px;
      color: var(--muted);
      font-size: 14px;
    }

    /* =============== Responsive =============== */
    @media (max-width: 560px){
      body{ padding: 16px; }
      .card{ padding: 26px 18px 18px; border-radius: 16px; }
      h2{ font-size: 26px; }
      .subtitle{ font-size: 14.5px; }
    }

    @media (prefers-reduced-motion: reduce){
      *{ animation: none !important; transition: none !important; }
    }
  </style>
</head>
<body>
  <main class="container">
    <section class="card" role="status" aria-live="polite">
      <h2>Password Reset</h2>
      <p class="subtitle">Here’s the status of your request.</p>

      <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($success): ?>
        <div class="message success">Password has been successfully reset.</div>
        <a href="../public/login.php" class="btn">Go to Login</a>
      <?php else: ?>
        <div class="message">No action was performed.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
