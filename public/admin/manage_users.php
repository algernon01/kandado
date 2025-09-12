<?php
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
function safe_dir(string $d): string { return strtolower($d)==='desc'?'DESC':'ASC'; }
function order_by_for(string $key, string $dir): string {
  $dir = safe_dir($dir);
  switch ($key) {
    case 'name':    return "u.first_name $dir, u.last_name $dir";
    case 'email':   return "u.email $dir";
    case 'role':    return "u.role $dir, u.id ASC";
    case 'created': return "u.created_at $dir";
    case 'status':  return "archived $dir, u.id ASC";
    case 'locker':  return "l.locker_number $dir, u.id ASC";
    case 'id':
    default:        return "u.id $dir";
  }
}
function status_label(bool $isArchived): string { return $isArchived ? 'Archived' : 'Active'; }

/* ===== BULK & PER-USER ACTIONS ===== */
$sessionUserId = $_SESSION['user_id'] ?? null;
$actionDone = null; $actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? null; $postedCsrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $postedCsrf)) {
    $actionError = 'Security check failed. Please refresh and try again.';
  } else {
    // Normalize verbs (legacy → new)
    $normalize = static function($a){
      $a = strtolower(trim((string)$a));
      if ($a==='hold') return 'archive';
      if ($a==='unhold' || $a==='undo') return 'unarchive';
      return $a;
    };
    $action = $normalize($action);

    // Collect IDs (bulk or single), protect against acting on self
    $ids = [];
    if (!empty($_POST['user_ids']) && is_array($_POST['user_ids'])) { $ids = int_array($_POST['user_ids']); }
    elseif (!empty($_POST['user_id'])) { $ids = int_array([$_POST['user_id']]); }
    $ids = not_self($ids, $sessionUserId);

    if ($action === 'undo_last_archive') {
      $last = $_SESSION['last_archived_ids'] ?? [];
      $ids  = int_array(is_array($last)?$last:[]);
      if (!empty($ids)) {
        $ph = in_placeholders(count($ids));
        $sql = "UPDATE users SET archived = 0 WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types=str_repeat('i', count($ids)); $stmt->bind_param($types, ...$ids); $stmt->execute();
        unset($_SESSION['last_archived_ids']);
        $actionDone = 'last archive undone';
      } else {
        $actionError = 'Nothing to undo.';
      }
    } elseif ($action && !empty($ids)) {
      $ph = in_placeholders(count($ids));
      if ($action === 'delete') {
        $sql = "DELETE FROM users WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types=str_repeat('i', count($ids)); $stmt->bind_param($types, ...$ids); $stmt->execute();
        $actionDone = 'deleted';
      } elseif ($action === 'archive' || $action === 'unarchive') {
        $arch = ($action==='archive')?1:0;
        $sql = "UPDATE users SET archived = ? WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types='i'.str_repeat('i',count($ids)); $params=array_merge([$arch],$ids); $stmt->bind_param($types, ...$params); $stmt->execute();
        if ($arch===1) { $_SESSION['last_archived_ids'] = $ids; }
        $actionDone = $arch ? 'archived' : 'restored';
      }
    } else if ($action) {
      $actionError = 'No valid users selected.';
    }
  }
}

/* ===== SEARCH, FILTERS, SORT, PAGINATION ===== */
$limit   = isset($_GET['per']) ? max(5, min(100, (int)$_GET['per'])) : 10;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $limit;

$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$f_role  = isset($_GET['role']) ? (string)$_GET['role'] : '';
$f_stat  = isset($_GET['status']) ? (string)$_GET['status'] : '';
if ($f_stat === 'hold') $f_stat = 'archived'; // back-compat
$f_lock  = isset($_GET['has_locker']) ? (string)$_GET['has_locker'] : '';

$sortKey = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
$sortDir = isset($_GET['dir']) ? (string)$_GET['dir'] : 'asc';
$orderBy = order_by_for($sortKey, $sortDir);

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
$countSql = "SELECT COUNT(DISTINCT u.id) AS total
             FROM users u
             LEFT JOIN locker_qr l ON l.user_id=u.id AND l.status='occupied'
             WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt){
  if(!empty($params)) $countStmt->bind_param($types, ...$params);
  $countStmt->execute(); $totalUsers=(int)($countStmt->get_result()->fetch_assoc()['total']??0); $countStmt->close();
} else { $totalUsers=0; }
$totalPages = max(1, (int)ceil($totalUsers / $limit));

/* ===== LIST ===== */
$listSql = "SELECT
              u.id, u.first_name, u.last_name, u.email, u.role, u.created_at,
              u.profile_image, IFNULL(u.archived,0) AS archived,
              l.locker_number
            FROM users u
            LEFT JOIN locker_qr l ON l.user_id=u.id AND l.status='occupied'
            WHERE $whereSql
            GROUP BY u.id
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
if ($listStmt){
  if(!empty($params)){ $typesList=$types.'ii'; $paramsList=array_merge($params,[$limit,$offset]); $listStmt->bind_param($typesList, ...$paramsList); }
  else { $listStmt->bind_param('ii',$limit,$offset); }
  $listStmt->execute(); $usersRes=$listStmt->get_result();
} else {
  $usersRes = new class{ public $num_rows=0; public function fetch_assoc(){return null;} };
}

