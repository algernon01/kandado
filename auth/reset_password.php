<?php
session_start();
require_once '../config/db.php';

$token = $_GET['token'] ?? '';
$error = '';

if (empty($token)) {
    $error = 'Invalid or missing token.';
} else {
    $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Invalid or expired token.';
    } elseif (strtotime($user['reset_token_expires']) < time()) {
        $error = 'This reset link has expired.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reset Password • Kandado</title>
  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
  <link rel="stylesheet" href="../assets/css/style.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
  <script src="../assets/js/auth.js" defer></script>
</head>
<body class="auth-body">
  <div class="auth-card form-container no-flare" role="region" aria-live="polite">
    <header class="header">
      <h1 class="title">Reset your password</h1>
      <p class="subtitle">Create a new password for your account.</p>
    </header>

    <?php if (!empty($error)): ?>
      <div class="alert" role="alert">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
      </div>
      <p class="bottom-text">
        <a href="/kandado/public/forgot_password.php" class="link">Request a new link</a> ·
        <a href="/kandado/public/login.php" class="link">Back to login</a>
      </p>
    <?php else: ?>
      <!-- data-special-chars is the exact set enforced on the server -->
      <form
        id="resetForm"
        method="POST"
        action="reset_password_process.php"
        class="auth-form"
        novalidate
        data-special-chars="!@#$%^&*()-_=+{};:,<.>"
      >
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />

        <label class="label" for="password">New password</label>
        <div class="input-group">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="10" width="18" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
              <path d="M7 10V7a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <input
            type="password"
            name="new_password"
            id="password"
            placeholder="At least 8 characters"
            required
            autocomplete="new-password"
            aria-describedby="pw-help"
          />
          <button type="button" class="toggle-btn js-toggle-password" data-target="#password" aria-pressed="false" aria-label="Show password">Show</button>
        </div>
        <small id="pw-help" class="help">
          Must be ≥ 8 chars and include at least 1 special character from: <code>! @ # $ % ^ &amp; * ( ) - _ = + { } ; : , &lt; . &gt;</code>
        </small>

        <label class="label" for="confirm_password">Confirm new password</label>
        <div class="input-group">
          <span class="input-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="10" width="18" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
              <path d="M7 10V7a5 5 0 0 1 10 0v3" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <input
            type="password"
            name="confirm_password"
            id="confirm_password"
            placeholder="Re-enter password"
            required
            autocomplete="new-password"
          />
          <button type="button" class="toggle-btn js-toggle-password" data-target="#confirm_password" aria-pressed="false" aria-label="Show password">Show</button>
        </div>

        <!-- Strength meter -->
        <div class="strength" aria-live="polite">
          <div id="strength-meter"><div id="strength-bar"></div></div>
          <p id="strength-label">Enter a password</p>
        </div>

        <button type="submit" class="primary-btn" id="submitBtn">Reset password</button>
      </form>

      <p class="bottom-text">
        <a href="/kandado/public/login.php" class="link">Back to login</a>
      </p>
    <?php endif; ?>
  </div>

  <script>
    // Page-specific validation (aligned with server's exact special-char set)
    (function () {
      const byId = (id) => document.getElementById(id);

      const form   = document.getElementById('resetForm');
      if (!form) return; // no form when token invalid

      const pw     = byId('password');
      const conf   = byId('confirm_password');
      const bar    = byId('strength-bar');
      const label  = byId('strength-label');
      const submit = byId('submitBtn');

      // The exact set the server expects
      const specials = form.getAttribute('data-special-chars') || "!@#$%^&*()-_=+{};:,<.>";

      // Escape chars for use inside a JS character class
      function escapeForCharClass(s) {
        return s.replace(/[\\^$.*+?()[\]{}|\-]/g, '\\$&');
      }
      const specialRe = new RegExp('[' + escapeForCharClass(specials) + ']');

      // Strength meter uses SAME special set (not [^A-Za-z0-9])
      function strengthInfo(value) {
        let score = 0;
        if (value.length >= 8) score++;
        if (/[A-Z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (specialRe.test(value)) score++;

        if (score <= 1) return { label: 'Weak', width: '25%', color: '#ef4444', score };
        if (score === 2) return { label: 'Medium', width: '50%', color: '#f59e0b', score };
        if (score === 3) return { label: 'Strong', width: '75%', color: '#3b82f6', score };
        return { label: 'Very strong', width: '100%', color: '#22c55e', score };
      }

      // Live meter update
      pw?.addEventListener('input', () => {
        const s = strengthInfo(pw.value);
        if (bar) {
          bar.style.width = s.width;
          bar.style.background = s.color;
        }
        if (label) {
          label.textContent = s.label;
          label.style.color = s.color;
        }
      });

      // Submit validation with friendly prompts
      form.addEventListener('submit', (e) => {
        const pass  = pw?.value || '';
        const cpass = conf?.value || '';
        const s     = strengthInfo(pass);

        // Must contain at least 1 required special char
        if (!specialRe.test(pass)) {
          e.preventDefault();
          const msg = `Password must include at least one special character: ${specials}`;
          if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Missing special character', text: msg, confirmButtonColor: '#2563EB' });
          } else {
            alert(msg);
          }
          return;
        }

        // Mismatch
        if (pass !== cpass) {
          e.preventDefault();
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: 'Passwords don’t match',
              text: 'Please re-enter the same password.',
              confirmButtonColor: '#2563EB'
            });
          } else {
            alert('Passwords don’t match. Please re-enter the same password.');
          }
          return;
        }

        // Too weak
        if (s.score <= 1) {
          e.preventDefault();
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: 'Weak password',
              text: 'Use at least 8 characters with uppercase, number, and a symbol from the allowed set.',
              confirmButtonColor: '#2563EB'
            });
          } else {
            alert('Weak password. Use at least 8 chars with uppercase, number, and an allowed symbol.');
          }
          return;
        }

        // Medium -> ask to proceed
        if (s.score === 2) {
          e.preventDefault();
          if (window.Swal) {
            Swal.fire({
              icon: 'warning',
              title: 'Medium strength',
              text: 'Proceed with a medium-strength password?',
              showCancelButton: true,
              confirmButtonText: 'Proceed',
              cancelButtonText: 'Improve',
              confirmButtonColor: '#2563EB',
              cancelButtonColor: '#ef4444'
            }).then((res) => {
              if (res.isConfirmed) {
                submit?.setAttribute('disabled', 'true');
                if (submit) submit.textContent = 'Submitting…';
                form.submit();
              }
            });
          } else {
            if (confirm('Password is medium. Proceed?')) {
              submit?.setAttribute('disabled', 'true');
              if (submit) submit.textContent = 'Submitting…';
              form.submit();
            }
          }
          return;
        }

        // Strong / Very strong: let it submit, disable button
        submit?.setAttribute('disabled', 'true');
        if (submit) submit.textContent = 'Submitting…';
      });
    })();
  </script>
</body>
</html>
