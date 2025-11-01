<?php
session_start();
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login | Kandado</title>

  <link rel="stylesheet" href="../assets/css/style.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
  <script src="../assets/js/auth.js" defer></script>

  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
</head>

<body class="auth-body">
  <div class="auth-shell">
    <section class="auth-preview" aria-hidden="true">
      <div class="preview-content">
        <a href="../index.php" class="brand-logo">
        <span class="brand-mark" aria-hidden="true">
          <img src="../assets/icon/kandado2.png" alt="" loading="lazy">
        </span>
        </a>
        <h2 class="preview-title">Welcome to Kandado</h2>
        <p class="preview-text">Securely manage your account with a calm, focused experience built for productivity.</p>
      </div>
      <footer class="preview-footer">
        <p>Trusted access control without the clutter.</p>
      </footer>
    </section>

    <div class="auth-card form-container no-flare">
      <header class="header">
        <span class="brand-chip">Kandado</span>
        <h1 class="title">Sign in</h1>
        <p class="subtitle">Enter your credentials to continue.</p>
      </header>

      <?php if (!empty($error)): ?>
        <div class="alert" role="alert"><p><?= htmlspecialchars($error) ?></p></div>
      <?php endif; ?>

      <form action="../auth/login_process.php" method="POST" class="auth-form" autocomplete="on" novalidate>
        <label class="label" for="email">Email</label>
        <div class="input-group">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 6l-10 7L2 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <input type="email" id="email" name="email" placeholder="you@example.com" required />
        </div>

        <label class="label" for="login_password">Password</label>
        <div class="input-group">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="10" width="18" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <input type="password" name="password" id="login_password" placeholder="Your password" required />
          <button type="button" class="toggle-btn js-toggle-password" data-target="#login_password" aria-pressed="false" aria-label="Show password">Show</button>
        </div>

        <div class="form-row">
          <a href="forgot_password.php" class="link subtle">Forgot password?</a>
        </div>

        <button type="submit" class="primary-btn">Sign in</button>
      </form>

      <p class="bottom-text">
        Don't have an account? <a href="register.php" class="link">Create one</a>
      </p>
    </div>
  </div>
</body>
</html>
