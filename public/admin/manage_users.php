<?php
// manage_users.php — v7.0.1 (fix: sort button clipping on the right edge)
// ----------------------------------------------------------------------------------------
// - Fix: .sort-strip now has safe end padding and no oversized mask, so the active sort
//        chip (e.g., "ID ▲") is no longer clipped on the right.
// - Everything else remains the same as the custom, responsive, no-Tabler build.
// ----------------------------------------------------------------------------------------

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php'); exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

include '../../includes/admin_header.php';

/* ===== DB CONNECTION ===== */
$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) { die('Connection failed: ' . $conn->connect_error); }

/* ===== HELPERS ===== */
function int_array(array $arr): array { $o=[]; foreach ($arr as $v){$v=(int)$v; if($v>0)$o[]=$v;} return array_values(array_unique($o)); }
function not_self(array $ids, $selfId): array { if(!$selfId) return $ids; return array_values(array_filter($ids, fn($x)=>(int)$x!==(int)$selfId)); }
function in_placeholders(int $n): string { return implode(',', array_fill(0,$n,'?')); }
function allowed_sort(string $k): string {
  $m=['id'=>'u.id','name'=>'u.first_name, u.last_name','email'=>'u.email','role'=>'u.role','created'=>'u.created_at','status'=>'archived','locker'=>'l.locker_number'];
  return $m[$k]??'u.id';
}
function safe_dir(string $d): string { return strtolower($d)==='desc'?'DESC':'ASC'; }

/* ===== BULK & PER-USER ACTIONS ===== */
$sessionUserId = $_SESSION['user_id'] ?? null;
$actionDone = null; $actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? null; $postedCsrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $postedCsrf)) {
    $actionError = 'Security check failed. Please refresh and try again.';
  } else {
    $ids = [];
    if (!empty($_POST['user_ids']) && is_array($_POST['user_ids'])) { $ids = int_array($_POST['user_ids']); }
    elseif (!empty($_POST['user_id'])) { $ids = int_array([$_POST['user_id']]); }
    $ids = not_self($ids, $sessionUserId);

    if ($action && !empty($ids)) {
      $ph = in_placeholders(count($ids));
      if ($action === 'delete') {
        $sql = "DELETE FROM users WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types=str_repeat('i', count($ids)); $stmt->bind_param($types, ...$ids); $stmt->execute();
        $actionDone = 'deleted';
      } elseif ($action === 'archive' || $action === 'undo') {
        $arch = ($action==='archive')?1:0;
        $sql = "UPDATE users SET archived = ? WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types='i'.str_repeat('i',count($ids)); $params=array_merge([$arch],$ids); $stmt->bind_param($types, ...$params); $stmt->execute();
        $actionDone = $action==='archive'?'archived':'restored';
      }
    } else if ($action) { $actionError = 'No valid users selected.'; }
  }
}

/* ===== SEARCH, FILTERS, SORT, PAGINATION ===== */
$limit   = isset($_GET['per']) ? max(5, min(100, (int)$_GET['per'])) : 10;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $limit;

$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$f_role  = isset($_GET['role']) ? (string)$_GET['role'] : '';
$f_stat  = isset($_GET['status']) ? (string)$_GET['status'] : '';
$f_lock  = isset($_GET['has_locker']) ? (string)$_GET['has_locker'] : '';

$sortKey = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
$sortDir = isset($_GET['dir']) ? (string)$_GET['dir'] : 'asc';
$orderBy = allowed_sort($sortKey) . ' ' . safe_dir($sortDir);

$whereParts=[]; $params=[]; $types='';
if ($q !== '') {
  if (ctype_digit($q)) {
    $whereParts[]='(u.id = ? OR CAST(u.id AS CHAR) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $idExact=(int)$q; $like="%$q%"; $params=array_merge($params,[$idExact,$like,$like,$like,$like]); $types.='issss';
  } else {
    $whereParts[]='(CAST(u.id AS CHAR) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $like="%$q%"; $params=array_merge($params,[$like,$like,$like,$like]); $types.='ssss';
  }
}
if ($f_role==='admin'||$f_role==='user'){ $whereParts[]='u.role = ?'; $params[]=$f_role; $types.='s'; }
if ($f_stat==='active'){ $whereParts[]='(IFNULL(u.archived,0) = 0)'; } elseif ($f_stat==='archived'){ $whereParts[]='(IFNULL(u.archived,0) = 1)'; }
if ($f_lock==='yes'){ $whereParts[]="(l.status = 'occupied' AND l.user_id = u.id)"; } elseif ($f_lock==='no'){ $whereParts[]='(l.user_id IS NULL)'; }
$whereSql = empty($whereParts) ? '1' : implode(' AND ', $whereParts);

