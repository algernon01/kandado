<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
date_default_timezone_set('Asia/Manila');

/* ====================== Helpers (network gating like your original) ====================== */
function effective_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote === '127.0.0.1' || $remote === '::1') {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xff = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            return trim($xff);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return trim($_SERVER['HTTP_X_REAL_IP']);
        }
    }
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    return $remote ?: '';
}
function is_lan_ip(string $ip): bool {
    if ($ip === '') return false;
    if (stripos($ip, '::ffff:') === 0) $ip = substr($ip, 7);

    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip)) {
        if (strpos($ip, '10.') === 0) return true;
        if (strpos($ip, '192.168.') === 0) return true;
        if (strpos($ip, '172.') === 0) {
            $second = (int) explode('.', $ip)[1];
            if ($second >= 16 && $second <= 31) return true;
        }
        if ($ip === '127.0.0.1') return true;
        return false;
    }

    $low = strtolower($ip);
    if ($low === '::1') return true;
    if (strpos($low, 'fc') === 0 || strpos($low, 'fd') === 0) return true;
    if (strpos($low, 'fe80:') === 0) return true;
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

/* ====================== Network read-only gate (same behavior as before) ====================== */
$clientIp    = effective_client_ip();
$host        = strtolower($_SERVER['HTTP_HOST'] ?? '');
$isLan       = is_lan_ip($clientIp);
$isNgrokHost = (strpos($host, 'ngrok') !== false);
$READ_ONLY_BY_NETWORK = $isNgrokHost || !$isLan;

/* If someone tries to POST from outside LAN, block it right here */
if ($READ_ONLY_BY_NETWORK && is_mutating_request()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Writes are disabled when not on the LAN. Connect to the local network to reserve.");
}

/* ====================== BAN lookup (DB) ====================== */
$TOTAL_LOCKERS = 4;

$hostDb = 'localhost'; $dbname = 'kandado'; $user = 'root'; $pass = '';
$BAN = ['active'=>false,'until'=>null,'permanent'=>false,'offenses'=>0,'reason'=>null];

try {
    $conn = new mysqli($hostDb, $user, $pass, $dbname);
    if ($conn->connect_errno) throw new Exception($conn->connect_error);
    $conn->set_charset('utf8mb4');

    // fetch user_bans
    $stmt = $conn->prepare("SELECT offense_count, banned_until, is_permanent FROM user_bans WHERE user_id=? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $isActive = ((int)$row['is_permanent'] === 1) ||
                    (!empty($row['banned_until']) && strtotime($row['banned_until']) > time());

        $BAN['active']    = $isActive;
        $BAN['until']     = $row['banned_until'];
        $BAN['permanent'] = ((int)$row['is_permanent'] === 1);
        $BAN['offenses']  = (int)$row['offense_count'];

        if ($isActive) {
            $stmt2 = $conn->prepare("
                SELECT details
                FROM violation_events
                WHERE user_id=? AND event IN ('ban_1d','ban_3d','ban_perm')
                ORDER BY id DESC LIMIT 1
            ");
            $stmt2->bind_param('i', $_SESSION['user_id']);
            $stmt2->execute();
            $ev = $stmt2->get_result()->fetch_assoc();
            $BAN['reason'] = $ev['details'] ?? 'Repeated locker holds.';
        }
    }
    $conn->close();
} catch (\Throwable $e) {
    // if DB fails, leave BAN inactive (better to allow UI but API will still guard generate/extend)
}

$IS_BANNED = $BAN['active'];
$BAN_UNTIL_MS = 0;
if (!empty($BAN['until'])) {
  $dt = new DateTime($BAN['until'], new DateTimeZone('Asia/Manila'));
  $BAN_UNTIL_MS = $dt->getTimestamp() * 1000; // epoch in ms
}

/* Final UI lock flag: banned OR network read-only */
$IS_READ_ONLY = $IS_BANNED || $READ_ONLY_BY_NETWORK;
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
  <link rel="icon" href="/kandado/assets/icon/icon_tab.png" sizes="any">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body
  class="<?= $IS_BANNED ? 'banned' : '' ?>"
  data-dashboard="1"
  data-total-lockers="<?= (int)$TOTAL_LOCKERS ?>"
  data-on-hold="<?= $IS_READ_ONLY ? 'true' : 'false' ?>"
  data-ban-active="<?= $IS_BANNED ? 'true' : 'false' ?>"
  data-ban-permanent="<?= $BAN['permanent'] ? 'true' : 'false' ?>"
  data-ban-until-ms="<?= (int)$BAN_UNTIL_MS ?>"
  data-ban-reason="<?= htmlspecialchars($BAN['reason'] ?? 'Repeated locker holds.', ENT_QUOTES, 'UTF-8') ?>"
>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/kandado/includes/user_header.php'; ?>

  <main class="container <?= $IS_READ_ONLY ? 'is-locked' : '' ?>" role="main">
    <?php if ($IS_BANNED): ?>
      <!-- BAN banner -->
      <div class="hold-banner" role="alert" aria-live="assertive">
        <span class="icon" aria-hidden="true">🚫</span>
        <div>
          <div><strong>Your account is banned.</strong></div>
          <div style="opacity:.85; font-weight:600; margin-top:2px;">
            Reason: <?= htmlspecialchars($BAN['reason'] ?? 'Repeated locker holds.', ENT_QUOTES, 'UTF-8') ?><br/>
            <?php if ($BAN['permanent']): ?>
              Status: <b>Permanent</b>
            <?php else: ?>
              Ends: <b><?= htmlspecialchars(date('F j, Y h:i A', strtotime($BAN['until']))) ?></b>
              <span id="banCountdown" style="margin-left:6px;"></span>
            <?php endif; ?>
          </div>
          <div style="opacity:.8; margin-top:6px;">
            Please wait until the ban expires, or contact an admin for review.
          </div>
        </div>
      </div>
      <div class="lock-inline-row" aria-hidden="true">
        <div class="badge">Banned: actions disabled</div>
      </div>
    <?php elseif ($READ_ONLY_BY_NETWORK): ?>
      <!-- Read-only (public/not on LAN) banner -->
      <div class="hold-banner" role="alert" aria-live="assertive">
        <span class="icon" aria-hidden="true">ℹ️</span>
        <div>
          <div><strong>Read-only mode.</strong> Connect to the local network to avail.</div>
          <div style="opacity:.8; font-weight:600; margin-top:2px;">Actions are disabled while accessing remotely.</div>
        </div>
      </div>
      <div class="lock-inline-row" aria-hidden="true">
        <div class="badge">Public access: read-only</div>
      </div>
    <?php endif; ?>

    <!-- Header / KPIs / Wallet -->
    <header class="page-header" role="region" aria-label="Locker dashboard controls" data-lockable>
      <div class="title-wrap">
        <h2>
          Locker Dashboard
          <?php if ($IS_READ_ONLY): ?><span class="hold-chip"><?= $IS_BANNED ? 'Banned' : 'Read-only' ?></span><?php endif; ?>
        </h2>
        <div class="legend" aria-hidden="true">
          <span class="pill available"><span class="dot"></span>Available</span>
          <span class="pill occupied"><span class="dot"></span>Occupied</span>
          <span class="pill hold"><span class="dot"></span>On&nbsp;Hold</span>
          <span class="pill maintenance"><span class="dot"></span>Maintenance</span>
        </div>
      </div>

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

  <script src="/kandado/assets/js/user_dashboard.js?v=1"></script>
</body>
</html>
