<?php
// ===============================
// Admin Header + Sidebar (Indigo Sidebar)
// - Sidebar: no unread count, icons are white
// - Header: unread badge is red
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

// Unread alerts count (safe fallback)
$unreadAlertCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_alerts WHERE is_read = 0");
    $stmt->execute();
    $unreadAlertCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $unreadAlertCount = 0;
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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Styles -->
<link rel="stylesheet" href="../../assets/css/admin_header.css">
<style>
  :root{
    --brand-blue:#3056ff;
    --alert-red:#e02424; /* header badge color when unread > 0 */
  }

  .admin-header{ display:flex; align-items:center; justify-content:space-between; }

  /* ===== Header alerts (bell + red badge) ===== */
  .header-right a.alerts-link{
    position:relative; display:inline-flex; align-items:center; gap:.5rem; padding:.5rem .6rem;
    color:#2b3a67; text-decoration:none;
  }
  .header-right a.alerts-link i{
    font-size:18px;
    color:var(--brand-blue); /* keep bell blue */
  }

  /* Red numeric pill shown ONLY in header when unread > 0 */
  .pill{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:20px; height:20px; padding:0 7px;
    border-radius:999px; font-size:11px; font-weight:700;
    background:var(--alert-red); color:#fff;
    border:2px solid var(--alert-red); line-height:18px;
    box-shadow:0 2px 6px rgba(224,36,36,.25);
  }

  /* place the pill on bell */
  .header-right a.alerts-link .pill{
    position:absolute; top:0; right:0; transform:translate(30%,-30%);
  }

  /* Red pulse ring when there are unread alerts */
  .alerts-link.has-unread::after{
    content:""; position:absolute; top:4px; right:4px; width:18px; height:18px;
    border:2px solid var(--alert-red); border-radius:999px;
    animation:ringPulse 1.6s cubic-bezier(0,0,.2,1) infinite;
    pointer-events:none;
  }
  @keyframes ringPulse{
    0%   { transform:scale(.6); opacity:.70; }
    70%  { transform:scale(1.8); opacity:0; }
    100% { transform:scale(1.8); opacity:0; }
  }

  /* Sidebar tweaks */
  .sidebar-links a i{ color:#fff; } /* make sidebar icons white */
  /* removed sidebar pill styles entirely */
  .header-right a.alerts-link:focus{
    outline:2px solid var(--brand-blue); outline-offset:2px; border-radius:10px;
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<a class="skip-link" href="#content">Skip to content</a>

<header class="admin-header">
  <div class="header-left">
    <button class="hamburger" id="hamburger" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">
      <i class="fas fa-bars"></i>
    </button>

    <a href="../../index.php" class="logo" aria-label="Go to dashboard" style="background-color: white;">
      <img src="/kandado/assets/icon/kandado.png" alt="Kandado logo" width="36" height="36" decoding="async" loading="eager"/>
    </a>
  </div>

  <!-- Header: red unread number when there are notifications -->
  <div class="header-right">
    <a class="alerts-link <?= $unreadAlertCount > 0 ? 'has-unread' : '' ?>" href="security_alerts.php" aria-label="Open security alerts">
      <i class="fas fa-bell" aria-hidden="true" style = "color: white;"></i>
      <?php if ($unreadAlertCount > 0): ?>
        <span class="pill" aria-label="<?= (int)$unreadAlertCount ?> unread alerts" role="status" aria-live="polite">
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
    <li>
      <a href="payments.php" class="<?= isActive('payments.php') ?>" aria-current="<?= $currentPage === 'payments.php' ? 'page' : 'false' ?>">
        <i class="fas fa-credit-card"></i> <span>Payments</span>
      </a>
    </li>
    <li>
      <a href="refunds.php" class="<?= isActive('refunds.php') ?>" aria-current="<?= $currentPage === 'refunds.php' ? 'page' : 'false' ?>">
          <i class="fas fa-hand-holding-dollar"></i> <span>Refunds</span>
      </a>
    </li>
    <li>

      </a>
    </li>
    <li>
      <a href="wallet.php" class="<?= isActive('wallet.php') ?>" aria-current="<?= $currentPage === 'wallet.php' ? 'page' : 'false' ?>">
        <i class="fas fa-wallet"></i> <span>Wallets</span>
      </a>
    </li>

       <li>
  <a href="violations.php"
     class="<?= isActive('violations.php') ?>"
      aria-current="<?= $currentPage === 'violations.php' ? 'page' : 'false' ?>">
      <i class="fas fa-triangle-exclamation"></i> <span>Violations</span>
    </a>
  </li>


    <!-- Security Alerts (no number shown in sidebar; icon is white via CSS) -->
    <li>
      <a href="security_alerts.php" class="<?= isActive('security_alerts.php') ?>" aria-current="<?= $currentPage === 'security_alerts.php' ? 'page' : 'false' ?>">
        <i class="fas fa-shield-halved"></i> <span>Security Alerts</span>
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
