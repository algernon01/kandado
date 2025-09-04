<?php
// ===============================
// Admin Dashboard (UI Redesign Only)
// ===============================
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include your header + sidebar (unchanged)
include '../../includes/admin_header.php';

// --- DB (mysqli) ---
$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// --- Summary counts ---
$total_lockers      = (int)$conn->query("SELECT COUNT(*) AS total FROM locker_qr")->fetch_assoc()['total'];
$occupied_lockers   = (int)$conn->query("SELECT COUNT(*) AS occupied FROM locker_qr WHERE status='occupied'")->fetch_assoc()['occupied'];
$available_lockers  = (int)$conn->query("SELECT COUNT(*) AS available FROM locker_qr WHERE status='available'")->fetch_assoc()['available'];
$total_users        = (int)$conn->query("SELECT COUNT(*) AS total_users FROM users")->fetch_assoc()['total_users'];

// --- Locker details ---
$locker_sql = "
    SELECT
        l.locker_number, l.status, l.code, l.item, l.expires_at, l.duration_minutes,
        u.first_name, u.last_name, u.email
    FROM locker_qr l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.locker_number ASC
";
$locker_result = $conn->query($locker_sql);

// --- Available lockers for selects ---
$available_result = $conn->query("SELECT locker_number FROM locker_qr WHERE status='available'");

// --- Locker usage (last 7 days) with zero-fill ---
$usage_rs = $conn->query("
    SELECT DATE(used_at) AS day, COUNT(*) AS usage_count
    FROM locker_history
    WHERE used_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(used_at)
    ORDER BY DATE(used_at) ASC
");
$usage_map = [];
if ($usage_rs) {
    while ($r = $usage_rs->fetch_assoc()) {
        $usage_map[$r['day']] = (int)$r['usage_count'];
    }
}
$dates = [];
$usage_counts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $dates[] = $d;
    $usage_counts[] = isset($usage_map[$d]) ? $usage_map[$d] : 0;
}
if ($usage_rs) $usage_rs->free();

// For charts
$occupied = $occupied_lockers;
$available = $available_lockers;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title>Admin Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* =========================================
   Fresh, Professional Admin UI (Light Only)
   - Mobile-first, highly responsive
   - No functionality changed
========================================= */
:root{
  /* Brand + Semantic */
  --brand-600:#2c5cff;
  --brand-500:#4974ff;
  --brand-400:#6a8bff;
  --brand-50:#eef3ff;

  --ok-600:#10b981;   /* emerald */
  --warn-600:#f59e0b; /* amber */
  --danger-600:#ef4444; /* red */
  --muted:#6b7280;

  /* Surfaces */
  --bg:#f6f8fb;
  --card:#ffffff;
  --card-2:#fbfcff;
  --stroke:#e7ecf5;

  /* Typography */
  --ink:#0f172a;
  --ink-2:#1f2937;

  /* Radius + Shadows */
  --r-lg:20px;
  --r-md:14px;
  --r-sm:10px;
  --shadow-1: 0 6px 18px rgba(16, 24, 40, .08);
  --shadow-2: 0 10px 30px rgba(16, 24, 40, .12);
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  background:var(--bg);
  color:var(--ink);
  line-height:1.5;
}

/* Respect existing header/sidebar from include */
main#content{
  margin-top: var(--header-h, 60px);
  margin-left: var(--sidebar-w, 280px);
  padding: clamp(16px, 2.5vw, 28px);
  min-height: calc(100vh - var(--header-h, 60px));
}
@media (max-width: 980px){
  main#content{ margin-left: 0; }
}

/* Page header bar */
.page-head{
  display:flex; align-items:center; justify-content:space-between;
  gap:12px; margin-bottom:18px;
}
.page-title{
  font-size: clamp(20px, 2.2vw, 28px);
  font-weight:800;
  letter-spacing:.2px;
}
.page-actions{
  display:flex; gap:8px; flex-wrap:wrap;
}
.tag{
  display:inline-flex; align-items:center; gap:8px;
  padding:8px 12px; background:var(--card);
  border:1px solid var(--stroke); border-radius:999px; box-shadow:var(--shadow-1);
  font-weight:700; color:#334155;
}

/* KPI cards – centered, semantic icons */
.kpis{
  display:grid;
  grid-template-columns: repeat(12, 1fr);
  gap:12px;
  margin: 8px 0 22px;
}
.kpi{
  grid-column: span 3;
  min-width: 220px;
  display:flex; flex-direction:column; align-items:center; text-align:center; gap:12px;
  background:linear-gradient(180deg, var(--card), var(--card-2));
  border:1px solid var(--stroke);
  border-radius: var(--r-lg);
  padding:20px 18px;
  box-shadow:var(--shadow-1);
}
.kpi .ico{
  width:56px; height:56px; display:grid; place-items:center;
  border-radius:14px; font-size:20px; font-weight:700;
}
.kpi .meta{ display:flex; flex-direction:column; align-items:center; }
.kpi .val{ font-size: clamp(22px, 2.6vw, 30px); font-weight:800; letter-spacing:.2px; line-height:1; }
.kpi .lbl{ font-size: 13px; color:var(--muted); font-weight:800; letter-spacing:.2px; text-transform:none; }

