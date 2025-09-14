<?php
// ===============================
// Admin Header + Sidebar (Indigo Sidebar + Green Active)
// ===============================

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/db.php';

// Defaults
$profileImageName = 'default.jpg';
$userName = 'Admin';

// Fetch current user
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $first = isset($user['first_name']) ? $user['first_name'] : '';
        $last  = isset($user['last_name']) ? $user['last_name'] : '';
        $userName = trim($first . ' ' . $last) ?: 'Admin';
        $profileImageName = !empty($user['profile_image']) ? $user['profile_image'] : 'default.jpg';
    }
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
<!-- Meta -->
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/admin_header.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Accessible skip link -->
<a class="skip-link" href="#content">Skip to content</a>

<header class="admin-header">
  <div class="header-left">
    <button class="hamburger" id="hamburger" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">
      <i class="fas fa-bars"></i>
    </button>
    <a href="dashboard.php" class="logo-text" aria-label="Go to dashboard">
      <i class="fa-solid fa-lock"></i> &nbsp;
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
        <!-- NEW: Payments nav item -->
    <li>
      <a href="payments.php" class="<?= isActive('payments.php') ?>" aria-current="<?= $currentPage === 'payments.php' ? 'page' : 'false' ?>">
        <i class="fas fa-credit-card"></i> <span>Payments</span>
      </a>
    </li>
    <li>
      <a href="wallet.php" class="<?= isActive('wallet.php') ?>" aria-current="<?= $currentPage === 'wallet.php' ? 'page' : 'false' ?>">
        <i class="fas fa-wallet"></i> <span>Wallet</span>
      </a>
    </li>

    <!-- /NEW -->
  </ul>

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

<!-- App JS (moved out of inline <script>) -->
<script src="../../assets/js/admin_header.js"></script>
