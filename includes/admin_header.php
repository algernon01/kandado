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

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ===============================
   Design Tokens (Header Blue, Sidebar Indigo, Active Green)
================================== */
:root{
  /* Brand gradient endpoints (header) */
  --brand-1:#3353bb;
  --brand-2:#4f6ed1;

  /* Sidebar gradient endpoints (indigo) */
  --sidebar-1: #2a3f93;
  --sidebar-2: #1f2e78;

  /* Sidebar text + accents */
  --sidebar-text: #eef3ff;
  --sidebar-muted: #cfe0ff;
  --sidebar-icon: #f3f7ff;
  --sidebar-hover-bg: rgba(255,255,255,.10);
  --sidebar-hover-border: rgba(255,255,255,.18);
  --sidebar-border: rgba(255,255,255,.14);
  --sidebar-scrollbar: rgba(255,255,255,.25);

  /* Active GREEN tokens */
  --active-green-1: #16a34a;   /* emerald-600 */
  --active-green-2: #22c55e;   /* emerald-500 */
  --active-green-bg: #e9f7ee;  /* used in light surfaces */
  --active-green-border: #cdebd8;

  /* Light surfaces (main area) */
  --bg: #f6f8ff;
  --surface: #ffffff;
  --surface-2: #fbfdff;
  --text: #0f172a;
  --muted: #64748b;
  --primary: var(--brand-1);
  --primary-600:#2b48a8;
  --primary-700:#223b8f;
  --accent:#4f6ed1;
  --success:#16a34a;
  --danger:#dc2626;
  --border:#e5e9f6;
  --radius: 14px;

  --header-h: 60px;
  --sidebar-w: 280px;

  --shadow-1: 0 8px 24px rgba(51, 83, 187, 0.18);
  --shadow-2: 0 4px 14px rgba(15, 23, 42, 0.08);
}

/* Reduce motion */
@media (prefers-reduced-motion: reduce){
  *{animation:none !important; transition:none !important; scroll-behavior:auto !important;}
}

/* ===============================
   Base Reset
================================== */
*{margin:0; padding:0; box-sizing:border-box;}
html, body{height:100%;}
body{
  font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans";
  background: linear-gradient(180deg, var(--bg), #ffffff 60%, var(--bg));
  color: var(--text);
  -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
}

/* ===============================
   Header (Brand Gradient)
================================== */
.admin-header{
  position: fixed; inset: 0 0 auto 0; height: var(--header-h);
  display: flex; align-items: center; gap: .75rem; padding: 0 .9rem;
  z-index: 1100; color: #fff;
  background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
  box-shadow: var(--shadow-1);
  border-bottom: 1px solid rgba(255,255,255,.18);
}

/* Skip link (a11y) */
.skip-link{position:absolute; left:-9999px; top:-9999px;}
.skip-link:focus{
  left:.75rem; top:.75rem; background:#fff; color:var(--primary-700);
  padding:.5rem .75rem; border-radius:8px; z-index: 1200; border:1px solid var(--primary);
  font-weight:600;
}

.header-left{display:flex; align-items:center; gap:.75rem;}
.hamburger{
  background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.25); color:#fff;
  font-size:1.2rem; cursor:pointer; display:none; width:42px; height:42px; border-radius:10px;
}
.hamburger:hover{background:rgba(255,255,255,.24);}
.hamburger:focus-visible{outline:2px solid #fff; outline-offset:2px;}

.logo-text{
  color:#fff; text-decoration:none; font-weight:700; letter-spacing:.2px;
  font-size:1.05rem; padding:.45rem .7rem; border-radius:10px;
  background: rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.25);
}

/* ===============================
   Sidebar (Indigo Gradient + Light Text)
================================== */
.sidebar{
  position: fixed; top: var(--header-h); left: 0;
  width: var(--sidebar-w); height: calc(100vh - var(--header-h));
  background: linear-gradient(180deg, var(--sidebar-1) 0%, var(--sidebar-2) 100%);
  border-right: 1px solid var(--sidebar-border);
  box-shadow: none;
  padding: 1rem 0 1.2rem; overflow-y:auto; z-index:1050;
  transition: transform .28s ease;
  color: var(--sidebar-text);
}
.sidebar::-webkit-scrollbar{width:10px;}
.sidebar::-webkit-scrollbar-thumb{background: var(--sidebar-scrollbar); border-radius:10px;}
.sidebar.open{transform: translateX(0);}

.sidebar-header{
  padding: .1rem 1rem 1rem; margin: 0 1rem 1rem;
  border-bottom: 1px dashed var(--sidebar-border);
}
.sidebar-profile{display:grid; grid-template-columns:64px 1fr; gap:.9rem; align-items:center;}
.sidebar-profile-img{
  width:64px; height:64px; border-radius:50%;
  border: 2px solid rgba(255,255,255,.25);
  object-fit:cover; background:rgba(255,255,255,.15);
  box-shadow: 0 6px 12px rgba(0,0,0,.18);
}
.sidebar-user-name{font-weight:700; line-height:1.15; color: var(--sidebar-text);}
.sidebar-user-sub{color: var(--sidebar-muted); font-size:.88rem; margin-top:.15rem;}