function keep(array $extra=[]): string {
  $base=['q'=>$_GET['q']??'','role'=>$_GET['role']??'','status'=>$_GET['status']??'','has_locker'=>$_GET['has_locker']??'','per'=>$_GET['per']??''];
  $q=array_merge($base,$extra); return http_build_query(array_filter($q,fn($v)=>$v!==''&&$v!==null));
}

// View context flags
$onArchivedView = ($f_stat==='archived');
$hasUndo = !empty($_SESSION['last_archived_ids']);
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
/* ===========================================
   LIGHT UI THEME — Responsive & Accessible
   All styles are scoped to #manage-users
   =========================================== */
:root { color-scheme: only light; } /* enforce light */
#manage-users{
  --brand: #2563eb;
  --brand-2:#60a5fa;
  --ink:   #0f172a;
  --muted: #64748b;
  --bg:    #f7f9fc;
  --surface:#ffffff;
  --subtle:#f3f6fb;
  --border:#e6eaf2;
  --success:#16a34a;
  --warning:#b45309;
  --danger:#dc2626;
  --amber:#f59e0b;
  --radius-lg: 16px;
  --radius-md: 12px;
  --radius-sm: 10px;
  --shadow-sm: 0 2px 10px rgba(2,6,23,.06);
  --shadow-md: 0 8px 30px rgba(2,6,23,.10);
  --header-h: 60px;
  --sidebar-w: 280px;
  font-family:'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  color:var(--ink);
  background: linear-gradient(180deg, #f9fbff 0%, #f5f7fc 60%, #f3f6fb 100%);
  line-height:1.45; overflow-x:hidden;
}

/* Layout */
#manage-users .mu-main{ margin-top:var(--header-h); margin-left:var(--sidebar-w); min-height:calc(100vh - var(--header-h)); padding: 20px clamp(16px,2vw,32px); }
@media (max-width: 860px){ #manage-users .mu-main{ margin-left:0; } }
#manage-users .container-xl{ max-width:1220px; margin:0 auto; }

/* Typography & utilities (no explicit weights here) */
#manage-users .h1{ font-size: clamp(1.4rem, 1.2rem + 1vw, 2rem); margin:0; letter-spacing:-.01em; }
#manage-users .lead{ color:var(--muted); }
#manage-users .small{ font-size:.875rem; color:var(--muted); }
#manage-users .muted{ color:var(--muted); }
#manage-users .mb-1{ margin-bottom:.5rem; color:#223b8f; font-size:25px; }
#manage-users .mb-2{ margin-bottom:1rem; }
#manage-users .mb-3{ margin-bottom:1.5rem; }
#manage-users .mt-1{ margin-top:.5rem; }
#manage-users .gap-1{ gap:.35rem; } .gap-2{ gap:.6rem; } .gap-3{ gap:.9rem; }
#manage-users .d-flex{ display:flex; } .align-center{ align-items:center; } .justify-between{ justify-content:space-between; } .wrap{ flex-wrap:wrap; }
#manage-users .w-100{ width:100%; }
#manage-users .text-end{ text-align:right; }
#manage-users .text-center{ text-align:center; }

/* Cards */
#manage-users .card{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow-md); overflow:hidden; }
#manage-users .card-body{ padding:1rem; }
#manage-users .card-header, #manage-users .card-footer{ background:var(--subtle); border-bottom:1px solid var(--border); padding:.75rem 1rem; }
#manage-users .card-footer{ border-top:1px solid var(--border); border-bottom:none; }

/* Buttons */
#manage-users .btn{
  --bg:#fff; --bd:var(--border); --fg:var(--ink);
  display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
  padding:.56rem .9rem; letter-spacing:.01em;
  border-radius:var(--radius-sm); border:1px solid var(--bd); color:var(--fg); background:var(--bg);
  text-decoration:none; cursor:pointer; user-select:none; white-space:nowrap;
  transition: transform .04s ease, background .2s ease, border-color .2s ease, color .2s ease, box-shadow .2s ease, filter .2s ease;
}
#manage-users .btn:hover{ box-shadow:var(--shadow-sm); filter:brightness(.98); }
#manage-users .btn:active{ transform: translateY(1px); }
#manage-users .btn:focus-visible{ outline:3px solid rgba(37,99,235,.35); outline-offset:2px; }
#manage-users .btn-sm{ padding:.35rem .6rem; font-size:.9rem; border-radius:10px; }
#manage-users .btn-lg{ padding:.7rem 1rem; }
#manage-users .btn-primary{ --bg:var(--brand); --bd:var(--brand); --fg:#fff; box-shadow:0 4px 14px rgba(37,99,235,.18); }
#manage-users .btn-ghost{ --bg:#fff; --bd:var(--border); --fg:var(--ink); }