/* ===== COUNT ===== */
$countSql = "SELECT COUNT(DISTINCT u.id) AS total FROM users u LEFT JOIN locker_qr l ON l.user_id=u.id AND l.status='occupied' WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt){
  if(!empty($params)) $countStmt->bind_param($types, ...$params);
  $countStmt->execute(); $totalUsers=(int)($countStmt->get_result()->fetch_assoc()['total']??0); $countStmt->close();
} else { $totalUsers=0; }
$totalPages = max(1, (int)ceil($totalUsers / $limit));

/* ===== LIST ===== */
$listSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.created_at, u.profile_image, IFNULL(u.archived,0) AS archived, l.locker_number
            FROM users u
            LEFT JOIN locker_qr l ON l.user_id=u.id AND l.status='occupied'
            WHERE $whereSql GROUP BY u.id ORDER BY $orderBy LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
if ($listStmt){
  if(!empty($params)){ $typesList=$types.'ii'; $paramsList=array_merge($params,[$limit,$offset]); $listStmt->bind_param($typesList, ...$paramsList);}
  else { $listStmt->bind_param('ii',$limit,$offset); }
  $listStmt->execute(); $usersRes=$listStmt->get_result();
} else {
  $usersRes = new class{ public $num_rows=0; public function fetch_assoc(){return null;} };
}

function keep(array $extra=[]): string {
  $base=['q'=>$_GET['q']??'','role'=>$_GET['role']??'','status'=>$_GET['status']??'','has_locker'=>$_GET['has_locker']??'','per'=>$_GET['per']??''];
  $q=array_merge($base,$extra); return http_build_query(array_filter($q,fn($v)=>$v!==''&&$v!==null));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title>Manage Users — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ===== Scoped UI (no header/sidebar bleed) ===== */
#manage-users{
  --mu-brand:#0d5ef4; --mu-ink:#0b1b3a; --mu-muted:#64748b; --mu-bg:#f6f8fb; --mu-surface:#ffffff; --mu-border:#e5e9f2;
  --mu-green:#16a34a; --mu-red:#dc2626; --mu-radius:16px; --mu-shadow:0 8px 20px rgba(0,0,0,.12);
  font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--mu-ink); background:var(--mu-bg);
  --header-h:60px; --sidebar-w:280px; line-height:1.45; overflow-x:hidden;
}

/* Layout */
#manage-users .mu-main{ margin-top:var(--header-h); margin-left:var(--sidebar-w); min-height:calc(100vh - var(--header-h)); padding: 20px clamp(16px,2vw,32px); }
@media (max-width: 860px){ #manage-users .mu-main{ margin-left:0; } }
#manage-users .container-xl{ max-width:1200px; margin:0 auto; }

/* Utilities */
#manage-users .d-flex{ display:flex; } #manage-users .flex-column{ flex-direction:column; }
#manage-users .flex-md-row{ flex-direction:column; } @media (min-width:861px){ #manage-users .flex-md-row{ flex-direction:row; } }
#manage-users .align-items-start{ align-items:flex-start; } #manage-users .align-items-center{ align-items:center; }
#manage-users .justify-content-between{ justify-content:space-between; } #manage-users .justify-content-center{ justify-content:center; }
#manage-users .gap-2{ gap:.5rem; } #manage-users .mb-3{ margin-bottom:1rem; } #manage-users .mb-1{ margin-bottom:.35rem; }
#manage-users .mt-1{ margin-top:.35rem; } #manage-users .ms-1{ margin-left:.25rem; } #manage-users .me-1{ margin-right:.25rem; } #manage-users .me-2{ margin-right:.5rem; }
#manage-users .w-100{ width:100%; } #manage-users .w-md-auto{ width:100%; } @media (min-width:861px){ #manage-users .w-md-auto{ width:auto; } }
#manage-users .h2{ font-size:1.6rem; margin:0; } #manage-users .fw-black{ font-weight:900; }
#manage-users .text-secondary{ color:var(--mu-muted); } #manage-users .text-primary{ color:var(--mu-brand); }
#manage-users .text-end{ text-align:right; } @media (max-width:860px){ #manage-users .text-end{ text-align:left; } }
#manage-users .small{ font-size:.875rem; } #manage-users .p-3{ padding:1rem; }

/* Buttons */
#manage-users .btn{
  --btn-bg:#fff; --btn-bd:var(--mu-border); --btn-fg:var(--mu-ink);
  display:inline-flex; align-items:center; gap:.35rem; padding:.55rem .9rem; font-weight:700;
  border-radius:12px; border:1px solid var(--btn-bd); color:var(--btn-fg); background:var(--btn-bg); text-decoration:none; cursor:pointer;
  transition: transform .04s ease, background .2s ease, border-color .2s ease, color .2s ease; user-select:none;
}
#manage-users .btn:active{ transform: translateY(1px); }
#manage-users .btn-sm{ padding:.35rem .6rem; font-size:.9rem; border-radius:10px; }
#manage-users .btn-primary{ --btn-bg:var(--mu-brand); --btn-bd:var(--mu-brand); --btn-fg:#fff; }
#manage-users .btn-outline-secondary{ --btn-bg:#fff; --btn-bd:var(--mu-border); --btn-fg:#0b1b3a; }
#manage-users .btn-outline-primary{ --btn-bg:#fff; --btn-bd:rgba(13,94,244,.45); --btn-fg:#0b1b3a; }
#manage-users .btn-warning{ --btn-bg:#fde68a; --btn-bd:#facc15; } #manage-users .btn-danger{ --btn-bg:#fecaca; --btn-bd:#ef4444; } #manage-users .btn-success{ --btn-bg:#bbf7d0; --btn-bd:#22c55e; }
#manage-users .btn-link{ border:none; background:transparent; color:var(--mu-brand); padding:0 .25rem; }

