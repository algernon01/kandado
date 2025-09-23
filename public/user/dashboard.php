<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

/** ===================== LAN GUARD (add once at the top) ===================== */
function effective_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    // If request comes through a local proxy (ngrok -> 127.0.0.1), trust X-Forwarded-For
    if ($remote === '127.0.0.1' || $remote === '::1') {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xff = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            return trim($xff);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return trim($_SERVER['HTTP_X_REAL_IP']);
        }
    }
    // If ever behind Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    return $remote ?: '';
}

function is_lan_ip(string $ip): bool {
    if ($ip === '') return false;
    if (stripos($ip, '::ffff:') === 0) $ip = substr($ip, 7); // IPv4-mapped IPv6

    // IPv4
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip)) {
        if (strpos($ip, '10.') === 0) return true;
        if (strpos($ip, '192.168.') === 0) return true;
        if (strpos($ip, '172.') === 0) {
            $second = (int) explode('.', $ip)[1];
            if ($second >= 16 && $second <= 31) return true; // 172.16.0.0/12
        }
        if ($ip === '127.0.0.1') return true; // loopback
        return false;
    }

    // IPv6 (loopback, ULA, link-local)
    $low = strtolower($ip);
    if ($low === '::1') return true;
    if (strpos($low, 'fc') === 0 || strpos($low, 'fd') === 0) return true; // fc00::/7
    if (strpos($low, 'fe80:') === 0) return true; // link-local
    return false;
}

function is_mutating_request(): bool {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST') {
        if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        } elseif (!empty($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
    }
    return !in_array($method, ['GET','HEAD','OPTIONS'], true);
}

$clientIp    = effective_client_ip();
$host        = strtolower($_SERVER['HTTP_HOST'] ?? '');
$isLan       = is_lan_ip($clientIp);
$isNgrokHost = (strpos($host, 'ngrok') !== false); // matches *.ngrok.io / *.ngrok-free.app

// Read-only when NOT on LAN or when accessed via an ngrok host
$READ_ONLY_BY_NETWORK = $isNgrokHost || !$isLan;

// Hard-block any writes (POST/PUT/PATCH/DELETE) when off-LAN
if ($READ_ONLY_BY_NETWORK && is_mutating_request()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Writes are disabled when not on the LAN. Connect to the local network to reserve.");
}
/** =================== END LAN GUARD =================== */

$TOTAL_LOCKERS = 4;

$IS_ON_HOLD = null;
if (isset($_SESSION['on_hold'])) {
  $IS_ON_HOLD = (bool)$_SESSION['on_hold'];
} else {
  $hostDb = 'localhost'; $dbname = 'kandado'; $user = 'root'; $pass = '';
  $IS_ON_HOLD = false;
  try {
    $conn = new mysqli($hostDb, $user, $pass, $dbname);
    if (!$conn->connect_error) {
      $conn->set_charset('utf8mb4');
      $stmt = $conn->prepare("SELECT archived FROM users WHERE id = ? LIMIT 1");
      $stmt->bind_param('i', $_SESSION['user_id']);
      $stmt->execute();
      $res = $stmt->get_result()->fetch_assoc();
      $IS_ON_HOLD = isset($res['archived']) && (int)$res['archived'] === 1;
      $_SESSION['on_hold'] = $IS_ON_HOLD;
      $stmt->close(); $conn->close();
    }
  } catch (\Throwable $e) { $IS_ON_HOLD = false; }
}