/* Friendly colors */
#manage-users .btn-view{    --bg:#eef2ff; --bd:#c7d2fe; --fg:#1d4ed8; }
#manage-users .btn-warning{ --bg:#fff7ed; --bd:#fed7aa; --fg:#b45309; }
#manage-users .btn-success{ --bg:#ecfdf5; --bd:#bbf7d0; --fg:#166534; }
#manage-users .btn-danger{  --bg:#fff1f2; --bd:#fecdd3; --fg:#b91c1c; }

/* Segmented control */
#manage-users .segmented{
  display:inline-flex; gap:.35rem; padding:.25rem; border:1px solid var(--border);
  border-radius:999px; background:#fff; box-shadow:var(--shadow-sm);
}
#manage-users .segmented a{
  display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .85rem; border-radius:999px;
  text-decoration:none; color:var(--ink); transition:background .2s ease, color .2s ease;
}
#manage-users .segmented a.active{ background:linear-gradient(180deg,#5da1ff,#2b6ffb); color:#fff; }

/* Inputs */
#manage-users .form-label{ font-size:.9rem; margin-bottom:.35rem; color:#334155; }
#manage-users .form-control, #manage-users .form-select{
  appearance:none; width:100%; border:1px solid var(--border); border-radius:12px; padding:.6rem .8rem;
  background:#fff; color:var(--ink); outline:none; transition: box-shadow .15s ease, border-color .15s ease;
}
#manage-users .form-control:focus, #manage-users .form-select:focus{
  border-color:rgba(37,99,235,.45); box-shadow:0 0 0 3px rgba(37,99,235,.12);
}
#manage-users .input-icon{ position:relative; }
#manage-users .input-icon .addon{ position:absolute; inset-inline-start:.65rem; inset-block:0; display:grid; place-items:center; color:#64748b; }
#manage-users .input-icon input{ padding-inline-start:2rem; }

/* Top toolbar */
#manage-users .toolbar{
  display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
  background:linear-gradient(180deg,#ffffff, #f6f9ff 100%);
  border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow-md);
  padding: .9rem 1rem;
}
#manage-users .toolbar .right{ display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }

/* Columns dropdown */
#manage-users .dropdown{ position:relative; }
#manage-users .menu{
  position:absolute; right:0; top:calc(100% + .4rem); min-width:240px; background:#fff; border:1px solid var(--border);
  border-radius:12px; box-shadow:var(--shadow-md); padding:.6rem; display:none; z-index:50;
}
#manage-users .menu.open{ display:block; }
#manage-users .menu .form-check{ display:flex; align-items:center; gap:.5rem; margin:.35rem 0; font-size:.95rem; }

/* Filters row */
#manage-users .filters.card{ border:none; box-shadow:none; background:transparent; }
#manage-users details.filters > summary{
  list-style:none; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; padding:.45rem .8rem;
  border:1px solid var(--border); border-radius:999px; background:#fff; color:var(--ink);
}
#manage-users details.filters[open] > summary{ background:#f8fbff; }
#manage-users .filters-grid{ display:grid; grid-template-columns: 1fr; gap:.75rem; margin-top:.75rem; }
@media (min-width: 861px){
  #manage-users .filters-grid{ grid-template-columns: 1.2fr .8fr .8fr .8fr .8fr; align-items:end; }
}

/* Sort strip */
#manage-users .sort-strip{
  display:flex; align-items:center; gap:.35rem; overflow-x:auto; padding:.25rem;
}
#manage-users .sort-strip .btn{ white-space:nowrap; }

/* Table */
#manage-users .table-wrap{ overflow:auto; border:1px solid var(--border); border-radius:var(--radius-lg); background:#fff; box-shadow:var(--shadow-md); }
#manage-users table{ width:100%; border-collapse:separate; border-spacing:0; }
#manage-users thead th{
  position:sticky; top:0; z-index:1;
  text-align:left; background:#f1f5fd; color:#334155; font-size:.9rem; padding:.7rem .8rem; letter-spacing:.01em; border-bottom:1px solid var(--border);
}
#manage-users thead th:last-child{ text-align:center; }
#manage-users tbody td{ padding:.7rem .8rem; border-top:1px solid var(--border); vertical-align:middle; }
#manage-users tbody tr:nth-child(odd){ background:#ffffff; }
#manage-users tbody tr:nth-child(even){ background:#fbfdff; }
#manage-users .table-hover tbody tr:hover{ background:#f8fbff; }

