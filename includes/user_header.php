<?php
// user_header.php — v6 (medium-width mobile drawer)

if (session_status() === PHP_SESSION_NONE) {
  // Ensure the session cookie is valid across the whole site (prevents “logout” on file routes)
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',                    // IMPORTANT
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}
$userName   = $_SESSION['user_name'] ?? 'User';
$rawProfile = $_SESSION['profile_image'] ?? '';
$profileImage = !empty($rawProfile)
  ? '../../assets/uploads/' . htmlspecialchars($rawProfile, ENT_QUOTES, 'UTF-8') . '?t=' . time()
  : '../../assets/uploads/default.jpg';

$currentPage = basename($_SERVER['PHP_SELF']);
$logoPng = '../../assets/img/logo-temp.png';
?>

<link rel="stylesheet" href="../../assets/css/user_header.css">

<header class="pro-header" id="proHeader" role="banner">
  <a class="sr-only" href="#main">Skip to content</a>
  <div class="container">
    <nav class="pro-nav" aria-label="Main">
      <a class="brand" href="dashboard.php" title="Go to dashboard">
        <img src="<?= htmlspecialchars($logoPng, ENT_QUOTES, 'UTF-8') ?>" alt="Company logo (PNG)">
      </a>

      <div class="nav-center" role="navigation" aria-label="Primary">
        <a class="link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>Lockers</a>
        <a class="link <?= $currentPage === 'mylocker.php' ? 'active' : '' ?>" href="mylocker.php" <?= $currentPage === 'mylocker.php' ? 'aria-current="page"' : '' ?>>My Locker</a>
      </div>

      <div class="right">
        <div class="profile-wrap">
          <button class="avatar-btn" id="avatarBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="profileMenu" title="Profile menu">
            <img class="avatar" src="<?= $profileImage ?>" alt="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>'s profile">
            <span class="caret" aria-hidden="true"></span>
          </button>
          <div class="menu" id="profileMenu" role="menu" aria-label="Profile">
            <div class="menu-head">
              <img class="mini" src="<?= $profileImage ?>" alt="">
              <div>
                <div class="name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="meta">Signed in</div>
              </div>
            </div>
            <hr>
            <div class="menu-body">
              <a role="menuitem" href="../profile.php">👤 Profile</a>
              <hr>
              <a role="menuitem" class="danger" href="#" id="logoutLink">🚪 Logout</a>
            </div>
          </div>
        </div>

        <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-controls="drawer" aria-expanded="false">
          <span class="bar top" aria-hidden="true"></span>
          <span class="bar mid" aria-hidden="true"></span>
          <span class="bar bot" aria-hidden="true"></span>
        </button>
      </div>
    </nav>
  </div>

  <div class="overlay" id="overlay" hidden></div>
  <aside class="drawer" id="drawer" aria-hidden="true" aria-label="Mobile menu">
    <div class="drawer-head">
      <img src="<?= $profileImage ?>" class="drawer-avatar" alt="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>'s profile">
      <div>
        <div class="drawer-name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="drawer-sub">Welcome back</div>
      </div>
    </div>

    <div class="drawer-body">
      <a class="btn primary" href="../profile.php" id="viewProfileBtn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5.33 0-8 2.67-8 6v1h16v-1c0-3.33-2.67-6-8-6Z" fill="#fff"/></svg>
        <span>View Profile</span>
      </a>
      <button class="btn danger" id="logoutBtnMobile">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12 2v10M7 5.3A8 8 0 1 0 17 5.3" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
        <span>Logout</span>
      </button>

      <div class="drawer-section">
        <div class="section-title">Navigation</div>
        <nav class="drawer-nav" aria-label="Mobile navigation">
          <a class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
            <span class="icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="2"/><path d="M8 3v18M16 3v18M3 9h18M3 15h18" stroke="currentColor" stroke-width="2"/></svg>
            </span>
            <span>Lockers</span>
            <span class="chev" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
          </a>
          <a class="nav-item <?= $currentPage === 'mylocker.php' ? 'active' : '' ?>" href="mylocker.php" <?= $currentPage === 'mylocker.php' ? 'aria-current="page"' : '' ?>>
            <span class="icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2"/><path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </span>
            <span>My Locker</span>
            <span class="chev" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
          </a>
        </nav>
      </div>

      <div class="drawer-spacer"></div>
      <div class="drawer-footer">© <?= date('Y') ?> Your Company</div>
    </div>
  </aside>
</header>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/user_header.js"></script>
