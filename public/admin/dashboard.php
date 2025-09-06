<?php
// ===============================
// Admin Dashboard (Maintenance + Auto-Expire + Activity Pagination + Mobile UX)
// ===============================
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/**
 * Timezone alignment
 * ------------------
 * The most common reason "expired" lockers still look occupied after refresh
 * is a PHP ⇄ MySQL timezone mismatch. We set both to the same zone here.
 * Adjust 'Asia/Manila' if your deployment uses a different zone.
 */
date_default_timezone_set('Asia/Manila');

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

// Make MySQL's NOW() match PHP time (prevents "stale until logout" issue)
$tzOffset = (new DateTime())->format('P'); // e.g. +08:00
$conn->query("SET time_zone = '{$conn->real_escape_string($tzOffset)}'");

/* -----------------------------------------------------------
   AUTO-CLEAR EXPIRED LOCKERS (so refresh reflects real state)
   - If expires_at <= NOW() and not under maintenance:
     set locker back to available + clear user/code/item/timers
----------------------------------------------------------- */
$clear_sql = "
  UPDATE locker_qr
  SET
    status='available',
    user_id=NULL,
    code=NULL,
    item=0,
    expires_at=NULL,
    duration_minutes=NULL
  WHERE
    maintenance = 0
    AND expires_at IS NOT NULL
    AND expires_at <= NOW()
    AND status IN ('occupied','hold')
";
$conn->query($clear_sql);

// --- Summary counts (maintenance is its own category) ---
$total_lockers         = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr")->fetch_assoc()['c'];
$maintenance_lockers   = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE maintenance=1")->fetch_assoc()['c'];
$occupied_lockers      = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE status='occupied' AND maintenance=0")->fetch_assoc()['c'];
$available_lockers     = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE status='available' AND maintenance=0")->fetch_assoc()['c'];
$hold_lockers          = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE status='hold' AND maintenance=0")->fetch_assoc()['c'];
$total_users           = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];

// --- Locker details (include maintenance flag) ---
$locker_sql = "
    SELECT
        l.locker_number, l.status, l.code, l.item, l.expires_at, l.duration_minutes, l.maintenance,
        u.first_name, u.last_name, u.email
    FROM locker_qr l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.locker_number ASC
";
$locker_result = $conn->query($locker_sql);

// --- Available lockers for selects (exclude maintenance) ---
$available_result = $conn->query("SELECT locker_number FROM locker_qr WHERE status='available' AND maintenance=0");

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