/* Badges */
#manage-users .badge{ display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .6rem; border-radius:999px; font-size:.78rem; }
#manage-users .badge-admin{ background:var(--brand); color:#fff; }
#manage-users .badge-user{ background:#e2e8f0; color:#1e293b; }
#manage-users .badge-active{ background:#e9f7ee; color:#166534; border:1px solid #cdebd8; }
#manage-users .badge-archived{ background:#fff7ed; color:#b45309; border:1px solid #fed7aa; }
#manage-users .row-archived{ background:#fff7ed !important; }

/* Avatar */
#manage-users .avatar{ width:44px; height:44px; object-fit:cover; border-radius:50%; border:2px solid transparent; box-shadow:0 2px 8px rgba(2,6,23,.06); }
#manage-users .avatar-ring{ position:relative; }
#manage-users .avatar-ring .ring{ position:absolute; inset:-2px; border:2px solid var(--brand); border-radius:50%; pointer-events:none; }

/* Actions */
#manage-users .btn-list{ display:flex; align-items:center; gap:.45rem; flex-wrap:nowrap; justify-content:center; }
#manage-users .btn-list .btn{ white-space:nowrap; }

/* Mobile table → cards */
@media (max-width: 860px){
  #manage-users table thead{ display:none; }
  #manage-users table tbody tr{ display:block; margin:12px 12px; border:1px solid var(--border); border-radius:14px; overflow:hidden; background:#fff; box-shadow:var(--shadow-sm); }
  #manage-users table tbody td{ display:flex; align-items:center; justify-content:space-between; padding:.7rem .9rem; border-bottom:1px dashed var(--border); font-size:.95rem; }
  #manage-users table tbody td:last-child{ border-bottom:none; }
  #manage-users table tbody td:before{ content: attr(data-label); color:#475569; margin-right:1rem; font-size:.82rem; }

  #manage-users table tbody td[data-label="Actions"]{ justify-content:center; }
  #manage-users .btn .txt{ display:none; }
  #manage-users .btn-list{ gap:.4rem !important; flex-wrap:nowrap; overflow-x:auto; }
}

/* Sticky bulk bar (mobile) */
#manage-users #bulkBar{
  display:none; position:fixed; left:50%; transform:translateX(-50%); bottom:max(12px, env(safe-area-inset-bottom));
  background:#fff; border:1px solid var(--border); border-radius:999px; box-shadow:var(--shadow-md); padding:.25rem .35rem; z-index:1000; gap:.25rem;
}
#manage-users #bulkBar .btn{ border-radius:999px; padding:.45rem .6rem; }
@media (min-width: 861px){ #manage-users #bulkBar{ display:none !important; } }

/* Column visibility */
#manage-users .hidden-col{ display:none !important; }

/* Modal */
#manage-users .mu-modal{ position:fixed; inset:0; background:rgba(2,6,23,.5); display:none; align-items:center; justify-content:center; z-index:1200; backdrop-filter: blur(2px); }
#manage-users .mu-modal.open{ display:flex; }
#manage-users .mu-modal .mu-dialog{ width:min(560px, 92vw); background:#fff; border-radius:16px; box-shadow:var(--shadow-md); overflow:hidden; position:relative; }
#manage-users .mu-modal .mu-body{ padding:1rem 1.25rem; }
#manage-users .mu-modal .mu-footer{ padding:.75rem 1.25rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:.5rem; background:#f8fafc; }
#manage-users .mu-modal .mu-close{ position:absolute; right:14px; top:10px; border:none; background:transparent; font-size:1.4rem; cursor:pointer; color:#475569; }
#manage-users .archived-chip{
  position:absolute; right:10px; top:10px; background:#fff7ed; color:#b45309; border:1px solid #fed7aa;
  border-radius:999px; padding:.25rem .55rem; font-size:.75rem; display:none;
}

/* Accessibility & polish */
#manage-users *:focus-visible{ outline:3px solid rgba(37,99,235,.35); outline-offset:2px; }
@media (prefers-reduced-motion: reduce){
  #manage-users *, #manage-users *::before, #manage-users *::after{ animation:none !important; transition:none !important; }
}

/* Spacing utilities */
#manage-users .me-1 { margin-inline-end:.25rem; }
#manage-users .me-2 { margin-inline-end:.5rem; }
#manage-users .ms-1 { margin-inline-start:.25rem; }
#manage-users .ms-2 { margin-inline-start:.5rem; }

/* ====================================================
   GLOBAL: make ALL text semibold (weight 600)
   ==================================================== */
#manage-users,
#manage-users *,
#manage-users *::before,
#manage-users *::after{
  font-weight:600 !important; /* <- matches your screenshot */
}

/* Placeholders also 600 */
#manage-users input::placeholder,
#manage-users textarea::placeholder{ font-weight:600 !important; }

/* ====================================================
   EXCEPTIONS for Font Awesome icons
   (icons use weight to pick style)
   ==================================================== */
#manage-users .fa-solid,
#manage-users .fas,
#manage-users .fa-solid::before,
#manage-users .fas::before{ font-weight:900 !important; }   /* solid icons */

