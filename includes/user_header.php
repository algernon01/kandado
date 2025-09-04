<?php
// user_header.php — v6 (medium-width mobile drawer)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userName   = $_SESSION['user_name'] ?? 'User';
$rawProfile = $_SESSION['profile_image'] ?? '';
$profileImage = !empty($rawProfile)
  ? '../../assets/uploads/' . htmlspecialchars($rawProfile, ENT_QUOTES, 'UTF-8') . '?t=' . time()
  : '../../assets/uploads/default.jpg';

$currentPage = basename($_SERVER['PHP_SELF']);
$logoPng = '../../assets/img/logo-temp.png';
?>
<!-- ========== HEADER START ========== -->
<style>
  :root{
    --primary-50:#eef4ff; --primary-100:#e0e7ff; --primary-600:#2e5bff; --primary-700:#2249de; --primary-800:#1b3db3;
    --accent-500:#14b8a6; --accent-600:#0ea5a0; --danger-500:#ef4444;
    --bg:#f7f9fc; --card:#ffffff; --ink:#0f172a; --muted:#667085;
    --ring:0 0 0 3px rgba(20,184,166,.28); --radius:14px;

    /* easy tweak: set the mobile drawer width */
    --drawer-mobile-w: 64vw;  /* medium-wide, not too long */
    --drawer-mobile-max: 420px;
  }

  *,*::before,*::after{box-sizing:border-box}
  html,body{margin:0;height:100%;overflow-x:hidden}
  body{background:var(--bg);font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji")}
  a{color:inherit;text-decoration:none}
  img{display:block;max-width:100%}
  button{font:inherit}
  .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

  /* Header shell */
  .pro-header{position:sticky;top:0;z-index:1000;background:linear-gradient(180deg,rgba(46,91,255,.98),rgba(27,61,179,.96));backdrop-filter:saturate(120%) blur(6px);box-shadow:0 2px 14px rgba(16,24,40,.18);transition:box-shadow .2s ease}
  .pro-header.elevated{box-shadow:0 12px 32px rgba(16,24,40,.22)}
  .pro-header .container{max-width:1280px;margin:0 auto;padding:0 clamp(.75rem,2vw,1rem)}

  .pro-nav{display:flex;align-items:center;justify-content:space-between;min-height:74px;gap:1rem}

  .brand{display:flex;align-items:center;gap:.7rem}
  .brand img{width:40px;height:40px;object-fit:contain;border-radius:10px;box-shadow:0 6px 16px rgba(0,0,0,.18);background:#fff}

  .nav-center{display:flex;align-items:center;justify-content:center;gap:.6rem;flex:1}
  .link{--pad-x:1rem;--pad-y:.55rem;position:relative;display:inline-flex;align-items:center;justify-content:center;padding:var(--pad-y) var(--pad-x);border-radius:999px;color:#eaf1ff;font-weight:800;letter-spacing:.25px;transition:transform .12s ease,color .12s ease,background-color .12s ease,box-shadow .12s ease,border-color .12s ease;border:1px solid rgba(255,255,255,.18);background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.03));box-shadow:inset 0 1px 0 rgba(255,255,255,.25),0 8px 18px rgba(0,0,0,.06)}
  .link:hover{color:#fff;background:linear-gradient(180deg,rgba(255,255,255,.12),rgba(255,255,255,.06));transform:translateY(-1px);border-color:rgba(255,255,255,.35);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 10px 20px rgba(0,0,0,.1)}
  .link:focus-visible{outline:none;box-shadow:var(--ring)}
  .link.active{color:var(--primary-800);background:#fff;border-color:#fff;box-shadow:0 10px 26px rgba(46,91,255,.28),0 1px 0 rgba(255,255,255,.9) inset;transform:translateY(-1px)}

  .right{display:flex;align-items:center;gap:.55rem}
  .avatar-btn{display:flex;align-items:center;gap:.45rem;border:none;background:transparent;cursor:pointer;border-radius:999px;padding:2px}
  .avatar-btn:focus-visible{box-shadow:var(--ring)}
  .avatar{width:42px;height:42px;border-radius:999px;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 10px rgba(0,0,0,.25)}
  .caret{width:8px;height:8px;border-right:2px solid #fff;border-top:2px solid #fff;transform:rotate(135deg);transition:transform .16s ease;display:inline-block}
  .avatar-btn.open .caret{transform:rotate(-45deg)}

  .profile-wrap{position:relative}
  .menu{position:absolute;top:calc(100% + 12px);right:0;min-width:280px;background:var(--card);color:var(--ink);border-radius:16px;box-shadow:0 18px 44px rgba(16,24,40,.18);border:1px solid #e9eef7;opacity:0;transform:translateY(-8px);pointer-events:none;transition:opacity .16s ease,transform .16s ease}
  .menu.open{opacity:1;transform:translateY(0);pointer-events:auto}
  .menu::before{content:"";position:absolute;right:18px;top:-8px;width:14px;height:14px;background:#fff;border-left:1px solid #e9eef7;border-top:1px solid #e9eef7;transform:rotate(45deg)}
  .menu-head{display:flex;gap:.7rem;align-items:center;padding:.85rem .9rem .6rem .9rem}
  .menu-head .mini{width:42px;height:42px;border-radius:999px;object-fit:cover;border:1px solid #eef2f7}
  .menu-head .name{font-weight:800}
  .menu-head .meta{font-size:.86rem;color:var(--muted)}
  .menu-body{padding:.5rem}
  .menu a{display:flex;align-items:center;gap:.65rem;padding:.75rem .9rem;color:var(--ink);border-radius:12px;font-weight:800}
  .menu a:hover{background:#f3f7ff}
  .menu a:focus-visible{outline:none;box-shadow:var(--ring)}
  .menu .danger{color:var(--danger-500)}
  .menu hr{border:none;height:1px;background:#eef2f7;margin:.35rem .65rem}

  /* Compact hamburger */
  .hamburger{display:none;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;cursor:pointer;width:40px;height:40px;border-radius:12px;position:relative;isolation:isolate;box-shadow:inset 0 1px 0 rgba(255,255,255,.25),0 10px 20px rgba(0,0,0,.10);transition:transform .12s ease,background .12s ease,border-color .12s ease}
  .hamburger:hover{transform:translateY(-1px);background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.35)}
  .hamburger:focus-visible{outline:none;box-shadow:var(--ring)}
  .bar{position:absolute;left:50%;width:20px;height:2.5px;background:#fff;border-radius:2px;transform:translateX(-50%);transition:transform .25s cubic-bezier(.2,.6,.2,1),width .25s,opacity .2s}
  .bar.top{top:12px;width:18px}
  .bar.mid{top:19px;width:14px;opacity:.95}
  .bar.bot{top:26px;width:10px}
  .hamburger.open .bar.top{transform:translate(-50%,7px) rotate(45deg);width:22px}
  .hamburger.open .bar.mid{opacity:0}
  .hamburger.open .bar.bot{transform:translate(-50%,-7px) rotate(-45deg);width:22px}

  /* Overlay + Drawer */
  .overlay{position:fixed;inset:0;background:rgba(16,24,40,.45);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .18s ease;z-index:1001;contain:paint}
  .overlay.show{opacity:1;pointer-events:auto}

  /* Drawer uses transform so width is independent of position */
  .drawer{
    position:fixed;top:0;right:0;
    width:360px; /* desktop/tablet default */
    height:100vh;height:100dvh;
    display:flex;flex-direction:column;background:var(--card);
    z-index:1002;box-shadow:-16px 0 40px rgba(16,24,40,.22);
    border-radius:16px 0 0 16px;will-change:transform;contain:paint;
    transform:translateX(105%); /* hidden off canvas */
    transition:transform .22s ease;
  }
  .drawer.open{transform:translateX(0)}

  /* Medium width on mobile (looks "just right") */
  @media (max-width:920px){
    .nav-center{display:none}
    .hamburger{display:inline-flex;align-items:center;justify-content:center}
    .profile-wrap{display:none}
    .drawer{
      width:min(var(--drawer-mobile-max), var(--drawer-mobile-w));
    }
  }

  .drawer-head{position:sticky;top:0;z-index:1;background:linear-gradient(180deg,#fff,#fafcff);padding:1rem 1rem calc(1rem + env(safe-area-inset-top));border-bottom:1px solid #eef2f7;display:flex;align-items:center;gap:.75rem}
  .drawer-avatar{width:52px;height:52px;border-radius:999px;border:3px solid #eef2ff;object-fit:cover;box-shadow:0 4px 16px rgba(0,0,0,.05)}
  .drawer-name{font-weight:800}
  .drawer-sub{opacity:.7;font-size:.9rem}

  .drawer-body{padding:1rem;display:flex;flex-direction:column;gap:.8rem}
  .btn{display:flex;align-items:center;justify-content:center;gap:.5rem;border:none;border-radius:12px;padding:.75rem .9rem;font-weight:800;cursor:pointer;transition:transform .1s ease,filter .1s ease}
  .btn:hover{transform:translateY(-1px)}
  .btn.primary{background:linear-gradient(180deg,var(--primary-700),var(--primary-600));color:#fff}
  .btn.primary:hover{filter:brightness(1.03)}
  .btn.danger{background:var(--danger-500);color:#fff}
  .btn.danger:hover{filter:brightness(.97)}

  .drawer-section{margin-top:.2rem}
  .section-title{font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin:2px 2px 6px 2px}

  .drawer-nav{display:flex;flex-direction:column;gap:.3rem}
  .nav-item{display:flex;align-items:center;gap:.65rem;padding:.7rem .8rem;border-radius:14px;color:var(--ink);font-weight:750;transition:background .12s ease,transform .12s ease}
  .nav-item:hover{background:#f3f6fd;transform:translateX(1px)}
  .nav-item .icon{flex:0 0 22px;display:grid;place-items:center;opacity:.9}
  .nav-item .chev{margin-left:auto;opacity:.35}
  .nav-item.active{background:linear-gradient(180deg,#eef5ff,#e8f0ff);color:var(--primary-700);border:1px solid #e5ecff;box-shadow:0 1px 0 #fff inset}
  .nav-item.active::before{content:"";width:3px;height:60%;border-radius:6px;background:var(--primary-600);margin-right:.35rem}

  .drawer-spacer{flex:1}
  .drawer-footer{padding:1rem calc(1rem + env(safe-area-inset-right));border-top:1px solid #eef2f7;color:#94a3b8;font-size:.82rem}

  @media (prefers-reduced-motion: reduce){ *{transition:none !important;animation:none !important} }
</style>

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

<script>
(() => {
  const header   = document.getElementById('proHeader');
  const avatarBtn= document.getElementById('avatarBtn');
  const menu     = document.getElementById('profileMenu');
  const burger   = document.getElementById('hamburgerBtn');
  const drawer   = document.getElementById('drawer');
  const overlay  = document.getElementById('overlay');
  const logoutM  = document.getElementById('logoutBtnMobile');

  const onScroll = () => header.classList.toggle('elevated', (window.scrollY||document.documentElement.scrollTop) > 6);
  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});

  /* profile menu */
  const menuLinks = () => menu ? [...menu.querySelectorAll('a[href], button:not([disabled])')] : [];
  const outsideClose = (e) => { if (menu && avatarBtn && !menu.contains(e.target) && !avatarBtn.contains(e.target)) closeMenu(); };
  const keyClose = (e) => {
    if (e.key === 'Escape') { closeMenu(); avatarBtn?.focus(); }
    if (e.key === 'Tab' && menu?.classList.contains('open')) {
      const items = menuLinks(); if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    }
  };
  function openMenu(){ if (!menu||!avatarBtn) return; menu.classList.add('open'); avatarBtn.classList.add('open'); avatarBtn.setAttribute('aria-expanded','true'); const items=menuLinks(); items.length&&items[0].focus(); document.addEventListener('click',outsideClose,true); document.addEventListener('keydown',keyClose,true); }
  function closeMenu(){ if (!menu||!avatarBtn) return; menu.classList.remove('open'); avatarBtn.classList.remove('open'); avatarBtn.setAttribute('aria-expanded','false'); document.removeEventListener('click',outsideClose,true); document.removeEventListener('keydown',keyClose,true); }
  avatarBtn?.addEventListener('click',(e)=>{ e.stopPropagation(); menu?.classList.contains('open') ? closeMenu() : openMenu(); });

  /* drawer */
  let scrollY = 0;
  const lockScroll   = () => { scrollY = window.scrollY||document.documentElement.scrollTop; document.body.style.position='fixed'; document.body.style.top = `-${scrollY}px`; document.body.style.width='100%'; };
  const unlockScroll = () => { document.body.style.position=''; document.body.style.top=''; document.body.style.width=''; window.scrollTo(0, scrollY); };

  const escDrawer = (e) => { if (e.key === 'Escape') closeDrawer(); };
  const clickOutsideDrawer = (e) => { if (!drawer?.classList.contains('open')) return; if (!drawer.contains(e.target) && !burger.contains(e.target)) closeDrawer(); };

  function openDrawer(){
    drawer.classList.add('open'); drawer.setAttribute('aria-hidden','false');
    burger.classList.add('open'); burger.setAttribute('aria-expanded','true'); burger.setAttribute('aria-label','Close menu');
    overlay.classList.add('show'); overlay.hidden = false; lockScroll();
    document.addEventListener('keydown', escDrawer, true);
    document.addEventListener('click', clickOutsideDrawer, true);
  }
  function closeDrawer(){
    drawer.classList.remove('open'); drawer.setAttribute('aria-hidden','true');
    burger.classList.remove('open'); burger.setAttribute('aria-expanded','false'); burger.setAttribute('aria-label','Open menu');
    overlay.classList.remove('show'); overlay.hidden = true; unlockScroll();
    document.removeEventListener('keydown', escDrawer, true);
    document.removeEventListener('click', clickOutsideDrawer, true);
  }

  burger?.addEventListener('click', () => (drawer.classList.contains('open') ? closeDrawer() : openDrawer()));
  overlay?.addEventListener('click', closeDrawer);
  document.querySelectorAll('.drawer a').forEach(a => a.addEventListener('click', closeDrawer));
  window.addEventListener('resize', () => { closeDrawer(); closeMenu(); });

  /* logout */
  function doLogout(){ window.location.href='../../auth/logout.php'; }
  async function confirmLogout(){
    if (window.Swal && typeof Swal.fire === 'function') {
      const res = await Swal.fire({
        title:'Sign out?', text:'You can sign back in anytime.', icon:'warning',
        showCancelButton:true, confirmButtonText:'Logout', cancelButtonText:'Cancel', reverseButtons:true,
        confirmButtonColor:'#ef4444', cancelButtonColor:'#334155', background:'#ffffff', color:'#0f172a'
      });
      if (res.isConfirmed) doLogout();
    } else { if (confirm('Are you sure you want to log out?')) doLogout(); }
  }
  document.getElementById('logoutLink')?.addEventListener('click',(e)=>{ e.preventDefault(); confirmLogout(); });
  logoutM?.addEventListener('click',(e)=>{ e.preventDefault(); confirmLogout(); });
})();
</script>
<!-- ========== HEADER END ========== -->
