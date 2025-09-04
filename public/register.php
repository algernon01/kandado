<?php
session_start();
$errors = $_SESSION['register_errors'] ?? [];
unset($_SESSION['register_errors']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register | Kandado</title>

  <link rel="stylesheet" href="../assets/css/style.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
  <script src="../assets/js/auth.js" defer></script>
</head>

<body class="auth-body">
  <div class="auth-card register-container">
    <header class="header">
      <h1 class="title">Register</h1>
      <p class="subtitle">Create your account to get started.</p>
    </header>

    <?php if (!empty($errors)): ?>
      <div class="alert" role="alert">
        <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form id="registerForm" method="POST" action="../auth/register_process.php" class="auth-form" novalidate>
      <div class="grid-2">
        <div>
          <label class="label" for="first_name">First name</label>
          <div class="input-group">
            <span class="input-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            </span>
            <input type="text" id="first_name" name="first_name" placeholder="Juan" required />
          </div>
        </div>

        <div>
          <label class="label" for="last_name">Last name</label>
          <div class="input-group">
            <span class="input-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            </span>
            <input type="text" id="last_name" name="last_name" placeholder="Dela Cruz" required />
          </div>
        </div>
      </div>

      <label class="label" for="reg_email">Email</label>
      <div class="input-group">
        <span class="input-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 6l-10 7L2 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        </span>
        <input type="email" id="reg_email" name="email" placeholder="you@example.com" required />
      </div>

      <label class="label" for="password">Password</label>
      <div class="input-group">
        <span class="input-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="10" width="18" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        </span>
        <input type="password" name="password" id="password" placeholder="At least 8 characters" required autocomplete="new-password" />
        <button type="button" class="toggle-btn js-toggle-password" data-target="#password" aria-pressed="false" aria-label="Show password">Show</button>
      </div>

      <label class="label" for="confirm_password">Confirm password</label>
      <div class="input-group">
        <span class="input-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="10" width="18" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        </span>
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required autocomplete="new-password" />
        <button type="button" class="toggle-btn js-toggle-password" data-target="#confirm_password" aria-pressed="false" aria-label="Show password">Show</button>
      </div>

      <!-- Strength meter -->
      <div class="strength" aria-live="polite">
        <div id="strength-meter"><div id="strength-bar"></div></div>
        <p id="strength-label">Enter a password</p>
      </div>

      <button type="submit" class="primary-btn">Sign up</button>
      <p class="bottom-text">Already have an account? <a href="login.php" class="link">Sign in</a></p>
    </form>
  </div>
</body>
</html>