// --- Recent activity: grab latest up to 150 (client paginates @10/page)
$recent_rs = $conn->query("
    SELECT locker_number, user_fullname, user_email, duration_minutes, expires_at, used_at, code
    FROM locker_history
    WHERE archived = 0
    ORDER BY used_at DESC
    LIMIT 150
");

// For charts
$occupied   = $occupied_lockers;
$available  = $available_lockers;
$hold       = $hold_lockers;
$maint      = $maintenance_lockers;
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
   Modern Admin UI (Mobile-first)
   - Added auto-expire, maintenance, activity pager
   - Desktop design preserved; mobile improved
========================================= */
:root{
  --brand-700:#1f46ff;
  --brand-600:#2c5cff;
  --brand-500:#4974ff;
  --brand-400:#6a8bff;
  --brand-50:#eef3ff;

  --ok-600:#10b981;
  --warn-600:#f59e0b;
  --danger-600:#ef4444;
  --indigo-600:#4f46e5;
  --muted:#6b7280;

  --bg:#f6f8fb;
  --card:#ffffff;
  --card-2:#fbfcff;
  --stroke:#e7ecf5;

  --ink:#0f172a;
  --ink-2:#1f2937;

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

/* KPI cards */
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
.kpi .lbl{ font-size: 13px; color:var(--muted); font-weight:800; letter-spacing:.2px; }

.kpi--total .ico{ background:var(--brand-50); color:var(--brand-600); }
.kpi--occupied .ico{ background:#fef2f2; color:var(--danger-600); }
.kpi--available .ico{ background:#ecfdf5; color:var(--ok-600); }
.kpi--users .ico{ background:#eef2ff; color:#4338ca; }
.kpi--hold .ico{ background:#fffbeb; color:var(--warn-600); }
.kpi--maint .ico{ background:#eef2ff; color:var(--indigo-600); }

@media (max-width: 1200px){ .kpi{ grid-column: span 6; } }
@media (max-width: 680px){ .kpi{ grid-column: span 12; } }

/* Main layout */
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

/* ---- Manage Lockers: filter chips ---- */
.filter-bar{
  display:flex; flex-wrap:nowrap; gap:8px; padding: 12px 16px; background:#fbfdff; border-bottom:1px solid var(--stroke);
  overflow-x:auto; -webkit-overflow-scrolling:touch;
}
.filter-bar::-webkit-scrollbar{ display:none; }
.filter-chip{
  display:inline-flex; align-items:center; gap:8px;
  padding:8px 12px; border-radius:999px; border:1px solid #dbe3f3; background:#fff;
  font-weight:800; font-size:13px; cursor:pointer; white-space:nowrap;
  transition: all .2s ease;
}
.filter-chip[aria-pressed="true"]{
  background:var(--brand-600); color:#fff; border-color:var(--brand-600);
  box-shadow:var(--shadow-1);
}
.filter-chip .count{
  min-width:22px; height:22px; display:grid; place-items:center; border-radius:999px;
  padding:0 6px; font-size:12px; font-weight:800; background:#f1f5ff; color:#23324a;
}
.filter-chip[aria-pressed="true"] .count{ background:rgba(255,255,255,.2); color:#fff; }

/* Locker grid */
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
  padding: 16px 14px 12px;
  position:relative;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  min-height: 160px;
}
.locker-card:hover{ transform: translateY(-2px); box-shadow:var(--shadow-2); border-color:#dbe3f3; }
@media (max-width: 1200px){ .locker-card{ grid-column: span 6; } }
@media (max-width: 680px){ .locker-card{ grid-column: span 12; } }

/* Status badge */
.status-badge{
  position:absolute; top:12px; right:12px;
  font-size:12.5px; font-weight:800; letter-spacing:.3px;
  padding:6px 10px; border-radius:999px; box-shadow:var(--shadow-1);
  color:#fff;
}
.status-badge.available{ background: linear-gradient(135deg, #16a34a, #2be670); }
.status-badge.occupied{ background: linear-gradient(135deg, #ef4444, #f97316); }
.status-badge.hold{ background: linear-gradient(135deg, #f59e0b, #fde047); color:#1f2937; }
.status-badge.maintenance{ background: linear-gradient(135deg, #4f46e5, #818cf8); }

/* Locker header + meta */
.locker-top{ display:flex; align-items:center; gap:10px; margin-bottom:12px; }
.locker-num{ font-weight:900; letter-spacing:.3px; font-size: 16px; }
.item-badge{
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 9px; background:#fff7ed; color:#9a3412;
  border:1px solid #fed7aa; border-radius:9px; font-weight:800; font-size:12.5px;
}
.locker-details{
  background:#f8fafc; border:1px solid #e6eefb; border-radius:10px;
  padding:11px; font-size: 13.5px; color:#334155; margin-bottom:10px;
}
.locker-details p{ margin: 6px 0; }

/* Locker actions */
.btn-row{ display:flex; gap:10px; flex-wrap:wrap; }
.btn{
  appearance:none; border:none; cursor:pointer;
  display:inline-flex; align-items:center; gap:8px;
  padding:10px 12px; border-radius:11px; font-weight:800;
  transition: transform .2s ease, box-shadow .2s ease, background .2s ease, opacity .2s ease;
  box-shadow: var(--shadow-1);
}
.btn:focus-visible{ outline: 3px solid #bfd3ff; outline-offset:2px; }

.btn-reset{ background: linear-gradient(135deg, var(--brand-600), var(--brand-400)); color:#fff; }
.btn-reset:hover{ transform:translateY(-1px); box-shadow:var(--shadow-2); }
.btn-release{ background: linear-gradient(135deg, #fb923c, #f59e0b); color:#fff; }
.btn-release:hover{ transform:translateY(-1px); box-shadow:var(--shadow-2); }
/* Maintenance button */
.btn-maintenance{ background: linear-gradient(135deg, #6366f1, #8b5cf6); color:#fff; }
.btn-maintenance.is-on{ background: linear-gradient(135deg, #334155, #64748b); }

/* Highlight after forced unlock */
.locker-card.highlight{
  box-shadow: 0 0 0 3px rgba(251, 191, 36, .3), var(--shadow-2) !important;
}

/* Force unlock panel */
.force-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; }
.unlock-tile{
  grid-column: span 3;
  background:linear-gradient(180deg, var(--brand-600), var(--brand-400));
  border-radius:16px; color:#fff; text-align:center; padding:16px 12px;
  box-shadow:var(--shadow-2);
}
.unlock-tile h4{ margin:8px 0 10px; font-weight:900; letter-spacing:.3px; }
.unlock-action{
  margin-top:8px; background:#fff; color:#2c5cff;
  border:none; border-radius:10px; width:100%;
  padding:10px 0; font-weight:900; cursor:pointer; box-shadow:var(--shadow-1);
}
.unlock-action:hover{ transform: translateY(-1px); }
@media (max-width: 1200px){ .unlock-tile{ grid-column: span 6; } }
@media (max-width: 680px){ .unlock-tile{ grid-column: span 12; } }

.unlock-all{
  display:flex; align-items:center; justify-content:center; text-align:center;
  width: clamp(320px, 80%, 560px);
  margin: 12px auto 0;
  background: linear-gradient(180deg, #10b981, #34d399);
  border-radius:16px; color:#fff; padding: 18px 14px; box-shadow:var(--shadow-2);
}
#unlockAll:hover{ transform: translateY(-1px); }

/* Assign panel */
.form-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
@media (max-width: 980px){ .form-grid{ grid-template-columns: 1fr; } }
.form-group label{ display:block; font-weight:800; margin-bottom:8px; color:#0b2447; }
.select, .input{
  width:100%; padding:12px 14px; border-radius:12px;
  border:1px solid #dbe3f3; background:#ffffff;
  font-size:14px; color:#0f172a; outline:none;
  transition: box-shadow .2s ease, border-color .2s ease;
}
.select:focus, .input:focus{ border-color: var(--brand-500); box-shadow: 0 0 0 4px rgba(73,116,255,.15); }
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
  min-height: 360px; /* keeps section height stable while searching */
}
.search-wrap{ position:relative; margin-bottom:10px; }
.search-wrap .fa-search{ position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#7c91c2; }
#userSearchInput{ padding-left:40px; width:100%; }
.table-viewport{
  max-height: 260px; overflow:auto; border:1px solid #e9edf7; border-radius: 10px; background:#fff;
}
.user-table{ width:100%; border-collapse: collapse; font-size:14px; }
.user-table thead th{
  position: sticky; top:0; z-index:1; background:#eef2ff; color:#334155; font-weight:800;
  border-bottom:1px solid #e9edf7; padding:12px; text-align:left;
}
.user-table td{ padding:12px; border-bottom:1px solid #f2f5fb; text-align:left; }
.user-table tr:hover{ background:#f8fbff; cursor:pointer; }
.user-table tr.selected{ background: #2c5cff; color:#fff; }
#pagination, #activityPagination{
  margin-top:8px; display:flex; gap:6px; flex-wrap:wrap; justify-content:center;
}
#pagination button, #activityPagination button{
  border:1px solid #d1dcff; background:#fff; padding:6px 10px; border-radius:8px; font-weight:800; cursor:pointer;
}
#pagination button.active, #activityPagination button.active{ background:#2c5cff; color:#fff; border-color:#2c5cff; }

/* Charts */
.charts{ display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; }
.chart-card{
  grid-column: span 6;
  background:var(--card); border:1px solid var(--stroke); border-radius:var(--r-lg);
  padding: 14px; box-shadow:var(--shadow-1);
}
.chart-title{ font-weight:800; margin: 6px 0 12px; color:#23324a; text-align:left; }
.chart-card canvas{ width:100% !important; max-height: 300px; }
@media (max-width: 980px){ .chart-card{ grid-column: span 12; } }

/* Activity list */
.activity-list{ display:flex; flex-direction:column; gap:10px; }
.activity-item{
  display:flex; align-items:flex-start; gap:12px; padding:10px; border:1px solid #edf1fb; border-radius:12px; background:#fff;
}
.activity-ico{
  width:36px; height:36px; display:grid; place-items:center; border-radius:10px; background:#eef2ff; color:#4f46e5;
}
.activity-meta{ display:flex; flex-direction:column; gap:4px; }
.activity-meta .title{ font-weight:800; color:#1f2937; }
.activity-meta .sub{ font-size:13px; color:#475569; }
.activity-time{ margin-left:auto; font-size:12px; color:#64748b; white-space:nowrap; }

/* Mobile polish */
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; color:#475569; word-break:break-all; }
.small{ font-size:12px; color:#0c6b53 !important; opacity:1 !important; }

/* Mobile-first fixes without changing desktop look */
@media (max-width: 680px){
  .panel-body{ padding: 12px; }
  .locker-details{ font-size:13px; }
  .locker-details p{ margin: 4px 0; word-break:break-word; overflow-wrap:anywhere; }
  .btn-row .btn{ flex:1 1 48%; justify-content:center; }
  .locker-top{ flex-wrap:wrap; }
  .user-table td:nth-child(3){ overflow-wrap:anywhere; word-break:break-word; }
  .user-table th, .user-table td{ padding: 10px 8px; }
}
@media (max-width: 560px){
  /* Stack recent activity cleanly on phones */
  .activity-item{ flex-direction:column; }
  .activity-time{ margin-left:0; margin-top:6px; }
  .activity-meta .title{ font-size:14.5px; }
  .activity-meta .sub{ overflow-wrap:anywhere; word-break:break-word; }
}
/* Motion/accessibility fallbacks */
@media (hover:hover){
  .panel[aria-label="Manage lockers"] .locker-card:hover{
    transform: translateY(-2px); box-shadow: var(--shadow-2); border-color:#dbe3f3;
  }
}
@media (prefers-reduced-motion: reduce){
  .panel[aria-label="Manage lockers"] .locker-card,
  .panel[aria-label="Manage lockers"] .btn{ transition: none !important; }
  .panel[aria-label="Manage lockers"] .locker-card:hover{ transform: none !important; }
}
/* === Desktop list view for Manage Lockers (match "first screenshot") === */
@media (min-width: 981px){
  /* One full‑width card per row */
  .panel[aria-label="Manage lockers"] .locker-grid{
    display: grid;
    grid-template-columns: 1fr;   /* single column on PC */
    gap: 16px;
  }
  .panel[aria-label="Manage lockers"] .locker-card{
    grid-column: 1 / -1 !important; /* occupy the full width */
    min-height: 140px;               /* airy look */
    padding: 18px 16px 22px;         /* similar spacing to your first pic */
  }
  /* keep buttons under the locker title, like the first design */
  .panel[aria-label="Manage lockers"] .btn-row{
    margin-top: 10px;
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
    <div class="kpi kpi--total" aria-label="Total lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-cubes"></i></div>
      <div class="meta"><div class="val"><?= (int)$total_lockers ?></div><div class="lbl">Total Lockers</div></div>
    </div>
    <div class="kpi kpi--occupied" aria-label="Occupied lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-lock"></i></div>
      <div class="meta"><div class="val"><?= (int)$occupied_lockers ?></div><div class="lbl">Occupied</div></div>
    </div>
    <div class="kpi kpi--available" aria-label="Available lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-unlock"></i></div>
      <div class="meta"><div class="val"><?= (int)$available_lockers ?></div><div class="lbl">Available</div></div>
    </div>
    <div class="kpi kpi--hold" aria-label="Hold lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-hand"></i></div>
      <div class="meta"><div class="val"><?= (int)$hold_lockers ?></div><div class="lbl">On Hold</div></div>
    </div>
    <div class="kpi kpi--maint" aria-label="Maintenance lockers">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></div>
      <div class="meta"><div class="val"><?= (int)$maintenance_lockers ?></div><div class="lbl">Maintenance</div></div>
    </div>
    <div class="kpi kpi--users" aria-label="Total users">
      <div class="ico" aria-hidden="true"><i class="fa-solid fa-user-group"></i></div>
      <div class="meta"><div class="val"><?= (int)$total_users ?></div><div class="lbl">Total Users</div></div>
    </div>
  </section>

  <!-- Main Sections -->
  <div class="section-grid">
    <!-- Left: Manage Lockers -->
    <section class="panel" aria-label="Manage lockers">
      <div class="panel-head">
        <div class="panel-title"><i class="fa-solid fa-screwdriver-wrench"></i>Manage Lockers</div>
      </div>

      <!-- Filter Chips -->
      <div class="filter-bar" role="toolbar" aria-label="Locker filters">
        <button class="filter-chip" data-filter="all" aria-pressed="true"><i class="fa-solid fa-layer-group"></i> All <span class="count"><?= (int)$total_lockers ?></span></button>
        <button class="filter-chip" data-filter="available" aria-pressed="false"><i class="fa-solid fa-unlock"></i> Available <span class="count"><?= (int)$available_lockers ?></span></button>
        <button class="filter-chip" data-filter="occupied" aria-pressed="false"><i class="fa-solid fa-lock"></i> Occupied <span class="count"><?= (int)$occupied_lockers ?></span></button>
        <button class="filter-chip" data-filter="hold" aria-pressed="false"><i class="fa-solid fa-hand"></i> Hold <span class="count"><?= (int)$hold_lockers ?></span></button>
        <button class="filter-chip" data-filter="maintenance" aria-pressed="false"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance <span class="count"><?= (int)$maintenance_lockers ?></span></button>
      </div>

      <div class="panel-body">
        <div class="locker-grid" id="lockerGrid">
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
              $isMaintenance = !empty($locker['maintenance']) ? 1 : 0;

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
              $displayStatus = $isMaintenance ? 'maintenance' : ($status ?: 'unknown');
            ?>
            <article
              class="locker-card <?= $displayStatus ?>"
              data-status="<?= $status ?>"
              data-maintenance="<?= $isMaintenance ?>"
              data-has-item="<?= $item ? 1 : 0 ?>"
              data-locker-number="<?= $lnum ?>"
            >
              <span class="status-badge <?= $displayStatus ?>">
                <?= $displayStatus === 'hold' ? 'On Hold' : ucfirst($displayStatus) ?>
              </span>

              <div class="locker-top">
                <div class="locker-num">Locker <?= $lnum ?></div>
                <?php if(($status === 'hold' && $item) || ($status === 'occupied' && $item)): ?>
                  <span class="item-badge"><i class="fa-solid fa-box"></i> Item Inside</span>
                <?php endif; ?>
              </div>

              <?php if($displayStatus === 'occupied' || ($status === 'hold' && $item) || $displayStatus === 'maintenance'): ?>
              <div class="locker-details" aria-live="polite">
                <?php if($displayStatus === 'occupied'): ?>
                  <?php if($fname): ?>
                    <p><strong>User:</strong> <?= $fname . ' ' . $lname ?></p>
                    <p><strong>Email:</strong> <span class="mono"><?= $email ?></span></p>
                  <?php endif; ?>
                  <p><strong>QR Code:</strong> <span class="mono"><?= $code ?></span></p>
                  <p><strong>Item:</strong> <?= $item ? 'Contains Item' : 'Empty' ?></p>
                  <?php if($expires_at): ?><p><strong>Expires:</strong> <?= $expires_at ?></p><?php endif; ?>
                  <?php if($durationStr): ?><p><strong>Duration:</strong> <?= $durationStr ?></p><?php endif; ?>
                <?php elseif($displayStatus === 'maintenance'): ?>
                  <p><strong>Status:</strong> Under Maintenance (unavailable for assignments)</p>
                  <?php if($code): ?><p><strong>Last Code:</strong> <span class="mono"><?= $code ?></span></p><?php endif; ?>
                <?php else: ?>
                  <p><strong>Item:</strong> Contains Item</p>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <div class="btn-row">
                <button class="btn btn-reset" data-locker="<?= $lnum ?>" aria-label="Reset locker <?= $lnum ?>"><i class="fa-solid fa-rotate-right"></i> Reset</button>
                <?php if($status === 'hold' && !$isMaintenance): ?>
                  <button class="btn btn-release" data-locker="<?= $lnum ?>" aria-label="Release locker <?= $lnum ?>"><i class="fa-solid fa-unlock"></i> Release</button>
                <?php endif; ?>
                <button
                  class="btn btn-maintenance <?= $isMaintenance ? 'is-on' : '' ?>"
                  data-locker="<?= $lnum ?>"
                  data-maint="<?= $isMaintenance ?>"
                  aria-label="<?= $isMaintenance ? 'End maintenance for locker ' . $lnum : 'Mark locker ' . $lnum . ' as maintenance' ?>"
                >
                  <i class="fa-solid fa-screwdriver-wrench"></i>
                  <?= $isMaintenance ? 'End Maintenance' : 'Maintenance' ?>
                </button>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
      </div>
    </section>

    <!-- Right: Quick Controls + Assign -->
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
                <div class="table-viewport">
                  <table class="user-table" id="userTable">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th></tr></thead>
                    <tbody>
                      <?php
                      $users_rs = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role='user' AND archived=0");
                      while($u = $users_rs->fetch_assoc()):
                        $uid  = (int)$u['id'];
                        $name = htmlspecialchars($u['first_name'].' '.$u['last_name'], ENT_QUOTES, 'UTF-8');
                        $mail = htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8');
                      ?>
                        <tr data-user-id="<?= $uid ?>"><td><?= $uid ?></td><td><?= $name ?></td><td><?= $mail ?></td></tr>
                      <?php endwhile; ?>
                      <tr class="no-rows" style="display:none;"><td colspan="3" style="text-align:center; color:#64748b;">No users found.</td></tr>
                    </tbody>
                  </table>
                </div>
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

  <!-- Recent Activity (with client-side pagination: 10/page) -->
  <section style="margin-top:16px;">
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</div>
      </div>
      <div class="panel-body">
        <div class="activity-list" id="activityList">
          <?php if($recent_rs && $recent_rs->num_rows > 0): ?>
            <?php while($a = $recent_rs->fetch_assoc()): ?>
              <?php
                $lockerNo = (int)$a['locker_number'];
                $name = htmlspecialchars($a['user_fullname'] ?? 'Unknown User', ENT_QUOTES, 'UTF-8');
                $mail = htmlspecialchars($a['user_email'] ?? '', ENT_QUOTES, 'UTF-8');
                $used  = $a['used_at'] ? date('M d, Y h:i A', strtotime($a['used_at'])) : '';
                $dur   = isset($a['duration_minutes']) && $a['duration_minutes'] !== null ? (int)$a['duration_minutes'] : null;
                $durStr = $dur !== null ? ($dur < 60 ? "{$dur} min" : floor($dur/60)." hr".(floor($dur/60)>1?'s':'').(($dur%60)?' '.($dur%60).' min':'')) : null;
              ?>
              <div class="activity-item" data-activity="1">
                <div class="activity-ico"><i class="fa-solid fa-box-archive"></i></div>
                <div class="activity-meta">
                  <div class="title">Locker <?= $lockerNo ?> used by <?= $name ?></div>
                  <div class="sub"><?= $mail ? '<span class="mono">'.$mail.'</span>' : '' ?><?= $durStr ? ' • Duration: '.$durStr : '' ?></div>
                </div>
                <div class="activity-time"><?= $used ?></div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="activity-item" style="justify-content:center;">
              <div class="activity-meta"><div class="sub">No recent activity.</div></div>
            </div>
          <?php endif; ?>
        </div>
        <div id="activityPagination" aria-label="Activity pagination"></div>
      </div>
    </div>
  </section>

  <!-- Analytics -->
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
// ===================
// Status Filter Chips
// ===================
const chips = document.querySelectorAll('.filter-chip');
const cards = document.querySelectorAll('.locker-card');
let currentFilter = 'all';
chips.forEach(chip => {
  chip.addEventListener('click', () => {
    chips.forEach(c => c.setAttribute('aria-pressed','false'));
    chip.setAttribute('aria-pressed','true');
    currentFilter = chip.dataset.filter;
    cards.forEach(card => {
      const status = card.getAttribute('data-status'); // available | occupied | hold
      const maint  = card.getAttribute('data-maintenance') === '1';
      let show = true;
      if (currentFilter === 'available') show = !maint && status === 'available';
      else if (currentFilter === 'occupied') show = !maint && status === 'occupied';
      else if (currentFilter === 'hold') show = !maint && status === 'hold';
      else if (currentFilter === 'maintenance') show = maint;
      else show = true; // all
      card.style.display = show ? '' : 'none';
    });
  });
});

// ==================
// Reset locker
// ==================
document.querySelectorAll('.btn-reset').forEach(btn => {
  btn.addEventListener('click', () => {
    const lockerNumber = btn.dataset.locker;
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

// ==================
// Release locker (hold -> available)
// ==================
document.querySelectorAll('.btn-release').forEach(btn => {
  btn.addEventListener('click', () => {
    const lockerNumber = btn.dataset.locker;
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

// ==================
// Maintenance toggle
// ==================
document.querySelectorAll('.btn-maintenance').forEach(btn => {
  btn.addEventListener('click', () => {
    const lockerNumber = btn.dataset.locker;
    const isOn = btn.dataset.maint === '1';
    const mode = isOn ? 'off' : 'on';

    Swal.fire({
      title: `${isOn ? 'End' : 'Start'} Maintenance for Locker #${lockerNumber}?`,
      text: isOn ? "This locker will follow its current status (available/hold/occupied)."
                 : "Locker will be unavailable and excluded from assignments.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: isOn ? "End Maintenance" : "Start Maintenance",
      cancelButtonText: "Cancel",
      confirmButtonColor: isOn ? "#334155" : "#4f46e5"
    }).then(result => {
      if(result.isConfirmed){
        fetch(`/kandado/api/toggle_maintenance.php?locker=${encodeURIComponent(lockerNumber)}&mode=${mode}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              Swal.fire('Updated!', data.message || 'Maintenance status updated.', 'success')
                .then(()=>location.reload());
            } else {
              Swal.fire('Error', data.message || 'Failed to update maintenance status.', 'error');
            }
          }).catch(err => Swal.fire('Error', err.message, 'error'));
      }
    });
  });
});

// ==================
// Force unlock (single)
// ==================
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
              const card = document.querySelector(`.locker-card[data-locker-number="${lockerNumber}"]`);
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

// ==================
// Charts
// ==================
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
    labels:['Occupied','Available','Hold','Maintenance'],
    datasets:[{
      data:[<?= (int)$occupied ?>, <?= (int)$available ?>, <?= (int)$hold ?>, <?= (int)$maint ?>],
      backgroundColor:['#ef4444','#10b981','#f59e0b','#4f46e5'],
      hoverOffset:10
    }]
  },
  options:{ responsive:true, cutout:'65%', plugins:{ legend:{ position:'bottom' } } }
});

// ============================
// Duration options
// ============================
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

// ============================
// Assign form - VALIDATION + CONFIRM
// ============================
(function () {
  const form = document.getElementById('assignLockerForm');
  const selectedUserIdInput = document.getElementById('selectedUserId');
  const lockerSelect = document.getElementById('lockerSelect');
  const durationSelectEl = document.getElementById('durationSelect');

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
        html: `<ul style="list-style:none;padding:0;margin:0;">${missing.map(m=>`<li>${m}</li>`).join('')}</ul>`
      });
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

// ============================
// User table search + 3-wide pagination (stable height)
// ============================
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('userSearchInput');
  const table = document.getElementById('userTable');
  const tbody = table.querySelector('tbody');
  const dataRows = Array.from(tbody.querySelectorAll('tr[data-user-id]'));
  const noRows = tbody.querySelector('.no-rows');
  const selectedUserIdInput = document.getElementById('selectedUserId');
  const paginationContainer = document.getElementById('pagination');

  const rowsPerPage = 5;
  const groupSize = 3;
  let currentPage = 1;
  let filteredRows = dataRows;

  function renderTable() {
    dataRows.forEach(row => row.style.display = 'none');
    const start = (currentPage - 1) * rowsPerPage;
    const slice = filteredRows.slice(start, start + rowsPerPage);
    slice.forEach(row => row.style.display = '');
    const hasRows = filteredRows.length > 0;
    if (noRows) noRows.style.display = hasRows ? 'none' : '';
    renderPagination();
  }

  function renderPagination() {
    paginationContainer.innerHTML = '';
    const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if (totalPages <= 1) return;

    const groupIndex = Math.floor((currentPage - 1) / groupSize);
    const groupStart = groupIndex * groupSize + 1;
    const groupEnd = Math.min(groupStart + groupSize - 1, totalPages);

    if (groupStart > 1) {
      const prevBtn = document.createElement('button');
      prevBtn.type = 'button'; prevBtn.textContent = '«';
      prevBtn.addEventListener('click', () => { currentPage = Math.max(1, groupStart - groupSize); renderTable(); });
      paginationContainer.appendChild(prevBtn);
    }
    for (let i = groupStart; i <= groupEnd; i++) {
      const btn = document.createElement('button');
      btn.type = 'button'; btn.textContent = i;
      if (i === currentPage) btn.classList.add('active');
      btn.addEventListener('click', () => { currentPage = i; renderTable(); });
      paginationContainer.appendChild(btn);
    }
    if (groupEnd < totalPages) {
      const nextBtn = document.createElement('button');
      nextBtn.type = 'button'; nextBtn.textContent = '»';
      nextBtn.addEventListener('click', () => { currentPage = groupEnd + 1; renderTable(); });
      paginationContainer.appendChild(nextBtn);
    }
  }

  searchInput.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase().trim();
    filteredRows = dataRows.filter(row => {
      const cells = row.getElementsByTagName('td');
      return cells[0].textContent.toLowerCase().includes(filter) ||
             cells[1].textContent.toLowerCase().includes(filter) ||
             cells[2].textContent.toLowerCase().includes(filter);
    });
    currentPage = 1;
    renderTable();
  });

  tbody.addEventListener('click', function(e) {
    const targetRow = e.target.closest('tr[data-user-id]');
    if (!targetRow) return;
    dataRows.forEach(r => r.classList.remove('selected'));
    targetRow.classList.add('selected');
    selectedUserIdInput.value = targetRow.dataset.userId;
  });

  filteredRows = dataRows;
  renderTable();
});

// ============================
// Recent Activity pagination (10 rows/page, same 3-wide pager)
// ============================
document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('activityList');
  if (!list) return;
  const itemsAll = Array.from(list.querySelectorAll('[data-activity="1"]'));
  const pager = document.getElementById('activityPagination');

  const rowsPerPage = 5;
  const groupSize = 3;
  let currentPage = 1;
  let filtered = itemsAll; // reserved if you later add search/filter

  function renderList(){
    itemsAll.forEach(it => it.style.display = 'none');
    const start = (currentPage - 1) * rowsPerPage;
    const slice = filtered.slice(start, start + rowsPerPage);
    slice.forEach(it => it.style.display = '');
    renderPager();
  }

  function renderPager(){
    pager.innerHTML = '';
    const totalPages = Math.ceil(filtered.length / rowsPerPage);
    if (totalPages <= 1) return;

    const gi = Math.floor((currentPage - 1) / groupSize);
    const gs = gi * groupSize + 1;
    const ge = Math.min(gs + groupSize - 1, totalPages);

    if (gs > 1){
      const prev = document.createElement('button');
      prev.type='button'; prev.textContent='«';
      prev.onclick = () => { currentPage = Math.max(1, gs - groupSize); renderList(); };
      pager.appendChild(prev);
    }
    for (let i=gs; i<=ge; i++){
      const b = document.createElement('button');
      b.type='button'; b.textContent = i;
      if (i === currentPage) b.classList.add('active');
      b.onclick = () => { currentPage = i; renderList(); };
      pager.appendChild(b);
    }
    if (ge < totalPages){
      const next = document.createElement('button');
      next.type='button'; next.textContent='»';
      next.onclick = () => { currentPage = ge + 1; renderList(); };
      pager.appendChild(next);
    }
  }

  renderList();
});
</script>
</body>
</html>