// Combine account hold + network read-only for the UI lock
$IS_READ_ONLY = $IS_ON_HOLD || $READ_ONLY_BY_NETWORK;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="color-scheme" content="light" />
  <meta name="theme-color" content="#ffffff" />
  <title>Locker Dashboard • Kandado</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/kandado/assets/css/users_dashboard.css">
  <link rel="icon" href="../../assets/icon/icon_tab.png" sizes="any">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Read-only visuals -->
  <style>
    .hold-banner{
      margin:12px auto; max-width:1100px; padding:12px 14px;
      border-radius:12px; border:1px solid #fed7aa; background:#fff7ed; color:#b45309;
      font:600 14px/1.4 'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      display:flex; align-items:flex-start; gap:10px;
    }
    .hold-banner .icon{
      display:inline-flex; align-items:center; justify-content:center;
      width:28px; height:28px; border-radius:50%; background:#fde7cc; border:1px solid #fed7aa; flex-shrink:0;
    }
    .hold-chip{
      display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .55rem; border-radius:999px;
      background:#fff7ed; border:1px solid #fed7aa; color:#b45309; font:800 12px/1 'Inter',system-ui;
    }
    .disabled-link{ opacity:.55; pointer-events:none; }

    /* Only dim CONTENT areas, never the header (so Top Up stays crisp) */
    .is-locked [data-lockable]:not(.page-header){
      opacity:.55; filter:saturate(.7);
    }

    /* Inline (non-sticky) badge under the banner that doesn't scroll with the page header */
    .lock-inline-row{
      margin:-6px auto 10px;
      max-width:1100px;
      display:flex;
      justify-content:center;
      pointer-events:none;
    }
    .lock-inline-row .badge{
      pointer-events:auto;
      background:#fff7ed; border:1px solid #fed7aa; color:#b45309;
      border-radius:999px; padding:.45rem .8rem; font:800 13px/1 'Inter',system-ui;
      box-shadow:0 8px 24px rgba(180,83,9,.12);
    }

    @media (max-width:768px){
      .hold-chip{ display:none; }
      .lock-inline-row{ margin:-4px auto 8px; }
    }
  </style>