#manage-users .fa-regular,
#manage-users .far,
#manage-users .fa-regular::before,
#manage-users .far::before{ font-weight:400 !important; }   /* regular icons */

#manage-users .fa-brands,
#manage-users .fab,
#manage-users .fa-brands::before,
#manage-users .fab::before{ font-weight:400 !important; }   /* brands */

</style>
</head>
<body>

<div id="manage-users">
  <main id="content" class="mu-main">
    <div class="container-xl">

      <!-- Top toolbar -->
      <div class="toolbar mb-3">
        <div class="left">
    <h1 class="h1 mb-1" style ="font-weight: 800;">
      <i class="fas fa-users me-2" style="color:var(--brand)" aria-hidden="true"></i>
      Manage Users
    </h1>
        </div>

        <div class="right">
          <!-- Segmented Toggle (Active | Archived) -->
          <div class="segmented" role="tablist" aria-label="Status view">
            <?php
              $activeHref    = '?'.keep(['status'=>'active','page'=>1]);
              $archiveHref   = '?'.keep(['status'=>'archived','page'=>1]);
            ?>
            <a role="tab" href="<?= $activeHref ?>" class="<?= $onArchivedView?'':'active' ?>"><i class="fa-regular fa-circle-check"></i> Active</a>
            <a role="tab" href="<?= $archiveHref ?>" class="<?= $onArchivedView?'active':'' ?>"><i class="fa-solid fa-box-archive"></i> Archived</a>
          </div>

          <!-- Columns dropdown -->
          <div class="dropdown">
            <button class="btn btn-ghost" id="colsToggle" aria-expanded="false" aria-haspopup="true"><i class="fa-solid fa-table-columns me-1"></i> Columns</button>
            <div class="menu" id="colsMenu" role="menu" aria-label="Toggle columns">
              <div class="small mb-1">Toggle visibility</div>
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
      <details class="filters card mb-3" open>
        <summary><i class="fa-solid fa-sliders me-1"></i> Filters</summary>
        <form method="get" id="filtersForm" class="card-body">
          <div class="filters-grid">
            <div>
              <label class="form-label">Search</label>
              <div class="input-icon">
                <span class="addon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <input type="text" id="searchInput" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Search by ID, name, or email…" autocomplete="off" aria-label="Search">
              </div>
            </div>
            <div>
              <label class="form-label">Role</label>
              <select class="form-select" name="role" aria-label="Filter by role">
                <option value="" <?= $f_role===''?'selected':'' ?>>All</option>
                <option value="admin" <?= $f_role==='admin'?'selected':'' ?>>Admin</option>
                <option value="user" <?= $f_role==='user'?'selected':'' ?>>User</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select class="form-select" name="status" aria-label="Filter by status">
                <option value="" <?= $f_stat===''?'selected':'' ?>>All</option>
                <option value="active" <?= $f_stat==='active'?'selected':'' ?>>Active</option>
                <option value="archived" <?= $f_stat==='archived'?'selected':'' ?>>Archived</option>
              </select>
            </div>
            <div>
              <label class="form-label">Has Locker</label>
              <select class="form-select" name="has_locker" aria-label="Filter by locker">
                <option value="" <?= $f_lock===''?'selected':'' ?>>Any</option>
                <option value="yes" <?= $f_lock==='yes'?'selected':'' ?>>Yes</option>
                <option value="no" <?= $f_lock==='no'?'selected':'' ?>>No</option>
              </select>
            </div>
            <div>
              <label class="form-label">Per Page</label>
              <select class="form-select" name="per" aria-label="Per page">
                <?php foreach ([10,20,50,100] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="d-flex gap-2 mt-1 wrap">
            <button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Apply</button>
            <a class="btn btn-ghost" href="manage_users.php"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
          </div>
        </form>
      </details>

      <!-- Standalone form for "Undo Last Archive" -->
      <form id="undoLastForm" method="post" class="d-inline">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="undo_last_archive">
      </form>

      <!-- Bulk + Table form -->
      <form id="bulkForm" method="post" class="card" aria-describedby="bulkActionsHelp">
        <input type="hidden" name="action" id="bulkAction">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="card-header d-flex align-center justify-between wrap">
          <div class="d-flex align-center gap-2 wrap compact">
            <?php if ($onArchivedView): ?>
              <?php if ($hasUndo): ?>
                <button type="submit" form="undoLastForm" class="btn btn-pill btn-ghost" title="Undo Last Archive">
                  <i class="fa-solid fa-rotate-left"></i> <span class="txt">Undo Last Archive</span>
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-pill btn-ghost" title="Undo Last Archive" disabled>
                  <i class="fa-solid fa-rotate-left"></i> <span class="txt">Undo Last Archive</span>
                </button>
              <?php endif; ?>
              <button type="button" onclick="confirmBulk('unarchive')" class="btn btn-pill btn-primary" title="Unarchive selected users">
                <i class="fa-solid fa-box-open"></i> <span class="txt">Unarchive Selected</span>
              </button>
            <?php else: ?>
              <button type="button" onclick="confirmBulk('archive')" class="btn btn-pill btn-ghost" title="Archive selected users">
                <i class="fa-solid fa-box-archive"></i> <span class="txt">Archive</span>
              </button>
              <button type="button" onclick="confirmBulk('unarchive')" class="btn btn-pill btn-ghost" title="Unarchive selected users">
                <i class="fa-solid fa-rotate-left"></i> <span class="txt">Unarchive</span>
              </button>
              <button type="button" onclick="confirmBulk('delete')" class="btn btn-pill btn-ghost" title="Delete selected users">
                <i class="fa-solid fa-trash"></i> <span class="txt">Delete</span>
              </button>
            <?php endif; ?>
            <span id="selCount" class="small">No selection</span>
          </div>

          <div class="d-flex align-center gap-2 w-100" style="flex:1 1 auto;">
            <div class="small">Sort</div>
            <div class="sort-strip" style="flex:1 1 auto;">
              <?php $cols=['id'=>'ID','name'=>'Name','email'=>'Email','role'=>'Role','created'=>'Created','status'=>'Status','locker'=>'Locker'];
              foreach ($cols as $k=>$label):
                $active=($sortKey===$k);
                $dir=($active && strtolower($sortDir)==='asc') ? 'desc' : 'asc'; ?>
                <a class="btn btn-sm <?= $active?'btn-primary':'btn-ghost' ?>" href="?<?= keep(['sort'=>$k,'dir'=>$dir,'page'=>1]) ?>" aria-label="Sort by <?= htmlspecialchars($label) ?>">
                  <?= htmlspecialchars($label) ?><?= $active ? (strtolower($sortDir)==='asc'?' ▲':' ▼') : '' ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table table-hover align-middle" id="usersTable">
            <thead>
              <tr>
                <th style="width:36px"><input class="form-check-input" type="checkbox" id="selectAll" aria-label="Select all"></th>
                <th>ID</th>
                <th>User</th>
                <th class="col-email">Email</th>
                <th class="col-role">Role</th>
                <th class="col-created">Created</th>
                <th class="col-status">Status</th>
                <th class="col-locker">Locker</th>
                <th class="text-center">Actions</th><!-- centered -->
              </tr>
            </thead>
            <tbody>
            <?php if ($usersRes->num_rows > 0): ?>
              <?php while ($u=$usersRes->fetch_assoc()):
                $uid=(int)$u['id'];
                $full=trim(($u['first_name']??'').' '.($u['last_name']??'')); $full=$full!==''?$full:'—';
                $email=htmlspecialchars($u['email']??'', ENT_QUOTES, 'UTF-8');
                $role=htmlspecialchars($u['role']??'user', ENT_QUOTES, 'UTF-8');
                $isArchived=(int)($u['archived']??0)===1;
                $createdISO=$u['created_at'] ?: null; $created=$createdISO?date('M d, Y • h:i A', strtotime($createdISO)):'—';
                $profileName=!empty($u['profile_image'])?$u['profile_image']:'default.jpg';
                $img='/kandado/assets/uploads/'.htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8').'?t='.time();
                $locker=!empty($u['locker_number'])?('Locker '.(int)$u['locker_number']):'None';
                $rowClass=$isArchived?'row-archived':'';
                $payload=[ 'id'=>$uid,'name'=>$full,'email'=>$u['email']??'','role'=>$role,'created'=>$created,'archived'=>$isArchived,'locker'=>$u['locker_number']??null,'img'=>$img ];
              ?>
              <tr class="<?= $rowClass ?> user-row" data-user='<?= json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>
                <td data-label="Select">
                  <?php if ($uid !== (int)$sessionUserId): ?>
                    <input class="form-check-input row-check" type="checkbox" name="user_ids[]" value="<?= $uid ?>" aria-label="Select user <?= $uid ?>">
                  <?php else: ?><input class="form-check-input" type="checkbox" disabled aria-disabled="true"><?php endif; ?>
                </td>
                <td data-label="ID"><?= $uid ?></td>
                <td data-label="User">
                  <div class="d-flex align-center gap-2">
                    <div class="avatar-ring" title="<?= $isArchived ? 'Archived' : 'Active' ?>">
                      <img class="avatar" src="<?= $img ?>" onerror="this.onerror=null;this.src='/kandado/assets/uploads/default.jpg'" alt="<?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?>">
                      <?php if(!$isArchived): ?><span class="ring" aria-hidden="true"></span><?php endif; ?>
                    </div>
                    <div>
                      <div class="name" style="font-weight:900; color:var(--ink)"><?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small">ID: <?= $uid ?></div>
                    </div>
                  </div>
                </td>
                <td data-label="Email" class="col-email">
                  <a href="mailto:<?= $email ?>" class="text-reset" style="word-break:break-all;"><?= $email ?></a>
                  <button type="button" class="btn btn-ghost btn-sm ms-1" onclick="copyText('<?= $email ?>')" title="Copy email"><i class="fa-regular fa-copy"></i></button>
                </td>
                <td data-label="Role" class="col-role">
                  <?php if ($role==='admin'): ?>
                    <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Admin</span>
                  <?php else: ?>
                    <span class="badge badge-user"><i class="fas fa-user"></i> User</span>
                  <?php endif; ?>
                </td>
                <td data-label="Created" class="col-created" title="<?= htmlspecialchars($createdISO ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= $created ?></td>
                <td data-label="Status" class="col-status">
                  <?php if ($isArchived): ?>
                    <span class="badge badge-archived"><i class="fa-solid fa-box-archive"></i> Archived</span>
                  <?php else: ?>
                    <span class="badge badge-active"><i class="fa-regular fa-circle-check"></i> Active</span>
                  <?php endif; ?>
                </td>
                <td data-label="Locker" class="col-locker"><?= htmlspecialchars($locker, ENT_QUOTES, 'UTF-8') ?></td>

                <!-- ACTIONS: centered + 3 inline + friendly colors -->
                <td class="text-center" data-label="Actions">
                  <div class="btn-list compact">
                    <button type="button" class="btn btn-view btn-sm viewBtn" title="View">
                      <i class="fa-regular fa-eye"></i> <span class="txt ms-1">View</span>
                    </button>

                    <?php if ($uid !== (int)$sessionUserId): ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input type="hidden" name="action" value="<?= $isArchived ? 'unarchive' : 'archive' ?>">
                        <button class="btn <?= $isArchived ? 'btn-success' : 'btn-warning' ?> btn-sm"
                                onclick="return confirmRow(this.form, '<?= $isArchived ? 'Restore this user?' : 'Archive this user?' ?>')"
                                title="<?= $isArchived?'Unarchive':'Archive' ?>">
                          <i class="<?= $isArchived ? 'fas fa-rotate-left' : 'fa-solid fa-box-archive' ?>"></i>
                          <span class="txt ms-1"><?= $isArchived ? 'Unarchive' : 'Archive' ?></span>
                        </button>
                      </form>

                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger btn-sm"
                                onclick="return confirmRow(this.form, 'Delete this user permanently?')"
                                title="Delete">
                          <i class="fas fa-trash"></i> <span class="txt ms-1">Delete</span>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="small">(You)</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-center" style="padding:2.2rem 1rem;"><div class="muted"><i class="fas fa-user-slash me-1"></i>No users found<?= $q? ' for “' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '”' : '' ?>.</div></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): $window=2; $start=max(1,$page-$window); $end=min($totalPages,$page+$window); ?>
        <div class="card-footer d-flex justify-content-center wrap gap-2">
          <a class="btn btn-ghost <?= $page===1?'disabled':'' ?>" href="<?= $page===1 ? 'javascript:void(0)' : '?'.keep(['page'=>1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="First page">«</a>
          <a class="btn btn-ghost <?= $page===1?'disabled':'' ?>" href="<?= $page===1 ? 'javascript:void(0)' : '?'.keep(['page'=>$page-1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Previous page">Prev</a>
          <?php if ($start>1): ?><span class="px-2">…</span><?php endif; ?>
          <?php for ($i=$start; $i<=$end; $i++): ?>
            <a class="btn <?= $i===$page?'btn-primary':'btn-ghost' ?>" href="<?= $i===$page ? 'javascript:void(0)' : '?'.keep(['page'=>$i,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Page <?= $i ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($end<$totalPages): ?><span class="px-2">…</span><?php endif; ?>
          <a class="btn btn-ghost <?= $page>=$totalPages?'disabled':'' ?>" href="<?= $page>=$totalPages ? 'javascript:void(0)' : '?'.keep(['page'=>$page+1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Next page">Next</a>
          <a class="btn btn-ghost <?= $page>=$totalPages?'disabled':'' ?>" href="<?= $page>=$totalPages ? 'javascript:void(0)' : '?'.keep(['page'=>$totalPages,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Last page">»</a>
        </div>
        <?php endif; ?>
      </form>

      <!-- Sticky bulk bar (mobile, contextual) -->
      <div id="bulkBar" class="shadow" aria-hidden="true">
        <?php if ($onArchivedView): ?>
          <?php if ($hasUndo): ?>
            <button type="submit" form="undoLastForm" class="btn btn-ghost" title="Undo Last Archive"><i class="fa-solid fa-rotate-left"></i></button>
          <?php endif; ?>
          <button type="button" class="btn btn-primary" onclick="confirmBulk('unarchive')" title="Unarchive Selected"><i class="fa-solid fa-box-open"></i></button>
        <?php else: ?>
          <button type="button" class="btn btn-ghost" onclick="confirmBulk('archive')" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
          <button type="button" class="btn btn-ghost" onclick="confirmBulk('unarchive')" title="Unarchive"><i class="fa-solid fa-rotate-left"></i></button>
          <button type="button" class="btn btn-ghost" onclick="confirmBulk('delete')" title="Delete"><i class="fa-solid fa-trash"></i></button>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <!-- View Modal -->
  <div class="mu-modal" id="viewModal" aria-hidden="true" role="dialog" aria-label="User details">
    <div class="mu-dialog">
      <button class="mu-close" data-close aria-label="Close">&times;</button>
      <div class="mu-body">
        <span class="archived-chip" id="vmArchivedChip"><i class="fa-solid fa-box-archive me-1"></i> Archived</span>
        <div class="d-flex align-center gap-2 mb-2">
          <img id="vmAvatar" src="" alt="" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--brand)">
          <div>
            <div class="h5 fw-black" id="vmName" style="font-weight:900; font-size:1.1rem;">—</div>
            <div class="small">ID: <span id="vmId">—</span></div>
          </div>
        </div>
        <div class="d-flex wrap gap-2 small" style="row-gap:.5rem;">
          <div style="flex:1 1 100%"><i class="fa-regular fa-envelope me-1"></i> <a id="vmEmail" href="#" class="text-reset" style="word-break:break-all;">—</a> <button type="button" class="btn btn-ghost btn-sm ms-1" id="vmCopyEmail" title="Copy email"><i class="fa-regular fa-copy"></i></button></div>
          <div style="flex:1 1 45%"><i class="fa-solid fa-user-shield me-1"></i> <span id="vmRole">—</span></div>
          <div style="flex:1 1 45%"><i class="fa-regular fa-calendar me-1"></i> <span id="vmCreated">—</span></div>
          <div style="flex:1 1 45%"><i class="fa-solid fa-circle-info me-1"></i> <span id="vmStatus">—</span></div>
          <div style="flex:1 1 45%"><i class="fa-solid fa-key me-1"></i> <span id="vmLocker">—</span></div>
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
// Columns dropdown
(() => {
  const toggle = document.getElementById('colsToggle');
  const menu = document.getElementById('colsMenu');
  if(!toggle || !menu) return;
  const close = () => { menu.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); };
  toggle.addEventListener('click', (e)=>{ e.stopPropagation(); const open=menu.classList.toggle('open'); toggle.setAttribute('aria-expanded', open?'true':'false'); });
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
    Swal.fire({icon:'info',title:'No users selected',text:'Please select at least one user.',confirmButtonColor:'#2563eb'});
    return;
  }
  const text = action==='delete'
      ? 'Selected users will be permanently deleted!'
      : (action==='archive' ? 'Selected users will be archived!' : 'Selected users will be restored!');
  const color = action==='delete'?'#dc2626':'#2563eb';
  Swal.fire({ title:'Are you sure?', text, icon:'warning', showCancelButton:true, confirmButtonColor:color, cancelButtonColor:'#6b7280', confirmButtonText:'Yes, proceed!' })
   .then(r=>{ if(r.isConfirmed){ document.getElementById('bulkAction').value=action; document.getElementById('bulkForm').submit(); }});
}
function confirmRow(form, message){
  return Swal.fire({title:'Confirm', text:message||'Are you sure?', icon:'question', showCancelButton:true, confirmButtonText:'Yes', confirmButtonColor:'#2563eb'})
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
  const archChip=document.getElementById('vmArchivedChip');
  const open = ()=> modal.classList.add('open');
  const close = ()=> modal.classList.remove('open');

  function fillModal(data){
    const ava=document.getElementById('vmAvatar'); if(ava){ ava.src=data.img; ava.onerror=()=>{ ava.src='/kandado/assets/uploads/default.jpg'; }; }
    const setText=(id,val)=>{ const el=document.getElementById(id); if(el) el.textContent = val||'—'; };
    setText('vmName', data.name);
    setText('vmId', data.id);
    const email = data.email||''; const a=document.getElementById('vmEmail'); if(a){ a.textContent=email||'—'; a.href=email?('mailto:'+email):'#'; }
    setText('vmRole', data.role==='admin'?'Admin':'User');
    setText('vmCreated', data.created);
    setText('vmStatus', data.archived? 'Archived':'Active');
    setText('vmLocker', data.locker? ('Locker '+data.locker) : 'None');
    const copyBtn=document.getElementById('vmCopyEmail'); if(copyBtn){ copyBtn.onclick = ()=> email && copyText(email); }
    if(archChip){ archChip.style.display = data.archived ? 'inline-flex':'none'; }
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
Swal.fire({ icon:'success', title:'Success', text:'Action completed: <?= addslashes($actionDone) ?>', confirmButtonColor:'#2563eb' });
<?php endif; ?>
<?php if ($actionError): ?>
Swal.fire({ icon:'error', title:'Oops', text:'<?= addslashes($actionError) ?>', confirmButtonColor:'#2563eb' });
<?php endif; ?>
</script>
</body>
</html>