.sidebar-section-label{
  font-size:.78rem; letter-spacing:.08em; text-transform:uppercase;
  color:var(--sidebar-muted); margin:.9rem 1.6rem .5rem;
}

/* Links */
.sidebar-links{list-style:none; padding:.3rem 0; margin:0;}
.sidebar-links li{margin:.12rem 0;}
.sidebar-links a{
  --pad-x: 1rem;
  position: relative;
  display:flex; align-items:center; gap:.8rem;
  padding:.72rem var(--pad-x) .72rem calc(var(--pad-x) - .2rem);
  margin: 0 .6rem;
  color:var(--sidebar-text); text-decoration:none; font-weight:600; border-radius:12px;
  line-height:1.15; transition: background .15s ease, border-color .15s ease;
  border:1px solid transparent;
}
.sidebar-links a i{
  width:22px; text-align:center; font-size:1rem; color: var(--sidebar-icon);
}

/* Left accent bar – light by default/hover, GREEN when active */
.sidebar-links a::before{
  content:""; position:absolute; left:8px; top:50%; transform:translateY(-50%);
  width:4px; height:0; border-radius:4px;
  background: linear-gradient(180deg, rgba(255,255,255,.75), rgba(255,255,255,.45));
  transition: height .15s ease, background .15s ease;
}
.sidebar-links a:hover::before{ height: 65%; }
.sidebar-links a.active::before{
  background: linear-gradient(135deg, var(--active-green-1), var(--active-green-2));
  height: 65%;
}

/* Hover */
.sidebar-links a:hover{
  background: var(--sidebar-hover-bg);
  border-color: var(--sidebar-hover-border);
}

/* Active link container: GREEN on dark bg */
.sidebar-links a.active{
  background: rgba(34, 197, 94, .18); /* emerald-500 at ~18% */
  border-color: rgba(34, 197, 94, .35);
  box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .28);
}

/* Ensure focus rings are visible on dark sidebar */
.sidebar-links a:focus-visible{
  outline: 2px solid #ffffff;
  outline-offset: 3px;
  box-shadow: 0 0 0 2px rgba(255,255,255,.25);
}

/* ===============================
   Overlay (mobile)
================================== */
.overlay{
  position: fixed; inset: 0; background: rgba(51, 83, 187, .35);
  z-index:1040; display:none;
}
.overlay.show{display:block;}

/* ===============================
   Main layout offsets
================================== */
body > main#content{
  margin-top: var(--header-h);
  margin-left: var(--sidebar-w);
  padding: 1.25rem clamp(1rem, 2vw, 2rem);
  min-height: calc(100vh - var(--header-h));
  background: linear-gradient(180deg, var(--surface-2), var(--bg));
}

/* Optional content card helper */
.container-card{
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-2);
  padding: 1rem;
}

/* Status badge (kept green for consistency on light content surfaces) */
.badge{
  display:inline-flex; align-items:center; gap:.4rem;
  font-size:.75rem; font-weight:700; letter-spacing:.02em;
  padding:.25rem .6rem; border-radius:999px;
  background: var(--active-green-bg);
  color: var(--active-green-1);
  border:1px solid var(--active-green-border);
  line-height: 1;
}
.badge i{ color: var(--active-green-1); }

/* ===============================
   Responsive
================================== */
@media (max-width: 1024px){ :root{ --sidebar-w: 260px; } }
@media (max-width: 860px){
  .hamburger{display:inline-grid; place-items:center;}
  .sidebar{transform: translateX(-100%);}
  body > main#content{margin-left:0;}
}
@media (min-width: 861px){ .overlay{display:none !important;} }
</style>

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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hamburger = document.getElementById('hamburger');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('overlay');

  const setSidebar = (open) => {
    sidebar.classList.toggle('open', open);
    hamburger?.setAttribute('aria-expanded', String(open));
    overlay?.classList.toggle('show', open);
    if (overlay) overlay.hidden = !open;
    document.body.style.overflow = open && window.matchMedia('(max-width: 860px)').matches ? 'hidden' : '';
  };

  hamburger?.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
  overlay?.addEventListener('click', () => setSidebar(false));

  // Close on ESC
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) setSidebar(false);
  });

  // Focus trap (mobile)
  const focusableSel = 'a[href], button:not([disabled])';
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab' || !sidebar.classList.contains('open')) return;
    const nodes = sidebar.querySelectorAll(focusableSel);
    if (!nodes.length) return;
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  // Logout confirm (SweetAlert2) – uses your brand color
  const attachLogoutHandler = (el) => {
    el.addEventListener('click', (event) => {
      event.preventDefault();
      const brand = getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb';
      Swal.fire({
        title: 'Are you sure?',
        text: 'You will be logged out from the admin panel.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: brand,
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel',
        background: '#ffffff',
        color: '#0f172a'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = "/kandado/auth/logout.php";
        }
      });
    });
  };
  document.querySelectorAll('a[href$="logout.php"], a.logout-link').forEach(attachLogoutHandler);

  // Auto-close sidebar when resizing to desktop
  let width = window.innerWidth;
  window.addEventListener('resize', () => {
    const now = window.innerWidth;
    if (width <= 860 && now > 860) setSidebar(false);
    width = now;
  });
});
</script>