</head>
<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/kandado/includes/user_header.php'; ?>

  <main class="container <?= $IS_READ_ONLY ? 'is-locked' : '' ?>" role="main">
    <?php if ($IS_READ_ONLY): ?>
      <!-- Read-only banner -->
      <div class="hold-banner" role="alert" aria-live="assertive">
        <span class="icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 11V6a2 2 0 1 1 4 0v4h1V4a2 2 0 1 1 4 0v7h1V7a2 2 0 1 1 4 0v9a6 6 0 0 1-6 6h-2a7 7 0 0 1-7-7v-4h1z"/></svg>
        </span>
        <div>
          <div><strong><?= $IS_ON_HOLD ? 'Your account is on hold.' : 'Read-only mode.' ?></strong> Actions are disabled<?= $IS_ON_HOLD ? '' : ' when not on the LAN' ?>.</div>
          <?php if ($IS_ON_HOLD): ?>
            <div style="opacity:.8; font-weight:600; margin-top:2px;">Please contact support if you believe this is a mistake.</div>
          <?php else: ?>
            <div style="opacity:.8; font-weight:600; margin-top:2px;">Connect to the local network to avail.</div>
          <?php endif; ?>
        </div>
      </div>
      <!-- Inline (non-sticky) badge just under the banner -->
      <div class="lock-inline-row" aria-hidden="true">
        <div class="badge"><?= $IS_ON_HOLD ? 'On-hold mode: read-only' : 'Public access: read-only' ?></div>
      </div>
    <?php endif; ?>

    <!-- ======= HEADER (kept bright & clickable) ======= -->
    <header class="page-header" role="region" aria-label="Locker dashboard controls" data-lockable>
      <div class="title-wrap">
        <h2>
          Locker Dashboard
          <?php if ($IS_READ_ONLY): ?><span class="hold-chip"><?= $IS_ON_HOLD ? 'On&nbsp;Hold' : 'Read-only' ?></span><?php endif; ?>
        </h2>
        <div class="legend" aria-hidden="true">
          <span class="pill available"><span class="dot"></span>Available</span>
          <span class="pill occupied"><span class="dot"></span>Occupied</span>
          <span class="pill hold"><span class="dot"></span>On&nbsp;Hold</span>
          <span class="pill maintenance"><span class="dot"></span>Maintenance</span>
        </div>
      </div>

      <!-- RIGHT COLUMN: Refresh + Wallet -->
      <div class="toolbar">
        <div class="toolbar-row">
          <button id="refreshBtn" class="btn" type="button" title="Refresh now" aria-controls="lockerGrid">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 5v4h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 13a8.1 8.1 0 0 0 15.5 2M20 19v-4h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Refresh
          </button>
          <span id="lastUpdated" class="last-updated" aria-live="polite">—</span>
        </div>

        <div id="walletWidget" class="wallet-widget">
          <div class="wallet-card" aria-live="polite">
            <div class="wallet-left">
              <span class="wallet-icon" aria-hidden="true">💳</span>
              <span class="wallet-title">Wallet</span>
            </div>
            <div class="wallet-right">
              <span id="walletBalanceValue" class="wallet-balance">₱0.00</span>
            </div>
          </div>
          <a href="/kandado/public/user/topup.php" class="btn btn-primary wallet-topup-btn" aria-label="Top up wallet">Top Up</a>
        </div>
      </div>
    </header>

    <div id="offlineBanner" class="offline hidden" role="alert" aria-live="assertive" data-lockable>
      You’re offline. Data may be out of date.
    </div>

    <section class="overview" aria-label="Locker overview" data-lockable>
      <div class="overview-grid">
        <article class="kpi kpi-occupancy" aria-label="Occupancy">
          <div class="donut" id="occDonut" style="--p:0; --c: var(--occ-green);" aria-live="polite" aria-label="Occupancy 0%">
            <div class="donut-label" id="kpiOcc">0%</div>
          </div>
          <div class="kpi-meta">
            <div class="kpi-title">Occupancy</div>
            <div class="kpi-sub" id="kpiOccSub">0 in use • 0 available</div>
          </div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Total Lockers</div>
          <div class="kpi-value" id="kpiTotal"><?= (int)$TOTAL_LOCKERS ?></div>
          <div class="kpi-sub">All branches</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Available</div>
          <div class="kpi-value" id="kpiAvailable">0</div>
          <div class="kpi-sub">Ready to avail</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Occupied</div>
          <div class="kpi-value" id="kpiOccupied">0</div>
          <div class="kpi-sub">In use now</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">On Hold</div>
          <div class="kpi-value" id="kpiHold">0</div>
          <div class="kpi-sub">Item inside</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Maintenance</div>
          <div class="kpi-value" id="kpiMaintenance">0</div>
          <div class="kpi-sub">Unavailable (service)</div>
        </article>
      </div>
    </section>

    <section class="filter-bar" aria-label="Filters" data-lockable>
      <div class="segmented" role="tablist" aria-label="Status filter">
        <button class="seg active" id="filterAll" data-filter="all" role="tab" aria-selected="true">All</button>
        <button class="seg" id="filterAvailable" data-filter="available" role="tab" aria-selected="false">Available</button>
        <button class="seg" id="filterOccupied" data-filter="occupied" role="tab" aria-selected="false">Occupied</button>
        <button class="seg" id="filterHold" data-filter="hold" role="tab" aria-selected="false">On Hold</button>
        <button class="seg" id="filterMaintenance" data-filter="maintenance" role="tab" aria-selected="false">Maintenance</button>
      </div>

      <div class="search">
        <label for="searchInput" class="sr-only">Find locker number</label>
        <input id="searchInput" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Find locker # (e.g., 3)" />
        <button id="clearSearch" type="button" class="clear" aria-label="Clear search">×</button>
      </div>
    </section>

    <section id="lockerGrid" class="grid" aria-live="polite" aria-busy="true" data-lockable></section>
    <div id="emptyState" class="empty-state hidden" aria-live="polite" data-lockable>
      <div class="empty-icon" aria-hidden="true">🔎</div>
      <div class="empty-title">No lockers match your filters</div>
      <div class="empty-sub">Try changing the status or clearing the search.</div>
    </div>
  </main>

  <script>
    window.DASHBOARD = {
      totalLockers: <?= (int)$TOTAL_LOCKERS ?>,
      onHold: <?= $IS_READ_ONLY ? 'true' : 'false' ?>
    };

    (function lockDownIfOnHold(){
      if (!window.DASHBOARD.onHold) return;

      const isTopUp = (el) => !!(el && (el.classList?.contains('wallet-topup-btn') || el.closest?.('#walletWidget')));

      // 1) Disable common controls EXCEPT Top Up
      const disableEl = (el) => {
        if (!el || isTopUp(el)) return;
        const tag = el.tagName;
        if (['BUTTON','INPUT','SELECT','TEXTAREA'].includes(tag)) {
          el.disabled = true;
          el.setAttribute('aria-disabled','true');
        } else if (tag === 'A') {
          if (el.hasAttribute('href')) { el.dataset.href = el.getAttribute('href'); el.removeAttribute('href'); }
          el.classList.add('disabled-link');
          el.setAttribute('aria-disabled','true');
          el.tabIndex = -1;
        }
      };

      const root = document.querySelector('main.container');
      const interactiveSelectors = [
        'button', 'a.btn', /* leave .wallet-topup-btn enabled */
        '.segmented .seg',
        '#searchInput', '#clearSearch', '#lockerGrid button', '#lockerGrid a',
        'input', 'select', 'textarea', '[role="tab"]', '[type="submit"]'
      ];
      interactiveSelectors.forEach(sel => root.querySelectorAll(sel).forEach(disableEl));

      // 2) Intercept form submissions
      document.addEventListener('submit', function(e){
        if (!window.DASHBOARD.onHold) return;
        e.preventDefault(); e.stopImmediatePropagation();
        Swal.fire({ icon:'info', title:'Read-only mode', text:'Actions are disabled right now.', confirmButtonColor:'#0d5ef4' });
      }, true);

      // 3) Intercept clicks, but ALLOW Top Up and header nav
      document.addEventListener('click', function(e){
        if (!window.DASHBOARD.onHold) return;
        const t = e.target.closest('button, a, [role="button"], [role="tab"]');
        if (!t) return;
        if (t.closest('header') && !t.closest('.page-header')) return; // top global header
        if (isTopUp(t)) return; // allow Top Up
        e.preventDefault(); e.stopImmediatePropagation();
        Swal.fire({ icon:'info', title:'Read-only mode', text:'Actions are disabled right now.', confirmButtonColor:'#0d5ef4' });
      }, true);

      // 4) Intercept fetch() that tries to modify state (anything not GET)
      const origFetch = window.fetch.bind(window);
      window.fetch = function(resource, init){
        try {
          const method = (init && (init.method || (init.headers && init.headers['X-HTTP-Method-Override']))) || 'GET';
          if (String(method).toUpperCase() !== 'GET') {
            return Promise.reject(new Error('Read-only — write operations blocked'));
          }
        } catch(_){}
        return origFetch(resource, init);
      };

      // 5) Intercept XHR (legacy)
      const origOpen = XMLHttpRequest.prototype.open;
      XMLHttpRequest.prototype.open = function(method, url){
        this.__method = method;
        return origOpen.apply(this, arguments);
      };
      const origSend = XMLHttpRequest.prototype.send;
      XMLHttpRequest.prototype.send = function(body){
        if ((this.__method||'GET').toUpperCase() !== 'GET') {
          this.abort();
          Swal.fire({icon:'info', title:'Read-only mode', text:'Actions are disabled right now.', confirmButtonColor:'#0d5ef4'});
          return;
        }
        return origSend.apply(this, arguments);
      };

      // 6) Make content regions inert, but KEEP the page header interactive (Top Up lives here)
      document.querySelectorAll('[data-lockable]:not(.page-header)').forEach(el => el.setAttribute('inert',''));
    })();
  </script>

  <script src="/kandado/assets/js/user_dashboard.js?v=1"></script>
</body>
</html>