/* Card */
#manage-users .card{ border:1px solid var(--mu-border); background:var(--mu-surface); border-radius:var(--mu-radius); overflow:hidden; }
#manage-users .card-header, #manage-users .card-footer{ padding:.8rem 1rem; background:#f7f9fc; border-bottom:1px solid var(--mu-border); }
#manage-users .card-footer{ border-top:1px solid var(--mu-border); border-bottom:none; }

/* Inputs */
#manage-users .form-label{ font-weight:800; font-size:.9rem; margin-bottom:.35rem; color:#334155; }
#manage-users .form-control, #manage-users .form-select{
  appearance:none; width:100%; border:1px solid var(--mu-border); border-radius:12px; padding:.55rem .75rem;
  background:#fff; color:#0b1b3a; outline:none; transition: box-shadow .15s ease, border-color .15s ease;
}
#manage-users .form-control:focus, #manage-users .form-select:focus{
  border-color: rgba(13,94,244,.55); box-shadow: 0 0 0 3px rgba(13,94,244,.15);
}
#manage-users .input-icon{ position:relative; }
#manage-users .input-icon .input-icon-addon{ position:absolute; inset-inline-start:.6rem; inset-block:0; display:grid; place-items:center; color:#64748b; }
#manage-users .input-icon input{ padding-inline-start:2rem; }

/* Grid */
#manage-users .row{ display:flex; flex-wrap:wrap; gap:.7rem; }
#manage-users .col-12{ flex: 1 1 100%; } #manage-users .col-6{ flex: 1 1 calc(50% - .35rem); }
@media (min-width:861px){ #manage-users .col-md-4{ flex: 0 0 calc(33.333% - .35rem); } #manage-users .col-md-2{ flex: 0 0 calc(16.666% - .35rem); } }

/* Dropdown (custom) */
#manage-users .mu-dropdown{ position:relative; }
#manage-users .mu-menu{
  position:absolute; right:0; top:calc(100% + .4rem); min-width:230px; background:#fff; border:1px solid var(--mu-border); border-radius:12px;
  box-shadow: var(--mu-shadow); padding:.6rem; display:none; z-index:50;
}
#manage-users .mu-menu.open{ display:block; }
#manage-users .mu-menu .form-check{ display:flex; align-items:center; gap:.5rem; margin:.35rem 0; font-size:.95rem; }

/* Filters */
#manage-users details.filters{ border:1px solid var(--mu-border); border-radius:var(--mu-radius); background:#fff; }
#manage-users details.filters > summary{ padding:.2rem .2rem; margin: .4rem 0 .2rem; cursor:pointer; font-weight:900; color:#0b1b3a; }
#manage-users details.filters[open] > summary{ margin-bottom:.6rem; }

/* Table */
#manage-users .table{ width:100%; border-collapse:collapse; }
#manage-users .table thead th{ text-align:left; background:#f2f5fb; color:#334155; font-weight:800; font-size:.9rem; padding:.65rem .8rem; }
#manage-users .table tbody td{ padding:.65rem .8rem; border-top:1px solid var(--mu-border); vertical-align:middle; }
#manage-users .table-hover tbody tr:hover{ background:#f8fbff; }
#manage-users .align-middle td{ vertical-align:middle; }

