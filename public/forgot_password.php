<?php
session_start();
$success = $_SESSION['forgot_success'] ?? '';
$error   = $_SESSION['forgot_error']  ?? '';
unset($_SESSION['forgot_success'], $_SESSION['forgot_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Forgot Password • Kandado</title>

  <!-- Reuse your main auth styles -->
  <link rel="stylesheet" href="../assets/css/style.css" />

  <!-- Minimal additions for success/info notes (uses the same tokens) -->
  <style>
    .note{
      background: #ecfdf5;
      color: #065f46;
      border-left: 4px solid var(--success, #22c55e);
      border-radius: 12px;
      padding: 10px 12px;
      margin-bottom: 14px;
      font-size: 14px;
    }
  </style>
</head>

<body class="auth-body">
  <div class="auth-card form-container">
    <header class="header">
      <h1 class="title">Forgot password</h1>
      <p class="subtitle">Enter your email and we’ll send you a reset link.</p>
    </header>

    <?php if (!empty($success)): ?>
      <div class="note" role="status" aria-live="polite">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php elseif (!empty($error)): ?>
      <div class="alert" role="alert">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form id="forgotForm" action="../auth/forgot_password_process.php" method="POST" class="auth-form" novalidate>
      <label class="label" for="email">Email</label>
      <div class="input-group">
        <span class="input-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="2"/>
            <path d="M22 6l-10 7L2 6" fill="none" stroke="currentColor" stroke-width="2"/>
          </svg>
        </span>
        <input type="email" id="email" name="email" placeholder="you@example.com" required />
      </div>

      <button type="submit" class="primary-btn" id="submitBtn">Send reset link</button>
    </form>

    <p class="bottom-text">
      Remembered it? <a href="login.php" class="link">Back to login</a>
    </p>
  </div>

  <script>
    // Prevent double submit & give quick feedback
    (function () {
      const form = document.getElementById('forgotForm');
      const btn  = document.getElementById('submitBtn');
      form?.addEventListener('submit', () => {
        btn.disabled = true;
        btn.textContent = 'Sending…';
      });
    })();
  </script>
</body>
</html>
