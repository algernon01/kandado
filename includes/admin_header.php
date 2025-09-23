<?php
// ===============================
// Admin Header + Sidebar (Indigo Sidebar + Green Active)
// + Security Alerts tab with unread counter
// ===============================

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/db.php';

// Defaults
$profileImageName = 'default.jpg';
$userName = 'Admin';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Fetch current user
if ($userId) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $first = isset($user['first_name']) ? $user['first_name'] : '';
        $last  = isset($user['last_name']) ? $user['last_name'] : '';
        $userName = trim($first . ' ' . $last) ?: 'Admin';
        $profileImageName = !empty($user['profile_image']) ? $user['profile_image'] : 'default.jpg';
    }
}

// OPTIONAL: Unread security alerts counter
// Assumes a table `security_alerts` with columns: id, user_id (nullable), is_read (0/1), created_at
// Alerts addressed to a specific user or global (user_id IS NULL) are counted.
$unreadAlertCount = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM security_alerts 
        WHERE is_read = 0 
          AND (:uid IS NULL OR user_id = :uid OR user_id IS NULL)
    ");
    $stmt->execute([':uid' => $userId]);
    $unreadAlertCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $unreadAlertCount = 0; // fail safe
}

// Cache-busting for profile image
$profileImagePath = '/kandado/assets/uploads/' . htmlspecialchars($profileImageName, ENT_QUOTES, 'UTF-8') . '?t=' . time();

// Active page helper
$currentPage = basename(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH));
function isActive($file) {
  global $currentPage;
  return $currentPage === $file ? 'active' : '';
}
?>
<link rel="icon" href="../../assets/icon/icon_tab.png" sizes="any">
<!-- Meta -->
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Styles -->
<link rel="stylesheet" href="../../assets/css/admin_header.css">
<style>
  /* tiny pill badge (in case your CSS doesn't already have one) */
  .pill {
    display:inline-flex; align-items:center; justify-content:center;
    min-width: 18px; height: 18px; padding: 0 6px; margin-left: .5rem;
    border-radius: 999px; font-size: 11px; font-weight: 700;
    background: #e11d48; color: #fff; line-height: 18px;
  }
  .admin-header { display:flex; align-items:center; justify-content:space-between; }
  .header-right a.alerts-link { position:relative; display:inline-flex; align-items:center; gap:.5rem; padding:.5rem; }
  .header-right a.alerts-link .pill { position:absolute; top:2px; right:2px; }
</style>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Accessible skip link -->
<a class="skip-link" href="#content">Skip to content</a>

<header class="admin-header">
  <div class="header-left">
    <button class="hamburger" id="hamburger" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">
      <i class="fas fa-bars"></i>
    </button>

    <a href="dashboard.php" class="logo" aria-label="Go to dashboard" style="background-color: white;">
      <img
        src="/kandado/assets/icon/kandado.png"
        alt="Kandado logo"
        width="36"
        height="36"
        decoding="async"
        loading="eager"
      />
    </a>
  </div>

  <!-- Optional quick access to Security Alerts in header -->
  <div class="header-right">
    <a class="alerts-link" href="security_alerts.php" aria-label="Open security alerts">
      <i class="fas fa-bell"></i>
      <?php if ($unreadAlertCount > 0): ?>
        <span class="pill" aria-label="<?= (int)$unreadAlertCount ?> unread alerts">
          <?= $unreadAlertCount > 99 ? '99+' : (int)$unreadAlertCount ?>
        </span>
      <?php endif; ?>
    </a>
  </div>
</header>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Admin Sidebar">
  <div class="sidebar-header">
    <div class="sidebar-profile">
      <img
        src="<?= $profileImagePath ?>"
        alt="Profile picture of <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>"
        class="sidebar-profile-img"
        loading="lazy"
      />
      <div>
        <p class="sidebar-user-name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="sidebar-user-sub">Administrator <span class="badge"><i class="fa-regular fa-circle-check"></i> Active</span></p>
      </div>
    </div>
  </div>

  <div class="sidebar-section-label">Navigation</div>
  <ul class="sidebar-links">
    <li>
      <a href="dashboard.php" class="<?= isActive('dashboard.php') ?>" aria-current="<?= $currentPage === 'dashboard.php' ? 'page' : 'false' ?>">
        <i class="fas fa-gauge-high"></i> <span>Dashboard</span>
      </a>
    </li>
    <li>
      <a href="manage_users.php" class="<?= isActive('manage_users.php') ?>" aria-current="<?= $currentPage === 'manage_users.php' ? 'page' : 'false' ?>">
        <i class="fas fa-users"></i> <span>Manage Users</span>
      </a>
    </li>
    <li>
      <a href="locker_history.php" class="<?= isActive('locker_history.php') ?>" aria-current="<?= $currentPage === 'locker_history.php' ? 'page' : 'false' ?>">
        <i class="fas fa-history"></i> <span>Locker History</span>
      </a>
    </li>

    <!-- Payments -->
    <li>
      <a href="payments.php" class="<?= isActive('payments.php') ?>" aria-current="<?= $currentPage === 'payments.php' ? 'page' : 'false' ?>">
        <i class="fas fa-credit-card"></i> <span>Payments</span>
      </a>
    </li>

    <!-- Wallets -->
    <li>
      <a href="wallet.php" class="<?= isActive('wallet.php') ?>" aria-current="<?= $currentPage === 'wallet.php' ? 'page' : 'false' ?>">
        <i class="fas fa-wallet"></i> <span>Wallets</span>
      </a>
    </li>

    <!-- NEW: Security Alerts -->
    <li>
      <a href="security_alerts.php" class="<?= isActive('security_alerts.php') ?>" aria-current="<?= $currentPage === 'security_alerts.php' ? 'page' : 'false' ?>">
        <i class="fas fa-shield-halved"></i> <span>Security Alerts</span>
        <?php if ($unreadAlertCount > 0): ?>
          <span class="pill" aria-hidden="true"><?= $unreadAlertCount > 99 ? '99+' : (int)$unreadAlertCount ?></span>
        <?php endif; ?>
      </a>
    </li>
  </ul>

  <div class="sidebar-section-label">Account</div>
  <ul class="sidebar-links">
    <li>
      <a href="/kandado/auth/logout.php" class="logout-link">
        <i class="fas fa-right-from-bracket"></i> <span>Logout</span>
      </a>
    </li>
  </ul>
</aside>

<div class="overlay" id="overlay" hidden></div>

<!-- App JS -->
<script src="../../assets/js/admin_header.js"></script>