#manage-users .badge-round{ border-radius:999px; font-weight:800; padding:.35em .7em; font-size:.74rem; display:inline-flex; align-items:center; gap:.35rem; }
#manage-users .badge-admin{ background:var(--mu-brand); color:#fff; }
#manage-users .badge-user{ background:#e2e8f0; color:#1e293b; }
#manage-users .badge-active{ background:#e9f7ee; color:#166534; border:1px solid #cdebd8; }
#manage-users .badge-arch{ background:#f1f5f9; color:#334155; border:1px solid #e2e8f0; }
#manage-users .row-archived{ background:#fff7ed !important; }
#manage-users .avatar-sm{ width:40px; height:40px; object-fit:cover; border-radius:50%; border:2px solid var(--mu-brand); }
#manage-users .name{ font-weight:800; color:var(--mu-ink); }
#manage-users .subtle{ color:var(--mu-muted); font-size:12px; }
#manage-users a.breakable{ word-break:break-all; }

/* ===== SORT STRIP (FIX) =====
   - Add end padding so last chip never touches the scroll edge
   - Remove huge rounded mask (no clipping)
   - Keep smooth horizontal scroll without hiding content */
#manage-users .sort-strip{
  display:flex; align-items:center; gap:.25rem;
  overflow-x:auto; overflow-y:visible;
  padding: .25rem .75rem .25rem .25rem; /* <-- extra right padding fixes clip */
  border-radius: 8px;                   /* smaller, non-intrusive */
  background: transparent;              /* no visible mask behind buttons */
  scroll-padding-right: .75rem;         /* keep final button fully visible when scrolled */
  -webkit-overflow-scrolling: touch;
}
#manage-users .sort-strip .btn{ white-space:nowrap; flex:0 0 auto; }

/* Row actions */
#manage-users .btn-list{ display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
#manage-users .compact-actions .btn{ padding:.5rem .8rem; }
#manage-users .btn i{ pointer-events:none; }

/* Mobile table -> cards */
@media (max-width: 860px){
  #manage-users table thead{ display:none; }
  #manage-users table tbody tr{ display:block; margin-bottom:12px; border:1px solid var(--mu-border); border-radius:14px; overflow:hidden; background:#fff; }
  #manage-users table tbody td{ display:flex; align-items:center; justify-content:space-between; padding:.6rem .9rem; border-bottom:1px dashed var(--mu-border); font-size:.95rem; }
  #manage-users table tbody td:last-child{ border-bottom:none; }
  #manage-users table tbody td:before{ content: attr(data-label); font-weight:700; color:#475569; margin-right:1rem; font-size:.8rem; }
  #manage-users .btn .txt{ display:none; }
  #manage-users .btn-list{ gap:.4rem !important; }
  #manage-users .topbar-actions{ gap:.5rem; }
}