/* semantic color wrappers for icons */
.kpi--total .ico{ background:var(--brand-50); color:var(--brand-600); }
.kpi--occupied .ico{ background:#fef2f2; color:var(--danger-600); }
.kpi--available .ico{ background:#ecfdf5; color:var(--ok-600); }
.kpi--users .ico{ background:#eef2ff; color:#4338ca; }

@media (max-width: 1200px){ .kpi{ grid-column: span 6; } }
@media (max-width: 680px){ .kpi{ grid-column: span 12; } }

/* Main layout: two sections stack on mobile */
.section-grid{
  display:grid;
  grid-template-columns: 1.2fr 1fr;
  gap:16px;
}
@media (max-width: 1200px){ .section-grid{ grid-template-columns: 1fr; } }

/* Panel */
.panel{
  background:var(--card);
  border:1px solid var(--stroke);
  border-radius:var(--r-lg);
  box-shadow:var(--shadow-1);
  overflow:hidden;
}
.panel-head{
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 16px;
  background:#f9fbff;
  border-bottom:1px solid var(--stroke);
}
.panel-title{
  font-size:16px; font-weight:800; letter-spacing:.25px; color:#23324a;
}
.panel-body{ padding: 14px 16px; }

/* Locker grid (original baseline) */
.locker-grid{
  display:grid;
  grid-template-columns: repeat(12, 1fr);
  gap:12px;
}
.locker-card{
  grid-column: span 4;
  background: var(--card);
  border:1px solid var(--stroke);
  border-radius: var(--r-md);
  padding: 14px 12px 12px;
  position:relative;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.locker-card:hover{ transform: translateY(-2px); box-shadow:var(--shadow-2); border-color:#dbe3f3; }
@media (max-width: 1200px){ .locker-card{ grid-column: span 6; } }
@media (max-width: 680px){ .locker-card{ grid-column: span 12; } }

/* Status badge */
.status-badge{
  position:absolute; top:10px; right:10px;
  font-size:12px; font-weight:800; letter-spacing:.3px;
  padding:6px 10px; border-radius:999px; box-shadow:var(--shadow-1);
  color:#fff;
}
/* Make "Available" badge green */
.status-badge.available{
  background: linear-gradient(135deg, #16a34a, #2be670); /* green */
  color:#fff;
}
.status-badge.occupied{ background: linear-gradient(135deg, #ef4444, #f97316); }
.status-badge.hold{ background: linear-gradient(135deg, #f59e0b, #fde047); color:#1f2937; }

/* Locker header + meta */
.locker-top{ display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.locker-num{ font-weight:900; letter-spacing:.3px; font-size: 16px; }
.item-badge{
  display:inline-flex; align-items:center; gap:6px;
  padding:4px 8px; background:#fff7ed; color:#9a3412;
  border:1px solid #fed7aa; border-radius:8px; font-weight:800; font-size:12px;
}
.locker-details{
  background:#f8fafc; border:1px solid #e6eefb; border-radius:10px;
  padding:10px; font-size: 13px; color:#334155; margin-bottom:10px;
}
.locker-details p{ margin: 6px 0; }

/* Locker actions */
.btn-row{ display:flex; gap:8px; flex-wrap:wrap; }
.btn{
  appearance:none; border:none; cursor:pointer;
  display:inline-flex; align-items:center; gap:8px;
  padding:10px 12px; border-radius:10px; font-weight:800;
  transition: transform .2s ease, box-shadow .2s ease, background .2s ease, opacity .2s ease;
  box-shadow: var(--shadow-1);
}
.btn:focus-visible{ outline: 3px solid #bfd3ff; outline-offset:2px; }

/* Specific buttons */
.btn-reset{
  background: linear-gradient(135deg, var(--brand-600), var(--brand-400));
  color:#fff;
}
.btn-reset:hover{ transform:translateY(-1px); box-shadow:var(--shadow-2); }
.btn-release{
  background: linear-gradient(135deg, #fb923c, #f59e0b);
  color:#fff;
}
.btn-release:hover{ transform:translateY(-1px); box-shadow:var(--shadow-2); }

/* Highlight after forced unlock */
.locker-card.highlight{
  box-shadow: 0 0 0 3px rgba(251, 191, 36, .3), var(--shadow-2) !important;
}

/* Force unlock panel (unchanged) */
.force-grid{
  display:grid; grid-template-columns: repeat(12, 1fr); gap:12px;
}
.unlock-tile{
  grid-column: span 3;
  background:linear-gradient(180deg, var(--brand-600), var(--brand-400));
  border-radius:16px; color:#fff; text-align:center; padding:16px 12px;
  box-shadow:var(--shadow-2);
}
.unlock-tile h4{ margin:8px 0 10px; font-weight:900; letter-spacing:.3px; }
.unlock-action{
  margin-top:8px;
  background:#fff; color:var(--brand-600);
  border:none; border-radius:10px; width:100%;
  padding:10px 0; font-weight:900; cursor:pointer; box-shadow:var(--shadow-1);
}
.unlock-action:hover{ transform: translateY(-1px); }
@media (max-width: 1200px){ .unlock-tile{ grid-column: span 6; } }
@media (max-width: 680px){ .unlock-tile{ grid-column: span 12; } }

/* Unlock All: compact + centered */
.unlock-all{
  display:flex; align-items:center; justify-content:center; text-align:center;
  width: clamp(320px, 80%, 560px);
  margin: 12px auto 0;
  background: linear-gradient(180deg, #10b981, #34d399);
  border-radius:16px; color:#fff; padding: 18px 14px; box-shadow:var(--shadow-2);
}
#unlockAll:hover{ transform: translateY(-1px); }

/* Assign panel (unchanged layout, but we’ll validate via JS) */
.form-grid{
  display:grid; grid-template-columns: 1fr 1fr; gap:14px;
}
@media (max-width: 980px){ .form-grid{ grid-template-columns: 1fr; } }
.form-group label{
  display:block; font-weight:800; margin-bottom:8px; color:#0b2447;
}
.select, .input{
  width:100%; padding:12px 14px; border-radius:12px;
  border:1px solid #dbe3f3; background:#ffffff;
  font-size:14px; color:#0f172a; outline:none;
  transition: box-shadow .2s ease, border-color .2s ease;
}
.select:focus, .input:focus{
  border-color: var(--brand-500);
  box-shadow: 0 0 0 4px rgba(73,116,255,.15);
}
.submit-row{ display:flex; justify-content:flex-end; }
.btn-assign{
  background: linear-gradient(135deg, var(--brand-600), #6d28d9);
  color:#fff; border:none; border-radius:12px; padding:12px 18px; font-weight:900; cursor:pointer;
  box-shadow:var(--shadow-2);
}
.btn-assign:hover{ transform: translateY(-1px); }

/* User selector table */
.user-table-wrap{
  background:#f8fafc; border:1px solid #e6eefb; border-radius:14px; padding:12px;
}
.search-wrap{ position:relative; margin-bottom:10px; }
.search-wrap .fa-search{ position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#7c91c2; }
#userSearchInput{ padding-left:40px; width:100%; }
.user-table{
  width:100%; border-collapse: collapse; border-radius: 10px; overflow:hidden; font-size:14px;
}
.user-table th, .user-table td{ padding:12px; border-bottom:1px solid #e9edf7; text-align:left; }
.user-table th{ background:#eef2ff; color:#334155; font-weight:800; }
.user-table tr:hover{ background:#f1f5ff; cursor:pointer; }
.user-table tr.selected{ background: #2c5cff; color:#fff; }
#pagination{ margin-top:8px; display:flex; gap:6px; flex-wrap:wrap; justify-content:center; }
#pagination button{
  border:1px solid #d1dcff; background:#fff; padding:6px 10px; border-radius:8px; font-weight:800; cursor:pointer;
}
#pagination button.active{ background:#2c5cff; color:#fff; border-color:#2c5cff; }

/* Charts (unchanged) */
.charts{ display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; }
.chart-card{
  grid-column: span 6;
  background:var(--card); border:1px solid var(--stroke); border-radius:var(--r-lg);
  padding: 14px; box-shadow:var(--shadow-1);
}
.chart-title{ font-weight:800; margin: 6px 0 12px; color:#23324a; text-align:left; }
.chart-card canvas{ width:100% !important; max-height: 300px; }
@media (max-width: 980px){ .chart-card{ grid-column: span 12; } }

/* Utilities */
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; color:#475569; }
.small{ font-size:12px; color:#0c6b53 !important; opacity:1 !important;  }

/* =========================================
   ONLY Manage Lockers – Scoped UI Tweaks
   (Medium, user-friendly, responsive)
========================================= */
.panel[aria-label="Manage lockers"] .panel-title i{ margin-right:8px; }

.panel[aria-label="Manage lockers"] .locker-grid{
  /* auto-fit medium cards; looks good on desktop & phone */
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 14px;
  align-items: stretch;
}

.panel[aria-label="Manage lockers"] .locker-card{
  grid-column: auto !important;       /* ignore old 4/6/12 spans */
  padding: 16px 14px 12px;
  border-radius: var(--r-lg);
  min-height: 180px;                   /* compact but not cramped */
}

.panel[aria-label="Manage lockers"] .locker-top{
  gap: 12px;
  margin-bottom: 12px;
}
.panel[aria-label="Manage lockers"] .locker-num{ font-size: 16px; }
.panel[aria-label="Manage lockers"] .status-badge{
  top: 12px; right: 12px;
  font-size: 12.5px; padding: 6px 10px;
}
.panel[aria-label="Manage lockers"] .locker-details{
  padding: 11px; font-size: 13.5px; margin-bottom: 10px;
}
.panel[aria-label="Manage lockers"] .item-badge{
  padding: 5px 9px; font-size: 12.5px; border-radius: 9px;
}
.panel[aria-label="Manage lockers"] .btn-row{ gap: 10px; }
.panel[aria-label="Manage lockers"] .btn{ padding: 10px 12px; border-radius: 11px; }

@media (hover:hover){
  .panel[aria-label="Manage lockers"] .locker-card:hover{
    transform: translateY(-2px);
    box-shadow: var(--shadow-2);
    border-color:#dbe3f3;
  }
}

@media (prefers-reduced-motion: reduce){
  .panel[aria-label="Manage lockers"] .locker-card,
  .panel[aria-label="Manage lockers"] .btn{
    transition: none !important;
  }
  .panel[aria-label="Manage lockers"] .locker-card:hover{ transform: none !important; }
}
/* Force Unlock tiles: 2-up on phones (50% each) */
@media (max-width: 680px){
  .force-grid{
    grid-template-columns: repeat(2, 1fr) !important;
  }
  .unlock-tile{
    grid-column: auto / span 1 !important;
  }
}
/* User table: fix overflow on mobile */
@media (max-width: 680px){
  /* let long emails wrap instead of pushing layout wide */
  .user-table td:nth-child(3){
    overflow-wrap: anywhere;
    word-break: break-word;
  }
  .user-table th, .user-table td{ padding: 10px 8px; }
  .user-table-wrap{
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 6px;
  }
}
</style>
</head>

<body>
<main id="content" aria-label="Admin dashboard">
  <!-- Page Header -->
  <div class="page-head">
    <h1 class="page-title" style="color:#23325A">Admin Dashboard</h1>
    <div class="page-actions">
      <span class="tag" style="color:#23325A"><i class="fa-regular fa-calendar"></i><?= date('M d, Y') ?></span>
    </div>
  </div>

  <!-- KPIs -->
  <section class="kpis" aria-label="Key performance indicators">
    <!-- TOTAL LOCKERS -->
    <div class="kpi kpi--total" aria-label="Total lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-cubes"></i></div>
      <div class="meta">
        <div class="val"><?= (int)$total_lockers ?></div>
        <div class="lbl">Total Lockers</div>
      </div>
    </div>

    <!-- OCCUPIED (locked) -->
    <div class="kpi kpi--occupied" aria-label="Occupied lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-lock"></i></div>
      <div class="meta">
        <div class="val"><?= (int)$occupied_lockers ?></div>
        <div class="lbl">Occupied</div>
      </div>
    </div>

    <!-- AVAILABLE (unlocked) -->
    <div class="kpi kpi--available" aria-label="Available lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-unlock"></i></div>
      <div class="meta">
        <div class="val"><?= (int)$available_lockers ?></div>
        <div class="lbl">Available</div>
      </div>
    </div>

    <!-- USERS -->
    <div class="kpi kpi--users" aria-label="Total users">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-user-group"></i></div>
      <div class="meta">
        <div class="val"><?= (int)$total_users ?></div>
        <div class="lbl">Total Users</div>
      </div>
    </div>
  </section>

  <!-- Main Sections -->
  <div class="section-grid">
    <!-- Left: Manage Lockers (only section edited) -->
    <section class="panel" aria-label="Manage lockers">
      <div class="panel-head">
        <div class="panel-title">
          <i class="fa-solid fa-screwdriver-wrench"></i>Manage Lockers
        </div>
      </div>
      <div class="panel-body">
        <div class="locker-grid">
          <?php while($locker = $locker_result->fetch_assoc()): ?>
            <?php
              $status = htmlspecialchars($locker['status'] ?? '', ENT_QUOTES, 'UTF-8');
              $lnum   = (int)$locker['locker_number'];
              $fname  = htmlspecialchars($locker['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
              $lname  = htmlspecialchars($locker['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
              $email  = htmlspecialchars($locker['email'] ?? '', ENT_QUOTES, 'UTF-8');
              $code   = htmlspecialchars($locker['code'] ?? '', ENT_QUOTES, 'UTF-8');
              $item   = !empty($locker['item']);
              $expires_at = $locker['expires_at'] ? date('M d, Y h:i A', strtotime($locker['expires_at'])) : null;
              $duration_minutes = isset($locker['duration_minutes']) ? (int)$locker['duration_minutes'] : null;

              $durationStr = null;
              if ($duration_minutes !== null) {
                if ($duration_minutes < 60) {
                  $durationStr = $duration_minutes . ' min';
                } else {
                  $hours = floor($duration_minutes / 60);
                  $mins  = $duration_minutes % 60;
                  $durationStr = $hours . ' hr' . ($hours > 1 ? 's' : '');
                  if ($mins > 0) $durationStr .= ' ' . $mins . ' min';
                }
              }
            ?>
            <article class="locker-card <?= $status ?>">
              <span class="status-badge <?= $status ?>">
                <?= $status === 'hold' ? 'On Hold' : ucfirst($status ?: 'unknown') ?>
              </span>

              <div class="locker-top">
                <div class="locker-num">Locker <?= $lnum ?></div>
                <?php if($status === 'hold' && $item): ?>
                  <span class="item-badge"><i class="fa-solid fa-box"></i> Item Inside</span>
                <?php endif; ?>
              </div>

              <?php if($status === 'occupied' || ($status === 'hold' && $item)): ?>
              <div class="locker-details" aria-live="polite">
                <?php if($status === 'occupied'): ?>
                  <?php if($fname): ?>
                    <p><strong>User:</strong> <?= $fname . ' ' . $lname ?></p>
                    <p><strong>Email:</strong> <span class="mono"><?= $email ?></span></p>
                  <?php endif; ?>
                  <p><strong>QR Code:</strong> <span class="mono"><?= $code ?></span></p>
                  <p><strong>Item:</strong> <?= $item ? 'Contains Item' : 'Empty' ?></p>
                  <?php if($expires_at): ?><p><strong>Expires:</strong> <?= $expires_at ?></p><?php endif; ?>
                  <?php if($durationStr): ?><p><strong>Duration:</strong> <?= $durationStr ?></p><?php endif; ?>
                <?php else: ?>
                  <p><strong>Item:</strong> Contains Item</p>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <div class="btn-row">
                <button class="btn btn-reset" data-locker="<?= $lnum ?>" aria-label="Reset locker <?= $lnum ?>"><i class="fa-solid fa-rotate-right"></i> Reset</button>
                <?php if($status === 'hold'): ?>
                  <button class="btn btn-release" data-locker="<?= $lnum ?>" aria-label="Release locker <?= $lnum ?>"><i class="fa-solid fa-unlock"></i> Release</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
      </div>
    </section>

    <!-- Right: Force Unlock + Assign (unchanged layout; validation & pagination handled in JS) -->
    <section class="panel" aria-label="Force unlock and assign">
      <div class="panel-head">
        <div class="panel-title"><i class="fa-solid fa-key"></i> Quick Controls</div>
      </div>
      <div class="panel-body">
        <!-- Force Unlock -->
        <h3 class="small" style="margin:0 0 8px;">Force Unlock</h3>
        <div class="force-grid">
          <?php for($i=1;$i<=4;$i++): ?>
            <div class="unlock-tile">
              <i class="fa-solid fa-unlock-alt fa-2x"></i>
              <h4>Locker <?= $i ?></h4>
              <button class="unlock-action btn-force-unlock" data-locker="<?= $i ?>" aria-label="Force unlock locker <?= $i ?>"><i class="fa-solid fa-key"></i> Unlock</button>
            </div>
          <?php endfor; ?>
        </div>
        <div class="unlock-all" role="region" aria-label="Unlock all lockers">
          <div style="max-width:520px; width:50%;">
            <i class="fa-solid fa-lock-open fa-xl"></i>
            <div style="margin-top:10px;">
              <button id="unlockAll" class="unlock-action" aria-label="Force unlock all lockers"><i class="fa-solid fa-unlock"></i> Unlock All</button>
            </div>
            <div class="small" style="opacity:.9; margin-top:8px;">Opens doors without changing their status</div>
          </div>
        </div>

        <!-- Assign Locker -->
        <h3 class="small" style="margin:16px 0 8px;">Assign Locker Manually</h3>
        <form id="assignLockerForm" class="form" method="post" action="/kandado/api/assign_locker.php">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label for="userSearchInput">Select User</label>
              <div class="user-table-wrap">
                <div class="search-wrap">
                  <i class="fa-solid fa-search"></i>
                  <input type="text" id="userSearchInput" class="input" placeholder="Search by ID, name, or email..." autocomplete="off">
                </div>
                <table class="user-table" id="userTable" style="display:none;">
                  <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th></tr>
                  </thead>
                  <tbody>
                    <?php
                    $users_rs = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role='user' AND archived=0");
                    while($u = $users_rs->fetch_assoc()):
                      $uid  = (int)$u['id'];
                      $name = htmlspecialchars($u['first_name'].' '.$u['last_name'], ENT_QUOTES, 'UTF-8');
                      $mail = htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8');
                    ?>
                      <tr data-user-id="<?= $uid ?>">
                        <td><?= $uid ?></td>
                        <td><?= $name ?></td>
                        <td><?= $mail ?></td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
                <div id="pagination" aria-label="User pagination"></div>
              </div>
              <input type="hidden" name="user_id" id="selectedUserId" required>
            </div>

            <div class="form-group">
              <label for="lockerSelect">Select Locker</label>
              <select id="lockerSelect" name="locker_number" class="select" required>
                <option value="">Choose Available Locker</option>
                <?php while($l = $available_result->fetch_assoc()): ?>
                  <option value="<?= (int)$l['locker_number'] ?>">Locker <?= (int)$l['locker_number'] ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="durationSelect">Select Duration</label>
              <select id="durationSelect" name="duration" class="select" required>
                <option value="">Choose Duration</option>
                <!-- populated by JS -->
              </select>
            </div>
          </div>

          <div class="submit-row" style="margin-top:8px;">
            <button type="submit" class="btn-assign"><i class="fa-solid fa-plus"></i> Assign Locker</button>
          </div>
        </form>
      </div>
    </section>
  </div>

  <!-- Analytics (unchanged) -->
  <section style="margin-top:16px;">
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fa-solid fa-chart-line"></i> Locker Usage Analytics</div>
      </div>
      <div class="panel-body">
        <div class="charts">
          <div class="chart-card">
            <h4 class="chart-title">Daily Locker Usage (Last 7 Days)</h4>
            <canvas id="dailyUsageChart" aria-label="Daily usage chart" role="img"></canvas>
          </div>
          <div class="chart-card">
            <h4 class="chart-title">Locker Status Overview</h4>
            <canvas id="statusChart" aria-label="Status chart" role="img"></canvas>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Reset locker
document.querySelectorAll('.btn-reset').forEach(btn => {
  btn.addEventListener('click', () => {
    const lockerNumber = btn.dataset.locker;
    const lockerCard = btn.closest('.locker-card');
    const statusBadge = lockerCard.querySelector('.status-badge');

    Swal.fire({
      title:`Reset Locker #${lockerNumber}?`,
      text:"This will mark the locker as available.",
      icon:"warning",
      showCancelButton:true,
      confirmButtonText:"Yes, reset",
      cancelButtonText:"Cancel",
      confirmButtonColor:"#2c5cff"
    }).then(result => {
      if(result.isConfirmed){
        fetch(`/kandado/api/reset_locker.php?locker=${encodeURIComponent(lockerNumber)}`)
          .then(res => res.json())
          .then(data => {
            if(data.success){
              statusBadge.textContent='Available';
              statusBadge.classList.remove('occupied','hold');
              statusBadge.classList.add('available');
              lockerCard.classList.remove('occupied','hold');
              lockerCard.classList.add('available');
              lockerCard.querySelectorAll('.locker-details p').forEach(p => p.remove());
              Swal.fire('Reset!', `Locker #${lockerNumber} is now available.`,'success')
                .then(()=>location.reload());
            } else {
              Swal.fire('Error', data.message || 'Failed to reset locker.','error');
            }
          }).catch(err => Swal.fire('Error', err.message,'error'));
      }
    });
  });
});

// Release locker (hold -> available)
document.querySelectorAll('.btn-release').forEach(btn => {
  btn.addEventListener('click', () => {
    const lockerNumber = btn.dataset.locker;
    const lockerCard = btn.closest('.locker-card');
    const statusBadge = lockerCard.querySelector('.status-badge');

    Swal.fire({
      title:`Release Locker #${lockerNumber}?`,
      text:"This will mark the locker as available.",
      icon:"warning",
      showCancelButton:true,
      confirmButtonText:"Yes, release",
      cancelButtonText:"Cancel",
      confirmButtonColor:"#10b981"
    }).then(result => {
      if(result.isConfirmed){
        fetch(`/kandado/api/release_locker.php?locker=${encodeURIComponent(lockerNumber)}`)
          .then(res => res.json())
          .then(data => {
            if(data.success){
              statusBadge.textContent='Available';
              statusBadge.classList.remove('hold');
              statusBadge.classList.add('available');
              lockerCard.classList.remove('hold');
              lockerCard.classList.add('available');
              lockerCard.querySelectorAll('.locker-details p').forEach(p => p.remove());
              Swal.fire('Released!', `Locker #${lockerNumber} is now available.`,'success')
                .then(()=>location.reload());
            } else {
              Swal.fire('Error', data.message || 'Failed to release locker.','error');
            }
          }).catch(err => Swal.fire('Error', err.message,'error'));
      }
    });
  });
});

// Force unlock (single)
document.querySelectorAll('.btn-force-unlock').forEach(btn => {
  btn.addEventListener('click', () => {
    const lockerNumber = btn.dataset.locker;
    Swal.fire({
      title:`Force Unlock Locker #${lockerNumber}?`,
      text:"Opens the door without changing status.",
      icon:"warning",
      showCancelButton:true,
      confirmButtonText:"Yes, unlock",
      cancelButtonText:"Cancel",
      confirmButtonColor:"#f59e0b"
    }).then(result => {
      if(result.isConfirmed){
        fetch(`/kandado/api/forced_unlock_api.php?locker=${encodeURIComponent(lockerNumber)}`)
          .then(res => res.json())
          .then(data => {
            if(data.success){
              Swal.fire('Unlocked!', `Locker #${lockerNumber} is now unlocked.`, 'success');
              const card = btn.closest('.locker-card');
              if(card){ card.classList.add('highlight'); setTimeout(()=>card.classList.remove('highlight'), 1500); }
            } else {
              Swal.fire('Error', data.message || 'Failed to unlock locker.', 'error');
            }
          }).catch(err => Swal.fire('Error', err.message,'error'));
      }
    });
  });
});

// Force unlock (all)
const unlockAll = document.getElementById('unlockAll');
if (unlockAll) {
  unlockAll.addEventListener('click', () => {
    Swal.fire({
      title:`Force Unlock All Lockers?`,
      text:"Opens every locker door without changing status.",
      icon:"warning",
      showCancelButton:true,
      confirmButtonText:"Yes, unlock all",
      cancelButtonText:"Cancel",
      confirmButtonColor:"#ef4444"
    }).then(result => {
      if(result.isConfirmed){
        fetch(`/kandado/api/forced_unlock_api.php?all=1`)
          .then(res => res.json())
          .then(data => {
            if(data.success){
              Swal.fire('Unlocked!', 'All lockers are now unlocked.', 'success');
              document.querySelectorAll('.locker-card').forEach(card => {
                card.classList.add('highlight');
                setTimeout(()=>card.classList.remove('highlight'), 1500);
              });
            } else {
              Swal.fire('Error', data.message || 'Failed to unlock all lockers.', 'error');
            }
          }).catch(err => Swal.fire('Error', err.message,'error'));
      }
    });
  });
}

// Charts (unchanged)
const dailyCtx = document.getElementById('dailyUsageChart').getContext('2d');
new Chart(dailyCtx, {
  type:'line',
  data:{
    labels: <?= json_encode($dates) ?>,
    datasets:[{
      label:'Locker Usage',
      data: <?= json_encode($usage_counts) ?>,
      fill:true,
      backgroundColor:'rgba(76, 102, 255, 0.15)',
      borderColor:'rgba(44, 92, 255, 1)',
      borderWidth:2,
      pointRadius:4,
      pointHoverRadius:5,
      tension:0.35
    }]
  },
  options:{
    responsive:true,
    plugins:{ legend:{ display:true, position:'top' }, tooltip:{ mode:'index', intersect:false } },
    scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } }
  }
});

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
  type:'doughnut',
  data:{
    labels:['Occupied','Available'],
    datasets:[{
      data:[<?= (int)$occupied ?>, <?= (int)$available ?>],
      backgroundColor:['#ef4444','#10b981'],
      hoverOffset:10
    }]
  },
  options:{ responsive:true, cutout:'70%', plugins:{ legend:{ position:'bottom' } } }
});

// Duration options (unchanged)
const durationOptions = [
  {value:'30s',   text:'30 Seconds (Test)'},
  {value:'30min', text:'30 Minutes'},
  {value:'45min', text:'45 Minutes'},
  {value:'1hour', text:'1 Hour'},
  {value:'3hours',text:'3 Hours'},
  {value:'4hours',text:'4 Hours'},
  {value:'5hours',text:'5 Hours'}
];
const durationSelect = document.getElementById('durationSelect');
durationOptions.forEach(opt => {
  const o = document.createElement('option');
  o.value = opt.value; o.textContent = opt.text;
  durationSelect.appendChild(o);
});

/* ============================
   Assign form - VALIDATION + CONFIRM
   + User table search with 3-wide pagination « N N N »
============================ */
(function () {
  const form = document.getElementById('assignLockerForm');
  const selectedUserIdInput = document.getElementById('selectedUserId');
  const lockerSelect = document.getElementById('lockerSelect');
  const durationSelectEl = document.getElementById('durationSelect');

  // Validate on submit (user, locker, duration)
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const missing = [];
    if (!selectedUserIdInput.value) missing.push('Please select a user.');
    if (!lockerSelect.value) missing.push('Please choose an available locker.');
    if (!durationSelectEl.value) missing.push('Please select a duration.');

    if (missing.length) {
      Swal.fire({
        icon: 'error',
        title: 'Missing information',
       html: `<ul style="list-style:none;padding:0;margin:0;">
         ${missing.map(m=>`<li>${m}</li>`).join('')}
       </ul>`});
      return;
    }

    Swal.fire({
      title:"Assign Locker?",
      text:"The selected locker will be assigned to the selected user.",
      icon:"warning",
      showCancelButton:true,
      confirmButtonText:"Yes, assign",
      cancelButtonText:"Cancel",
      confirmButtonColor:"#2c5cff"
    }).then(result => {
      if(result.isConfirmed){
        const formData = new FormData(form);
        fetch('/kandado/api/assign_locker.php', { method:'POST', body:formData })
          .then(res => res.json())
          .then(data => {
            if(data.success){
              Swal.fire('Assigned!', data.message || 'Locker assigned successfully.', 'success')
                .then(()=>location.reload());
            } else {
              Swal.fire('Error', data.message || 'Failed to assign locker.', 'error');
            }
          })
          .catch(err => Swal.fire('Error', err.message, 'error'));
      }
    });
  });
})();

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('userSearchInput');
  const table = document.getElementById('userTable');
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.getElementsByTagName('tr'));
  const selectedUserIdInput = document.getElementById('selectedUserId');
  const paginationContainer = document.getElementById('pagination');

  const rowsPerPage = 5;
  const groupSize = 3; // show exactly 3 page numbers
  let currentPage = 1;
  let filteredRows = rows;

  function renderTable() {
    rows.forEach(row => row.style.display = 'none');
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    filteredRows.slice(start, end).forEach(row => row.style.display = '');
    table.style.display = filteredRows.length > 0 ? 'table' : 'none';
    renderPagination();
  }

  function renderPagination() {
    paginationContainer.innerHTML = '';
    const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if (totalPages <= 1) return;

    // Determine current group window
    const groupIndex = Math.floor((currentPage - 1) / groupSize);
    const groupStart = groupIndex * groupSize + 1;
    const groupEnd = Math.min(groupStart + groupSize - 1, totalPages);

    // « prev group
    if (groupStart > 1) {
      const prevBtn = document.createElement('button');
      prevBtn.type = 'button';
      prevBtn.textContent = '«';
      prevBtn.addEventListener('click', () => {
        const prevGroupPage = Math.max(1, groupStart - groupSize);
        currentPage = prevGroupPage;
        renderTable();
      });
      paginationContainer.appendChild(prevBtn);
    }

    // numbered pages in current group (always up to 3)
    for (let i = groupStart; i <= groupEnd; i++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = i;
      if (i === currentPage) btn.classList.add('active');
      btn.addEventListener('click', () => { currentPage = i; renderTable(); });
      paginationContainer.appendChild(btn);
    }

    // » next group
    if (groupEnd < totalPages) {
      const nextBtn = document.createElement('button');
      nextBtn.type = 'button';
      nextBtn.textContent = '»';
      nextBtn.addEventListener('click', () => {
        const nextGroupPage = groupEnd + 1;
        currentPage = nextGroupPage;
        renderTable();
      });
      paginationContainer.appendChild(nextBtn);
    }
  }

  // Search
  searchInput.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    filteredRows = rows.filter(row => {
      const cells = row.getElementsByTagName('td');
      return cells[0].textContent.toLowerCase().includes(filter) || // ID
             cells[1].textContent.toLowerCase().includes(filter) || // Name
             cells[2].textContent.toLowerCase().includes(filter);   // Email
    });
    currentPage = 1; // reset to first page of new filter
    renderTable();
  });

  // Row click to select a user
  tbody.addEventListener('click', function(e) {
    const targetRow = e.target.closest('tr');
    if (!targetRow) return;
    rows.forEach(r => r.classList.remove('selected'));
    targetRow.classList.add('selected');
    selectedUserIdInput.value = targetRow.dataset.userId;
  });

  // Initial render
  filteredRows = rows;
  renderTable();
});
</script>
</body>
</html>