/* Sticky bulk bar (mobile only) */
#manage-users #bulkBar{
  display:none; position:fixed; left:50%; transform:translateX(-50%);
  bottom: max(12px, env(safe-area-inset-bottom)); background:#fff; border:1px solid var(--mu-border);
  border-radius:999px; box-shadow:var(--mu-shadow); padding:.25rem .35rem; z-index:1000; gap:.25rem;
}
#manage-users #bulkBar .btn{ border-radius:999px; padding:.45rem .6rem; }
@media (min-width: 861px){ #manage-users #bulkBar{ display:none !important; } }

/* Column visibility */
#manage-users .hidden-col{ display:none !important; }

/* Modal (custom) */
#manage-users .mu-modal{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:1200; }
#manage-users .mu-modal.open{ display:flex; }
#manage-users .mu-modal .mu-dialog{ width:min(560px, 92vw); background:#fff; border-radius:16px; box-shadow:var(--mu-shadow); overflow:hidden; }
#manage-users .mu-modal .mu-body{ padding:1rem 1.25rem; }
#manage-users .mu-modal .mu-footer{ padding: .75rem 1.25rem; border-top:1px solid var(--mu-border); display:flex; justify-content:flex-end; gap:.5rem; background:#f8fafc; }
#manage-users .mu-modal .mu-close{ position:absolute; right:14px; top:10px; border:none; background:transparent; font-size:1.4rem; cursor:pointer; color:#475569; }

/* Misc */
#manage-users .card-header{ gap:.5rem; }
#manage-users .col-email a{ word-break:break-all; }
#manage-users .col-created{ white-space:nowrap; }
</style>
</head>
<body>

<div id="manage-users">
  <main id="content" class="mu-main">
    <div class="container-xl">

      <!-- Top -->
      <div class="d-flex flex-column flex-md-row align-items-start align-items-center justify-content-between mb-3 gap-2">
        <div>
          <h1 class="h2 fw-black mb-1"><i class="fas fa-users me-2 text-primary"></i>Manage Users</h1>
          <div class="text-secondary">Mobile-first admin page. Search, filter, and manage users efficiently.</div>
        </div>
        <div class="topbar-actions d-flex align-items-center compact-actions">
          <div class="mu-dropdown">
            <button class="btn btn-outline-secondary" id="colsToggle"><i class="fa-solid fa-table-columns me-1"></i> Columns</button>
            <div class="mu-menu" id="colsMenu">
              <div class="text-secondary small mb-1">Toggle visibility</div>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="email" type="checkbox" checked> <span>Email</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="role" type="checkbox" checked> <span>Role</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="created" type="checkbox" checked> <span>Created</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="status" type="checkbox" checked> <span>Status</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="locker" type="checkbox" checked> <span>Locker</span></label>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <details class="card p-3 mb-3 filters" open>
        <summary class="mb-2">Filters</summary>
        <form method="get" id="filtersForm">
          <div class="row align-items-end">
            <div class="col-12 col-md-4">
              <label class="form-label">Search</label>
              <div class="input-icon">
                <span class="input-icon-addon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="searchInput" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Search by ID, name, or email…" autocomplete="off">
              </div>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Role</label>
              <select class="form-select" name="role">
                <option value="" <?= $f_role===''?'selected':'' ?>>All</option>
                <option value="admin" <?= $f_role==='admin'?'selected':'' ?>>Admin</option>
                <option value="user" <?= $f_role==='user'?'selected':'' ?>>User</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="" <?= $f_stat===''?'selected':'' ?>>All</option>
                <option value="active" <?= $f_stat==='active'?'selected':'' ?>>Active</option>
                <option value="archived" <?= $f_stat==='archived'?'selected':'' ?>>Archived</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Has Locker</label>
              <select class="form-select" name="has_locker">
                <option value="" <?= $f_lock===''?'selected':'' ?>>Any</option>
                <option value="yes" <?= $f_lock==='yes'?'selected':'' ?>>Yes</option>
                <option value="no" <?= $f_lock==='no'?'selected':'' ?>>No</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Per Page</label>
              <select class="form-select" name="per">
                <?php foreach ([10,20,50,100] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 d-flex gap-2 mt-1 actions">
              <button class="btn btn-primary"><i class="fa-solid fa-sliders me-1"></i> Apply</button>
              <a class="btn btn-outline-secondary" href="manage_users.php"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
            </div>
          </div>
        </form>
      </details>

      <!-- Bulk + Table form -->
      <form id="bulkForm" method="post" class="card">
        <input type="hidden" name="action" id="bulkAction">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between compact-actions">
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="button" onclick="confirmBulk('archive')" class="btn btn-outline-primary"><i class="fas fa-box-archive me-1"></i><span class="txt">Archive</span></button>
            <button type="button" onclick="confirmBulk('undo')" class="btn btn-outline-success"><i class="fas fa-rotate-left me-1"></i><span class="txt">Undo</span></button>
            <button type="button" onclick="confirmBulk('delete')" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i><span class="txt">Delete</span></button>
            <span id="selCount" class="text-secondary small ms-1">No selection</span>
          </div>

          <div class="d-flex align-items-center gap-2 w-100 w-md-auto">
            <div class="text-secondary small me-1" style="flex-shrink:0;">Sort</div>
            <div class="sort-strip" style="flex-grow:1;">
              <?php $cols=['id'=>'ID','name'=>'Name','email'=>'Email','role'=>'Role','created'=>'Created','status'=>'Status','locker'=>'Locker'];
              foreach ($cols as $k=>$label):
                $active=($sortKey===$k);
                $dir=($active && strtolower($sortDir)==='asc') ? 'desc' : 'asc'; ?>
                <a class="btn btn-sm <?= $active?'btn-primary':'btn-outline-secondary' ?>" href="?<?= keep(['sort'=>$k,'dir'=>$dir,'page'=>1]) ?>">
                  <?= htmlspecialchars($label) ?><?= $active ? (strtolower($sortDir)==='asc'?' ▲':' ▼') : '' ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="table-responsive" style="overflow-x:auto;">
          <table class="table table-hover align-middle" id="usersTable">
            <thead>
              <tr>
                <th style="width:36px"><input class="form-check-input" type="checkbox" id="selectAll"></th>
                <th>ID</th>
                <th>User</th>
                <th class="col-email">Email</th>
                <th class="col-role">Role</th>
                <th class="col-created">Created</th>
                <th class="col-status">Status</th>
                <th class="col-locker">Locker</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($usersRes->num_rows > 0): ?>
              <?php while ($u=$usersRes->fetch_assoc()):
                $uid=(int)$u['id'];
                $full=trim(($u['first_name']??'').' '.($u['last_name']??'')); $full=$full!==''?$full:'—';
                $email=htmlspecialchars($u['email']??'', ENT_QUOTES, 'UTF-8');
                $role=htmlspecialchars($u['role']??'user', ENT_QUOTES, 'UTF-8');
                $arch=(int)($u['archived']??0)===1;
                $createdISO=$u['created_at'] ?: null; $created=$createdISO?date('M d, Y • h:i A', strtotime($createdISO)):'—';
                $profileName=!empty($u['profile_image'])?$u['profile_image']:'default.jpg';
                $img='/kandado/assets/uploads/'.htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8').'?t='.time();
                $locker=!empty($u['locker_number'])?('Locker '.(int)$u['locker_number']):'None';
                $rowClass=$arch?'row-archived':'';
                $payload=[ 'id'=>$uid,'name'=>$full,'email'=>$u['email']??'','role'=>$role,'created'=>$created,'archived'=>$arch,'locker'=>$u['locker_number']??null,'img'=>$img ];
              ?>
              <tr class="<?= $rowClass ?> user-row" data-user='<?= json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>
                <td data-label="Select">
                  <?php if ($uid !== (int)$sessionUserId): ?>
                    <input class="form-check-input row-check" type="checkbox" name="user_ids[]" value="<?= $uid ?>">
                  <?php else: ?><input class="form-check-input" type="checkbox" disabled><?php endif; ?>
                </td>
                <td data-label="ID"><?= $uid ?></td>
                <td data-label="User">
                  <div class="d-flex align-items-center gap-2">
                    <img class="avatar-sm" src="<?= $img ?>" onerror="this.onerror=null;this.src='/kandado/assets/uploads/default.jpg'" alt="<?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?>">
                    <div><div class="name"><?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?></div><div class="subtle">ID: <?= $uid ?></div></div>
                  </div>
                </td>
                <td data-label="Email" class="col-email">
                  <a href="mailto:<?= $email ?>" class="text-reset breakable"><?= $email ?></a>
                  <button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="copyText('<?= $email ?>')" title="Copy email"><i class="fa-regular fa-copy"></i></button>
                </td>
                <td data-label="Role" class="col-role">
                  <?php if ($role==='admin'): ?>
                    <span class="badge badge-round badge-admin"><i class="fas fa-user-shield"></i> Admin</span>
                  <?php else: ?>
                    <span class="badge badge-round badge-user"><i class="fas fa-user"></i> User</span>
                  <?php endif; ?>
                </td>
                <td data-label="Created" class="col-created" title="<?= htmlspecialchars($createdISO ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= $created ?></td>
                <td data-label="Status" class="col-status">
                  <?php if ($arch): ?>
                    <span class="badge badge-round badge-arch"><i class="fa-regular fa-box-archive"></i> Archived</span>
                  <?php else: ?>
                    <span class="badge badge-round badge-active"><i class="fa-regular fa-circle-check"></i> Active</span>
                  <?php endif; ?>
                </td>
                <td data-label="Locker" class="col-locker"><?= htmlspecialchars($locker, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end" data-label="Actions">
                  <div class="btn-list compact-actions">
                    <button type="button" class="btn btn-outline-secondary viewBtn"><i class="fa-regular fa-eye"></i> <span class="txt ms-1">View</span></button>
                    <?php if ($uid !== (int)$sessionUserId): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                      <input type="hidden" name="user_id" value="<?= $uid ?>">
                      <input type="hidden" name="action" value="<?= $arch ? 'undo' : 'archive' ?>">
                      <button class="btn btn-warning" onclick="return confirmRow(this.form, '<?= $arch ? 'Restore this user?' : 'Archive this user?' ?>')">
                        <i class="<?= $arch ? 'fas fa-rotate-left' : 'fas fa-box-archive' ?>"></i> <span class="txt ms-1"><?= $arch ? 'Undo' : 'Archive' ?></span>
                      </button>
                    </form>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                      <input type="hidden" name="user_id" value="<?= $uid ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="btn btn-danger" onclick="return confirmRow(this.form, 'Delete this user permanently?')">
                        <i class="fas fa-trash"></i> <span class="txt ms-1">Delete</span>
                      </button>
                    </form>
                    <?php else: ?><span class="text-secondary">(You)</span><?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-center" style="padding:2.2rem 1rem;"><div class="text-secondary"><i class="fas fa-user-slash me-1"></i>No users found<?= $q? ' for “' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '”' : '' ?>.</div></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): $window=2; $start=max(1,$page-$window); $end=min($totalPages,$page+$window); ?>
        <div class="card-footer d-flex justify-content-center flex-wrap gap-2">
          <a class="btn btn-outline-secondary <?= $page===1?'disabled':'' ?>" href="<?= $page===1 ? 'javascript:void(0)' : '?'.keep(['page'=>1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>">«</a>
          <a class="btn btn-outline-secondary <?= $page===1?'disabled':'' ?>" href="<?= $page===1 ? 'javascript:void(0)' : '?'.keep(['page'=>$page-1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>">Prev</a>
          <?php if ($start>1): ?><span class="px-2">…</span><?php endif; ?>
          <?php for ($i=$start; $i<=$end; $i++): ?>
            <a class="btn <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" href="<?= $i===$page ? 'javascript:void(0)' : '?'.keep(['page'=>$i,'sort'=>$sortKey,'dir'=>$sortDir]) ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($end<$totalPages): ?><span class="px-2">…</span><?php endif; ?>
          <a class="btn btn-outline-secondary <?= $page>=$totalPages?'disabled':'' ?>" href="<?= $page>=$totalPages ? 'javascript:void(0)' : '?'.keep(['page'=>$page+1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>">Next</a>
          <a class="btn btn-outline-secondary <?= $page>=$totalPages?'disabled':'' ?>" href="<?= $page>=$totalPages ? 'javascript:void(0)' : '?'.keep(['page'=>$totalPages,'sort'=>$sortKey,'dir'=>$sortDir]) ?>">»</a>
        </div>
        <?php endif; ?>
      </form>

      <!-- Sticky bulk bar (mobile, centered) -->
      <div id="bulkBar" class="shadow">
        <button type="button" class="btn btn-warning" onclick="confirmBulk('archive')" title="Archive"><i class="fas fa-box-archive"></i></button>
        <button type="button" class="btn btn-success" onclick="confirmBulk('undo')" title="Undo"><i class="fas fa-rotate-left"></i></button>
        <button type="button" class="btn btn-danger" onclick="confirmBulk('delete')" title="Delete"><i class="fas fa-trash"></i></button>
      </div>

    </div>
  </main>

  <!-- View Modal (custom) -->
  <div class="mu-modal" id="viewModal" aria-hidden="true" role="dialog">
    <div class="mu-dialog">
      <button class="mu-close" data-close aria-label="Close">&times;</button>
      <div class="mu-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <img id="vmAvatar" src="" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--mu-brand)">
          <div>
            <div class="fw-black" id="vmName" style="font-size:1.15rem;">—</div>
            <div class="text-secondary small">ID: <span id="vmId">—</span></div>
          </div>
        </div>
        <div class="row small" style="gap:.5rem .7rem;">
          <div class="col-12"><i class="fa-regular fa-envelope me-1"></i> <a id="vmEmail" href="#" class="text-reset breakable">—</a> <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="vmCopyEmail"><i class="fa-regular fa-copy"></i></button></div>
          <div class="col-6"><i class="fa-solid fa-user-shield me-1"></i> <span id="vmRole">—</span></div>
          <div class="col-6"><i class="fa-regular fa-calendar me-1"></i> <span id="vmCreated">—</span></div>
          <div class="col-6"><i class="fa-solid fa-box-archive me-1"></i> <span id="vmStatus">—</span></div>
          <div class="col-6"><i class="fa-solid fa-key me-1"></i> <span id="vmLocker">—</span></div>
        </div>
      </div>
      <div class="mu-footer">
        <button type="button" class="btn btn-primary" data-close>Close</button>
      </div>
    </div>
  </div>
</div><!-- /#manage-users -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Columns dropdown (custom)
(() => {
  const toggle = document.getElementById('colsToggle');
  const menu = document.getElementById('colsMenu');
  if(!toggle || !menu) return;
  const close = () => menu.classList.remove('open');
  toggle.addEventListener('click', (e)=>{ e.stopPropagation(); menu.classList.toggle('open'); });
  document.addEventListener('click', (e)=>{ if(!menu.contains(e.target) && e.target!==toggle) close(); });
})();

// Selection helpers
const selAll = document.getElementById('selectAll');
function selCount(){ return document.querySelectorAll("input[name='user_ids[]']:checked").length; }
function updateSelState(){
  const n=selCount();
  const bar=document.getElementById('bulkBar');
  const counter=document.getElementById('selCount');
  if(counter) counter.textContent = n? (n+' selected'):'No selection';
  if (window.matchMedia('(max-width: 860px)').matches) { if(bar) bar.style.display = n? 'flex':'none'; }
}
selAll && selAll.addEventListener('click', ()=>{
  document.querySelectorAll("input[name='user_ids[]']").forEach(cb=>{ if(!cb.disabled) cb.checked=selAll.checked;});
  updateSelState();
});
Array.from(document.querySelectorAll('.row-check')).forEach(cb=> cb.addEventListener('change', updateSelState));
window.addEventListener('resize', updateSelState);
document.addEventListener('DOMContentLoaded', updateSelState);

// SweetAlert confirms
function confirmBulk(action){
  const checked=Array.from(document.querySelectorAll("input[name='user_ids[]']:checked")).map(i=>i.value);
  if(!checked.length){
    Swal.fire({icon:'info',title:'No users selected',text:'Please select at least one user.',confirmButtonColor:'#0d5ef4'});
    return;
  }
  const text = action==='delete'?'Selected users will be permanently deleted!':(action==='archive'?'Selected users will be archived!':'Selected users will be restored!');
  const color = action==='delete'?'#dc2626':'#0d5ef4';
  Swal.fire({ title:'Are you sure?', text, icon:'warning', showCancelButton:true, confirmButtonColor:color, cancelButtonColor:'#6b7280', confirmButtonText:'Yes, proceed!' })
   .then(r=>{ if(r.isConfirmed){ document.getElementById('bulkAction').value=action; document.getElementById('bulkForm').submit(); }});
}
function confirmRow(form, message){
  return Swal.fire({title:'Confirm', text:message||'Are you sure?', icon:'question', showCancelButton:true, confirmButtonText:'Yes', confirmButtonColor:'#0d5ef4'})
    .then(r=>{ if(r.isConfirmed){ form.submit(); } return false; }), false;
}

// Copy helper
function copyText(txt){
  navigator.clipboard.writeText(txt).then(()=>{
    Swal.fire({ icon:'success', title:'Copied', text:'Copied to clipboard.', timer:1100, showConfirmButton:false });
  });
}

// Column visibility persistence
(function(){
  const key='col_visibility_users';
  const state=JSON.parse(localStorage.getItem(key)||'{}');
  const apply=()=>{
    ['email','role','created','status','locker'].forEach(col=>{
      const vis=state[col]!==false;
      document.querySelectorAll('.col-'+col).forEach(el=>el.classList.toggle('hidden-col',!vis));
      const input=document.querySelector('.col-toggle[data-col="'+col+'"]');
      if(input) input.checked=vis;
    });
  };
  document.querySelectorAll('.col-toggle').forEach(inp=> inp.addEventListener('change',()=>{
    state[inp.dataset.col]=inp.checked; localStorage.setItem(key, JSON.stringify(state)); apply();
  }));
  apply();
})();

// Live search debounce (500ms)
(function(){
  const input=document.getElementById('searchInput'); const form=document.getElementById('filtersForm'); let t; if(!input) return;
  input.addEventListener('input',()=>{ clearTimeout(t); t=setTimeout(()=>{ form.submit(); },500); });
})();

// Custom modal
(function(){
  const modal=document.getElementById('viewModal');
  if(!modal) return;
  const open = ()=> modal.classList.add('open');
  const close = ()=> modal.classList.remove('open');

  function fillModal(data){
    const ava=document.getElementById('vmAvatar'); if(ava){ ava.src=data.img; ava.onerror=()=>{ ava.src='/kandado/assets/uploads/default.jpg'; }; }
    const setText=(id,val)=>{ const el=document.getElementById(id); if(el) el.textContent = val||'—'; };
    setText('vmName', data.name);
    setText('vmId', data.id);
    const email = data.email||''; const a=document.getElementById('vmEmail'); if(a){ a.textContent=email||'—'; a.href=email?('mailto:'+email):'#'; }
    setText('vmRole', data.role);
    setText('vmCreated', data.created);
    setText('vmStatus', data.archived? 'Archived':'Active');
    setText('vmLocker', data.locker? ('Locker '+data.locker) : 'None');
    const copyBtn=document.getElementById('vmCopyEmail'); if(copyBtn){ copyBtn.onclick = ()=> email && copyText(email); }
  }

  Array.from(document.querySelectorAll('.viewBtn')).forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      const tr=e.target.closest('tr'); if(!tr) return;
      const data=JSON.parse(tr.dataset.user||'{}'); fillModal(data); open();
    });
  });
  Array.from(document.querySelectorAll('.user-row')).forEach(tr=>{
    tr.addEventListener('click', (e)=>{
      const target=e.target;
      if(target.closest('button')||target.closest('a')||target.closest('input')||target.tagName==='INPUT' || target.tagName==='BUTTON') return;
      const data=JSON.parse(tr.dataset.user||'{}'); fillModal(data); open();
    });
  });

  modal.addEventListener('click', (e)=>{ if(e.target===modal || e.target.hasAttribute('data-close')) close(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') close(); });
})();

<?php if ($actionDone): ?>
Swal.fire({ icon:'success', title:'Success', text:'Action completed: <?= addslashes($actionDone) ?>', confirmButtonColor:'#0d5ef4' });
<?php endif; ?>
<?php if ($actionError): ?>
Swal.fire({ icon:'error', title:'Oops', text:'<?= addslashes($actionError) ?>', confirmButtonColor:'#0d5ef4' });
<?php endif; ?>
</script>
</body>
</html>
