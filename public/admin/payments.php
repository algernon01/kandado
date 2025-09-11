<?php
/**
 * payments.php — Admin payments panel (fixed + searchable user picker)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ---------------- Guard: Admin only ----------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php');
  exit();
}

// ---------------- DB ----------------
require_once '../../config/db.php'; // safe even if included by header
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------- CSRF ----------------
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ---------------- Helpers ----------------
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money($n): string    { return number_format((float)$n, 2, '.', ','); }
function dt_out(?string $dt): string {
  if (!$dt) return '';
  return date('Y-m-d H:i', strtotime($dt));
}
function dt_to_mysql(?string $dtLocal): ?string {
  if (!$dtLocal) return null;
  $t = str_replace('T', ' ', $dtLocal);
  $ts = strtotime($t);
  return $ts ? date('Y-m-d H:i:s', $ts) : null;
}
function redirect_with(array $override = []): void {
  $params = array_merge($_GET, $override);
  $url = strtok($_SERVER['REQUEST_URI'], '?');
  $qs  = http_build_query(array_filter($params, fn($v) => $v !== null));
  header('Location: ' . $url . ($qs ? ('?' . $qs) : ''));
  exit();
}

// ---------------- Parse Filters/Sorting/Pagination ----------------
$allowedSorts = [
  'date'      => 'p.created_at',
  'amount'    => 'p.amount',
  'user'      => 'u.last_name',
  'locker'    => 'p.locker_number',
  'method'    => 'p.method',
  'reference' => 'p.reference_no'
];
$sort  = $_GET['sort']  ?? 'date';
$order = strtolower($_GET['order'] ?? 'desc');
$sortSql  = $allowedSorts[$sort] ?? $allowedSorts['date'];
$orderSql = ($order === 'asc') ? 'ASC' : 'DESC';

$perPage = (int)($_GET['per_page'] ?? 15);
if ($perPage < 5)   $perPage = 5;
if ($perPage > 100) $perPage = 100;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$filters = [
  'q'          => trim((string)($_GET['q'] ?? '')),
  'method'     => (in_array($_GET['method'] ?? '', ['GCash','Maya'], true) ? $_GET['method'] : ''),
  'from'       => (string)($_GET['from'] ?? ''),
  'to'         => (string)($_GET['to']   ?? ''),
  'range'      => (string)($_GET['range'] ?? ''),
  'locker'     => ($_GET['locker'] ?? '') !== '' ? (int)$_GET['locker'] : '',
  'user_id'    => ($_GET['user_id'] ?? '') !== '' ? (int)$_GET['user_id'] : '',
  'min'        => ($_GET['min'] ?? '') !== '' ? (float)$_GET['min'] : '',
  'max'        => ($_GET['max'] ?? '') !== '' ? (float)$_GET['max'] : '',
];

// Quick ranges
if ($filters['range']) {
  $start = null; $end = null;
  switch ($filters['range']) {
    case 'today':
      $start = new DateTime('today'); $end = new DateTime('tomorrow -1 second'); break;
    case '7d':
      $start = (new DateTime('now'))->modify('-6 days')->setTime(0,0,0); $end = new DateTime('today 23:59:59'); break;
    case '30d':
      $start = (new DateTime('now'))->modify('-29 days')->setTime(0,0,0); $end = new DateTime('today 23:59:59'); break;
    case 'this_month':
      $start = new DateTime(date('Y-m-01 00:00:00')); $end = new DateTime(date('Y-m-t 23:59:59')); break;
    case 'this_year':
      $start = new DateTime(date('Y-01-01 00:00:00')); $end = new DateTime(date('Y-12-31 23:59:59')); break;
  }
  if ($start && $end) {
    $filters['from'] = $start->format('Y-m-d');
    $filters['to']   = $end->format('Y-m-d');
  }
}
unset($filters['range']);

// WHERE
$params = [];
$where  = 'WHERE 1=1 ';
if ($filters['q'] !== '') {
  $q = '%' . $filters['q'] . '%';
  $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR p.reference_no LIKE ? ";
  if (is_numeric($filters['q'])) {
    $where .= " OR p.locker_number = ? OR p.amount = ? ";
    $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    $params[] = (int)$filters['q'];
    $params[] = (float)$filters['q'];
  } else {
    $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
  }
  $where .= ") ";
}
if ($filters['method'] !== '') { $where .= " AND p.method = ? "; $params[] = $filters['method']; }
if ($filters['locker'] !== '') { $where .= " AND p.locker_number = ? "; $params[] = (int)$filters['locker']; }
if ($filters['user_id'] !== '') { $where .= " AND p.user_id = ? "; $params[] = (int)$filters['user_id']; }
if ($filters['from'] !== '') { $where .= " AND p.created_at >= ? "; $params[] = $filters['from'] . ' 00:00:00'; }
if ($filters['to'] !== '')   { $where .= " AND p.created_at <= ? "; $params[] = $filters['to'] . ' 23:59:59'; }
if ($filters['min'] !== '')  { $where .= " AND p.amount >= ? "; $params[] = (float)$filters['min']; }
if ($filters['max'] !== '')  { $where .= " AND p.amount <= ? "; $params[] = (float)$filters['max']; }

// ---------------- Export CSV BEFORE any output ----------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $limitForExport = 10000;
  $sql = "
    SELECT p.id, p.created_at, p.method, p.amount, p.reference_no, p.duration,
           p.locker_number, u.first_name, u.last_name, u.email
    FROM payments p
    INNER JOIN users u ON p.user_id = u.id
    $where
    ORDER BY $sortSql $orderSql
    LIMIT $limitForExport
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=payments_' . date('Ymd_His') . '.csv');
  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
  fputcsv($out, ['ID','Date','User','Email','Locker #','Method','Amount','Reference #','Duration']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['id'], $r['created_at'],
      trim($r['first_name'].' '.$r['last_name']), $r['email'],
      $r['locker_number'], $r['method'], $r['amount'], $r['reference_no'], $r['duration']
    ]);
  }
  fclose($out);
  exit();
}

// ---------------- POST actions ----------------
function validate_payment(array $in, bool $forUpdate = false): array {
  $errors = []; $clean  = [];
  if ($forUpdate) {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) $errors['id'] = 'Invalid payment ID.';
    $clean['id'] = $id;
  }
  $clean['user_id']       = isset($in['user_id']) ? (int)$in['user_id'] : 0;
  $clean['locker_number'] = isset($in['locker_number']) ? (int)$in['locker_number'] : 0;

  $method = $in['method'] ?? '';
  if (!in_array($method, ['GCash','Maya'], true)) $errors['method'] = 'Payment method is required.';
  $clean['method'] = $method;

  $amount = (float)($in['amount'] ?? 0);
  if ($amount <= 0) $errors['amount'] = 'Amount must be greater than zero.';
  $clean['amount'] = $amount;

  $ref = trim((string)($in['reference_no'] ?? ''));
  if ($ref === '') $errors['reference_no'] = 'Reference number is required.';
  if (strlen($ref) > 50) $errors['reference_no'] = 'Reference number is too long.';
  $clean['reference_no'] = $ref;

  $duration = trim((string)($in['duration'] ?? ''));
  if ($duration === '') $errors['duration'] = 'Duration is required.';
  if (strlen($duration) > 20) $errors['duration'] = 'Duration is too long.';
  $clean['duration'] = $duration;

  $created_at = $in['created_at'] ?? '';
  $clean['created_at'] = $created_at ? dt_to_mysql($created_at) : null;

  return [$errors, $clean];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Security check failed. Please try again.'];
    redirect_with();
  }
  $act = $_POST['action'] ?? '';
  try {
    if ($act === 'create_payment') {
      [$errors, $clean] = validate_payment($_POST, false);
      if ($clean['user_id'] <= 0) $errors['user_id'] = 'Select a user.';
      if ($clean['locker_number'] <= 0) $errors['locker_number'] = 'Select a locker.';
      $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE reference_no = ?");
      $chk->execute([$clean['reference_no']]);
      if ($chk->fetchColumn() > 0) $errors['reference_no'] = 'Reference number already exists.';
      $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND archived = 0");
      $st->execute([$clean['user_id']]);
      if ($st->fetchColumn() == 0) $errors['user_id'] = 'Selected user not found or archived.';
      $st = $pdo->prepare("SELECT COUNT(*) FROM locker_qr WHERE locker_number = ?");
      $st->execute([$clean['locker_number']]);
      if ($st->fetchColumn() == 0) $errors['locker_number'] = 'Selected locker does not exist.';
      if ($errors) {
        $_SESSION['form_errors'] = $errors; $_SESSION['form_old'] = $_POST;
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Please fix the highlighted fields.'];
        redirect_with();
      }
      if ($clean['created_at']) {
        $sql = "INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $args = [$clean['user_id'], $clean['locker_number'], $clean['method'], $clean['amount'], $clean['reference_no'], $clean['duration'], $clean['created_at']];
      } else {
        $sql = "INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration)
                VALUES (?, ?, ?, ?, ?, ?)";
        $args = [$clean['user_id'], $clean['locker_number'], $clean['method'], $clean['amount'], $clean['reference_no'], $clean['duration']];
      }
      $pdo->prepare($sql)->execute($args);
      $_SESSION['flash'] = ['type'=>'success','msg'=>'Payment recorded successfully.'];
      redirect_with(['page'=>1]);
    }

    if ($act === 'update_payment') {
      [$errors, $clean] = validate_payment($_POST, true);
      $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE reference_no = ? AND id <> ?");
      $chk->execute([$clean['reference_no'], $clean['id']]);
      if ($chk->fetchColumn() > 0) $errors['reference_no'] = 'Reference number already exists.';
      $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND archived = 0");
      $st->execute([$clean['user_id']]);
      if ($st->fetchColumn() == 0) $errors['user_id'] = 'Selected user not found or archived.';
      $st = $pdo->prepare("SELECT COUNT(*) FROM locker_qr WHERE locker_number = ?");
      $st->execute([$clean['locker_number']]);
      if ($st->fetchColumn() == 0) $errors['locker_number'] = 'Selected locker does not exist.';
      if ($errors) {
        $_SESSION['edit_errors'] = $errors; $_SESSION['edit_old'] = $_POST;
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Please fix the highlighted fields.'];
        redirect_with();
      }
      $sql  = "UPDATE payments SET user_id=?, locker_number=?, method=?, amount=?, reference_no=?, duration=?";
      $args = [$clean['user_id'], $clean['locker_number'], $clean['method'], $clean['amount'], $clean['reference_no'], $clean['duration']];
      if ($clean['created_at']) { $sql .= ", created_at=?"; $args[] = $clean['created_at']; }
      $sql .= " WHERE id=?"; $args[] = $clean['id'];
      $pdo->prepare($sql)->execute($args);
      $_SESSION['flash'] = ['type'=>'success','msg'=>'Payment updated.'];
      redirect_with();
    }

    if ($act === 'delete_payment') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) { $_SESSION['flash']=['type'=>'error','msg'=>'Invalid delete request.']; redirect_with(); }
      $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
      $_SESSION['flash'] = ['type'=>'success','msg'=>'Payment deleted.'];
      redirect_with();
    }
  } catch (Throwable $ex) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Unexpected error: ' . $ex->getMessage()];
    redirect_with();
  }
}

// ---------------- Fetch data for selects ----------------
$users = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE archived = 0 ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
$lockers = $pdo->query("SELECT locker_number FROM locker_qr ORDER BY locker_number")->fetchAll(PDO::FETCH_COLUMN);

// Build a small array for the JS user picker (id + label)
$usersForJs = array_map(function ($u) {
  return [
    'id'    => (int)$u['id'],
    'label' => trim($u['first_name'].' '.$u['last_name']).' — '.$u['email']
  ];
}, $users);

// ---------------- Stats ----------------
$stats = $pdo->query("SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt, COALESCE(AVG(amount),0) AS avg FROM payments")->fetch(PDO::FETCH_ASSOC);
$todayRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$last7Revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$byMethod = $pdo->query("SELECT method, COUNT(*) cnt, COALESCE(SUM(amount),0) sum FROM payments GROUP BY method")->fetchAll(PDO::FETCH_ASSOC);

// ---------------- List query (with filters) ----------------
$countSql = "
  SELECT COUNT(*)
  FROM payments p
  INNER JOIN users u ON p.user_id = u.id
  $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$limit = (int)$perPage;       // safe to inline after casting
$off   = (int)$offset;        // DO NOT use placeholders for LIMIT/OFFSET

$listSql = "
  SELECT p.id, p.user_id, p.locker_number, p.method, p.amount, p.reference_no, p.duration, p.created_at,
         u.first_name, u.last_name, u.email
  FROM payments p
  INNER JOIN users u ON p.user_id = u.id
  $where
  ORDER BY $sortSql $orderSql
  LIMIT $limit OFFSET $off
";
$listStmt = $pdo->prepare($listSql);
foreach ($params as $i => $v) { $listStmt->bindValue($i+1, $v); }
$listStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Old form state
$form_old  = $_SESSION['form_old']  ?? [];
$form_errs = $_SESSION['form_errors'] ?? [];
$edit_old  = $_SESSION['edit_old']  ?? [];
$edit_errs = $_SESSION['edit_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors'], $_SESSION['edit_old'], $_SESSION['edit_errors']);

// Flash for SweetAlert
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---------------- UI Shell ----------------
include '../../includes/admin_header.php';
?>

<style>
  /* Layout and base buttons */
  main#content .page-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin:.25rem 0 1rem; }
  .page-title { display:flex; align-items:center; gap:.6rem; font-weight:800; letter-spacing:.2px; color: var(--primary-700); font-size:30px; }
  .page-title i { font-size:1.2rem; color:#fff; background: linear-gradient(135deg,var(--brand-1),var(--brand-2));
    border:1px solid rgba(0,0,0,.05); width:36px; height:36px; display:grid; place-items:center; border-radius:10px; }

  .btn { display:inline-flex; align-items:center; gap:.45rem; padding:.6rem .9rem; border-radius:12px; font-weight:700; text-decoration:none; cursor:pointer; border:1px solid transparent; }
  .btn i { font-size: .95rem; }
  .btn-primary { background: linear-gradient(135deg,var(--brand-1),var(--brand-2)); color:#fff; box-shadow: var(--shadow-1); }
  .btn-outline  { background: #fff; color: var(--primary-700); border-color: var(--border); }
  .btn-soft     { background: var(--surface-2); color: var(--text); border-color: var(--border); }
  .btn-danger   { background: var(--danger); color:#fff; }
  .btn:focus-visible { outline:2px solid var(--primary); outline-offset:2px; }

  /* Metrics */
  .metrics { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .9rem; margin-bottom: 1rem; }
  @media (max-width: 1000px) { .metrics{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 520px)  { .metrics{ grid-template-columns: 1fr; } }
  .metric-card { background: var(--surface); border:1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-2); padding: .9rem .95rem; }
  .metric-top { display:flex; align-items:center; justify-content:space-between; }
  .metric-top .label { color: var(--muted); font-size:.85rem; font-weight:700; letter-spacing:.02em; }
  .metric-top .chip  { font-size:.72rem; padding:.2rem .5rem; border-radius:999px; border:1px solid var(--border); background: var(--surface-2); color: var(--muted); }
  .metric-value { font-size:1.4rem; font-weight:800; margin-top:.3rem; }
  .metric-trend { font-size:.8rem; color: var(--muted); margin-top:.2rem; }

  /* Filters */
  .filter-bar { background: var(--surface); border:1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-2); padding: .85rem; display:grid; gap:.7rem; }
  .filter-row { display:grid; grid-template-columns: 1.2fr .8fr .8fr .7fr .7fr .9fr .7fr; gap:.6rem; }
  /* Critical fix for your first screenshot: prevent grid children from overflowing */
  .filter-row > .field { min-width: 0; } 
  @media (max-width: 1100px) { .filter-row{ grid-template-columns: 1fr 1fr 1fr 1fr; } }
  @media (max-width: 700px)  { .filter-row{ grid-template-columns: 1fr 1fr; } }

  .field { display:flex; flex-direction:column; gap:.25rem; }
  .field label { font-size:.8rem; font-weight:700; color:var(--muted); }
  .field input, .field select { border:1px solid var(--border); border-radius:10px; padding:.55rem .65rem; background:#fff; font: inherit; color: var(--text); width:100%; min-width:0; }

  .filter-actions { display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; }

  /* Table */
  .table-wrap { background: var(--surface); border:1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-2); margin-top: 1rem; overflow: hidden; }
  .table-toolbar { display:flex; align-items:center; justify-content:space-between; padding:.7rem .8rem; border-bottom:1px solid var(--border); background: var(--surface-2); }
  .table-toolbar .left { display:flex; align-items:center; gap:.5rem; color:var(--muted); font-weight:700; }
  .table-toolbar .right { display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; }
  table.payments { width:100%; border-collapse: collapse; }
  table.payments th, table.payments td { padding:.7rem .8rem; text-align:left; }
  table.payments thead th { background: var(--surface-2); border-bottom:1px solid var(--border); font-size:.8rem; color: var(--muted); }
  table.payments tbody tr + tr td { border-top:1px dashed var(--border); }
  table.payments tbody tr:hover { background: #fafcff; }
  .th-sort { display:inline-flex; align-items:center; gap:.25rem; text-decoration:none; color:inherit; }
  .amount { font-weight:800; }
  .method-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .55rem; border-radius:999px; font-weight:800; font-size:.75rem; }
  .method-gcash { background:#e9f2ff; color:#1d4ed8; border:1px solid #dbe7ff; }
  .method-maya  { background:#eef0ff; color:#4338ca; border:1px solid #e1e4ff; margin-top:2px; }

  .actions { display:flex; gap:.4rem; align-items:center; }
  .icon-btn { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--border); background:#fff; cursor:pointer; }

  /* Responsive table -> cards */
  @media (max-width: 760px) {
    table.payments thead { display:none; }
    table.payments, table.payments tbody, table.payments tr, table.payments td { display:block; width:100%; }
    table.payments tr { border-bottom:1px dashed var(--border); padding:.6rem .5rem; }
    table.payments td { padding:.35rem .4rem; }
    table.payments td::before { content: attr(data-label); display:block; font-size:.75rem; color:var(--muted); margin-bottom:.1rem; }
    /* Critical fix for your 3rd screenshot: keep actions horizontal on phones */
    table.payments td[data-label="Actions"] .actions { display:flex; flex-wrap:nowrap; gap:.35rem; white-space:nowrap; }
    table.payments td[data-label="Actions"] .icon-btn { display:inline-flex; }
  }

  /* Pagination */
  .pagination { display:flex; align-items:center; gap:.25rem; padding:.7rem; justify-content:flex-end; }
  .page-link { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:10px; border:1px solid var(--border); background:#fff; text-decoration:none; color:var(--text); font-weight:700; }
  .page-link.active { background: rgba(34,197,94,.18); border-color: rgba(34,197,94,.35); color: var(--active-green-1); }
  .page-link:hover { background:#f8fbff; }

  /* Modals */
  .modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; background: rgba(15,23,42,.35); padding:1rem; }
  .modal.open { display:flex; }
  .modal-card { width:min(720px, 96vw); background:#fff; border-radius:16px; border:1px solid var(--border); box-shadow: var(--shadow-2); overflow:hidden; }
  .modal-head { display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding:.9rem 1rem; background: var(--surface-2); border-bottom:1px solid var(--border); }
  .modal-body { padding: 1rem; display:grid; gap:.7rem; }
  .modal-grid { display:grid; gap:.6rem; grid-template-columns: 1fr 1fr; }
  .modal-grid > .field { min-width:0; } /* Fix overflow in modal (your 2nd screenshot) */
  @media (max-width: 700px) { .modal-grid{ grid-template-columns: 1fr; } }
  .modal-foot { display:flex; justify-content:flex-end; gap:.5rem; padding: .8rem 1rem; border-top:1px solid var(--border); background: #fff; }

  .error { color: #dc2626; font-size:.78rem; }

  /* Searchable user picker (combobox) */
  .combo { position:relative; }
  .combo input[type="text"] { padding-right:2.2rem; }
  .combo .clear { position:absolute; right:.4rem; top:50%; transform:translateY(-50%); border:1px solid var(--border);
    width:28px; height:28px; border-radius:8px; background:#fff; display:grid; place-items:center; cursor:pointer; }
  .combo .list { position:absolute; top:calc(100% + 6px); left:0; right:0; background:#fff; border:1px solid var(--border);
    border-radius:12px; box-shadow:var(--shadow-2); max-height:260px; overflow:auto; z-index:1300; }
  .combo .item { padding:.5rem .65rem; cursor:pointer; }
  .combo .item:hover, .combo .item.active { background:#f6f8ff; }

  /* Empty state */
  .empty { padding: 2.2rem 1rem; text-align:center; color: var(--muted); }
  .empty i { font-size:2rem; margin-bottom:.25rem; color: var(--primary); opacity:.85; }
  /* Icon button that can show a text label */
.icon-btn.with-text{
  width: auto;              /* let it grow */
  height: auto;
  padding: .35rem .6rem;    /* space for text */
  gap: .35rem;
  white-space: nowrap;      /* keep "View" on one line */
}

/* Optional: hide labels on small screens */
@media (max-width: 700px){
  .icon-btn.with-text .label { display:none; }
}

  
</style>

<main id="content" role="main" aria-labelledby="payments-title">
  <div class="page-head">
    <h1 class="page-title" id="payments-title">
      <i class="fa-solid fa-credit-card"></i> Payments
    </h1>
    <div class="filter-actions">

      <button class="btn btn-primary" id="open-create"><i class="fa-solid fa-plus"></i> Add Payment</button>
    </div>
  </div>

  <!-- Metrics -->
  <section class="metrics" aria-label="Payment metrics">
    <div class="metric-card">
      <div class="metric-top">
        <div class="label">Total Revenue (All‑time)</div>
        <span class="chip"><i class="fa-regular fa-circle-check"></i> Up</span>
      </div>
      <div class="metric-value">₱ <?= money($stats['total'] ?? 0) ?></div>
      <div class="metric-trend"><?= (int)($stats['cnt'] ?? 0) ?> payments • Avg ₱ <?= money($stats['avg'] ?? 0) ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-top"><div class="label">Revenue (Today)</div></div>
      <div class="metric-value">₱ <?= money($todayRevenue) ?></div>
      <div class="metric-trend">since 00:00</div>
    </div>
    <div class="metric-card">
      <div class="metric-top"><div class="label">Revenue (Last 7 days)</div></div>
      <div class="metric-value">₱ <?= money($last7Revenue) ?></div>
      <div class="metric-trend">rolling 7‑day window</div>
    </div>
    <div class="metric-card">
      <div class="metric-top"><div class="label">By Method</div></div>
      <div class="metric-value" style="font-size:1rem;">
        <?php if ($byMethod): foreach ($byMethod as $m): ?>
          <span class="method-chip <?= $m['method']==='GCash' ? 'method-gcash':'method-maya' ?>" title="Count: <?= (int)$m['cnt'] ?>">
            <?= e($m['method']) ?> • ₱ <?= money($m['sum']) ?>
          </span>
        <?php endforeach; else: ?>
          <span class="chip">No data</span>
        <?php endif; ?>
      </div>
      <div class="metric-trend">aggregate totals</div>
    </div>
  </section>

  <!-- Filters -->
  <section class="filter-bar" aria-label="Filters">
    <form method="get" id="filter-form" class="filters">
      <div class="filter-row">
        <div class="field">
          <label for="q">Search</label>
          <input type="text" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email, ref #, amount, locker #">
        </div>
        <div class="field">
          <label for="method">Method</label>
          <select id="method" name="method">
            <option value="">All</option>
            <option value="GCash" <?= $filters['method']==='GCash' ? 'selected':'' ?>>GCash</option>
            <option value="Maya"  <?= $filters['method']==='Maya'  ? 'selected':'' ?>>Maya</option>
          </select>
        </div>
        <div class="field">
          <label for="from">From</label>
          <input type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="field">
          <label for="to">To</label>
          <input type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="field">
          <label for="locker">Locker #</label>
          <select id="locker" name="locker">
            <option value="">All</option>
            <?php foreach ($lockers as $lk): ?>
              <option value="<?= (int)$lk ?>" <?= ($filters['locker'] !== '' && (int)$filters['locker']===(int)$lk) ? 'selected' : '' ?>>
                <?= (int)$lk ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="user_id">User</label>
          <select id="user_id" name="user_id">
            <option value="">All</option>
            <?php foreach ($users as $u): $uid=(int)$u['id']; ?>
              <option value="<?= $uid ?>" <?= ($filters['user_id'] !== '' && (int)$filters['user_id']===$uid) ? 'selected' : '' ?>>
                <?= e($u['first_name'] . ' ' . $u['last_name'] . ' — ' . $u['email']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="min">Amount (min‑max)</label>
          <div style="display:flex; gap:.4rem;">
            <input style="width:50%;" type="number" step="0.01" id="min" name="min" value="<?= e((string)$filters['min']) ?>" placeholder="Min">
            <input style="width:50%;" type="number" step="0.01" id="max" name="max" value="<?= e((string)$filters['max']) ?>" placeholder="Max">
          </div>
        </div>
      </div>

      <div class="filter-actions" style="justify-content:space-between; margin-top:.3rem;">
        <div class="range-chips" role="group" aria-label="Quick ranges" style="display:flex; gap:.4rem; flex-wrap:wrap;">
          <?php
            $base = $_GET; unset($base['from'],$base['to']);
            $chips = [''=>'All','today'=>'Today','7d'=>'Last 7d','30d'=>'Last 30d','this_month'=>'This Month','this_year'=>'This Year'];
          ?>
          <?php foreach ($chips as $r=>$label): ?>
            <?php $p = $base; if ($r==='') { unset($p['range']); } else { $p['range']=$r; } $url='?'.e(http_build_query($p)); ?>
            <?php $isActive = ($r==='') ? (!isset($_GET['range']) && $filters['from']==='' && $filters['to']==='') : (isset($_GET['range']) && $_GET['range']===$r); ?>
            <a href="<?= $url ?>" class="btn btn-soft" style="<?= $isActive?'box-shadow: inset 0 0 0 2px rgba(34,197,94,.35);':'' ?>">
              <?= e($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
        <div>
          <input type="hidden" name="sort" value="<?= e($sort) ?>">
          <input type="hidden" name="order" value="<?= e($order) ?>">
          <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
          <a class="btn btn-soft" href="?"><i class="fa-solid fa-rotate"></i> Reset</a>
        </div>
      </div>
    </form>
  </section>

  <!-- Table -->
  <section class="table-wrap" aria-label="Payments list">
    <div class="table-toolbar">
      <div class="left">
        <i class="fa-regular fa-rectangle-list"></i>
        <span><?= $totalRows ?> result<?= $totalRows===1?'':'s' ?></span>
      </div>
      <div class="right">
        <form method="get" id="paging-form" style="display:flex; align-items:center; gap:.4rem;">
          <?php foreach ($_GET as $k=>$v): if (!in_array($k,['per_page','page'],true)) : ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e(is_array($v)?json_encode($v):$v) ?>">
          <?php endif; endforeach; ?>
          <label for="per_page" style="font-size:.8rem; color: var(--muted); font-weight:700;">Per page</label>
          <select name="per_page" id="per_page" onchange="this.form.submit()" style="border:1px solid var(--border); border-radius:10px; padding:.35rem .5rem;">
            <?php foreach ([10,15,20,25,50,100] as $pp): ?>
              <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <a class="btn btn-outline" href="javascript:window.print()"><i class="fa-solid fa-print"></i> Print</a>
      </div>
    </div>

    <?php if (!$rows): ?>
      <div class="empty">
        <i class="fa-regular fa-face-smile"></i>
        <div style="font-weight:800; margin:.2rem 0;">No payments found</div>
        <div>Try changing filters or add a new payment.</div>
        <div style="margin-top:.7rem;"><button class="btn btn-primary" id="open-create-2"><i class="fa-solid fa-plus"></i> Add Payment</button></div>
      </div>
    <?php else: ?>
      <div style="overflow:auto;">
        <table class="payments">
          <thead>
            <tr>
              <?php
                function sort_link($key, $label, $curSort, $curOrder) {
                  $p = $_GET;
                  $p['sort'] = $key;
                  $p['order'] = ($curSort === $key && $curOrder === 'asc') ? 'desc' : 'asc';
                  $icon = 'fa-solid fa-arrow-up-short-wide';
                  if ($curSort === $key) $icon = ($curOrder==='asc') ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down';
                  $url = '?' . e(http_build_query($p));
                  return '<a class="th-sort" href="'.$url.'">'.e($label).' <i class="'.$icon.'"></i></a>';
                }
              ?>
              <th><?= sort_link('date','Date', $sort,$order) ?></th>
              <th><?= sort_link('user','User', $sort,$order) ?></th>
              <th><?= sort_link('locker','Locker #', $sort,$order) ?></th>
              <th><?= sort_link('method','Method', $sort,$order) ?></th>
              <th><?= sort_link('amount','Amount', $sort,$order) ?></th>
              <th><?= sort_link('reference','Reference #', $sort,$order) ?></th>
              <th>Duration</th>
              <th style="width:160px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $userFull = trim($r['first_name'].' '.$r['last_name']);
                $methodClass = $r['method']==='GCash'?'method-gcash':'method-maya';
              ?>
              <tr>
                <td data-label="Date"><?= e(dt_out($r['created_at'])) ?></td>
                <td data-label="User">
                  <div style="display:flex; flex-direction:column;">
                    <strong><?= e($userFull ?: '—') ?></strong>
                    <small style="color:var(--muted)"><?= e($r['email']) ?></small>
                  </div>
                </td>
                <td data-label="Locker #">#<?= (int)$r['locker_number'] ?></td>
                <td data-label="Method">
                  <span class="method-chip <?= $methodClass ?>"><i class="fa-solid fa-wallet"></i> <?= e($r['method']) ?></span>
                </td>
                <td data-label="Amount" class="amount">₱ <?= money($r['amount']) ?></td>
                <td data-label="Reference #">
                  <code id="ref-<?= (int)$r['id'] ?>" style="background:#f6f8ff; border:1px solid var(--border); padding:.15rem .35rem; border-radius:6px;"><?= e($r['reference_no']) ?></code>
                  <button class="icon-btn" title="Copy reference" data-copy="#ref-<?= (int)$r['id'] ?>"><i class="fa-regular fa-copy"></i></button>
                </td>
                <td data-label="Duration"><?= e($r['duration']) ?></td>
                <td class="actions" data-label="Actions">
              <button class="icon-btn with-text btn-view" aria-label="View payment"
                      data-id="<?= (int)$r['id'] ?>"
                      style ="color:#0000EE"
                      data-date="<?= e(dt_out($r['created_at'])) ?>"
                      data-user="<?= e($userFull) ?>"
                      data-email="<?= e($r['email']) ?>"
                      data-locker="<?= (int)$r['locker_number'] ?>"
                      data-method="<?= e($r['method']) ?>"
                      data-amount="<?= e((string)$r['amount']) ?>"
                      data-ref="<?= e($r['reference_no']) ?>"
                      data-duration="<?= e($r['duration']) ?>">
                  <i class="fa-regular fa-eye"></i><span class="label">View</span>
              </button>
                  <button class="icon-btn with-text btn-edit" 
                          data-id="<?= (int)$r['id'] ?>"
                          data-user_id="<?= (int)$r['user_id'] ?>"
                          data-locker="<?= (int)$r['locker_number'] ?>"
                          data-method="<?= e($r['method']) ?>"
                          data-amount="<?= e((string)$r['amount']) ?>"
                          data-ref="<?= e($r['reference_no']) ?>"
                          data-duration="<?= e($r['duration']) ?>"
                          data-created_at="<?= e(date('Y-m-d\TH:i', strtotime($r['created_at']))) ?>">
                    <i class="fa-regular fa-pen-to-square"></i><span class="label">Edit</span>
                  </button>
                  <form method="post" class="inline del-form" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                    <input type="hidden" name="action" value="delete_payment">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="button" class="icon-btn with-text btn-delete">  <i class="fa-regular fa-trash-can"></i><span class="label">Delete</span></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" aria-label="Pagination">
        <?php
          $makePageUrl = function(int $p) {
            $qp = $_GET; $qp['page']=$p; return '?' . e(http_build_query($qp));
          };
          $start = max(1, $page-2);
          $end   = min($totalPages, $page+2);
          if ($start > 1) echo '<a class="page-link" href="'.$makePageUrl(1).'">&laquo;</a>';
          if ($page > 1)  echo '<a class="page-link" href="'.$makePageUrl($page-1).'">&lsaquo;</a>';
          for ($i=$start; $i<=$end; $i++) {
            $cls = $i===$page ? 'page-link active':'page-link';
            echo '<a class="'.$cls.'" href="'.$makePageUrl($i).'">'.$i.'</a>';
          }
          if ($page < $totalPages) echo '<a class="page-link" href="'.$makePageUrl($page+1).'">&rsaquo;</a>';
          if ($end < $totalPages)  echo '<a class="page-link" href="'.$makePageUrl($totalPages).'">&raquo;</a>';
        ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- VIEW MODAL -->
<div class="modal" id="view-modal" aria-hidden="true" aria-labelledby="view-title" role="dialog">
  <div class="modal-card">
    <div class="modal-head">
      <strong id="view-title"><i class="fa-regular fa-eye"></i> Payment Details</strong>
      <button class="icon-btn" data-close="#view-modal" aria-label="Close"><i class="fa-regular fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="view-body"></div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-close="#view-modal">Close</button>
    </div>
  </div>
</div>

<!-- CREATE MODAL -->
<div class="modal" id="create-modal" aria-hidden="true" aria-labelledby="create-title" role="dialog">
  <div class="modal-card">
    <div class="modal-head">
      <strong id="create-title"><i class="fa-solid fa-plus"></i> Add Payment</strong>
      <button class="icon-btn" data-close="#create-modal" aria-label="Close"><i class="fa-regular fa-xmark"></i></button>
    </div>
    <form method="post" class="modal-body" id="create-form" novalidate>
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="create_payment">
      <div class="modal-grid">
        <!-- Searchable User Picker -->
        <div class="field">
          <label for="c_user_input">User</label>
          <div class="combo" id="c_combo">
            <input type="text" id="c_user_input" placeholder="Search name or email" autocomplete="off" value="<?= e((string)($form_old['c_user_label'] ?? '')) ?>">
            <input type="hidden" id="c_user_id" name="user_id" value="<?= e((string)($form_old['user_id'] ?? '')) ?>">
            <button class="clear" type="button" title="Clear"><i class="fa-regular fa-xmark"></i></button>
            <div class="list" id="c_user_list" hidden></div>
          </div>
          <?php if(isset($form_errs['user_id'])): ?><div class="error"><?= e($form_errs['user_id']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="c_locker">Locker #</label>
          <select id="c_locker" name="locker_number" required>
            <option value="">Select</option>
            <?php foreach ($lockers as $lk): ?>
              <option value="<?= (int)$lk ?>" <?= ((int)($form_old['locker_number'] ?? 0) === (int)$lk)?'selected':'' ?>><?= (int)$lk ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($form_errs['locker_number'])): ?><div class="error"><?= e($form_errs['locker_number']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="c_method">Method</label>
          <select id="c_method" name="method" required>
            <option value="">Select</option>
            <option value="GCash" <?= (($form_old['method'] ?? '')==='GCash')?'selected':'' ?>>GCash</option>
            <option value="Maya"  <?= (($form_old['method'] ?? '')==='Maya')?'selected':''  ?>>Maya</option>
          </select>
          <?php if(isset($form_errs['method'])): ?><div class="error"><?= e($form_errs['method']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="c_amount">Amount (₱)</label>
          <input type="number" step="0.01" id="c_amount" name="amount" value="<?= e((string)($form_old['amount'] ?? '')) ?>" required placeholder="0.00">
          <?php if(isset($form_errs['amount'])): ?><div class="error"><?= e($form_errs['amount']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="c_reference_no">Reference #</label>
          <div style="display:flex; gap:.4rem;">
            <input type="text" id="c_reference_no" name="reference_no" value="<?= e((string)($form_old['reference_no'] ?? '')) ?>" required placeholder="e.g., PAY-20250906-001">
            <button class="btn btn-soft" type="button" id="gen-ref"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate</button>
          </div>
          <?php if(isset($form_errs['reference_no'])): ?><div class="error"><?= e($form_errs['reference_no']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="c_duration">Duration</label>
          <input type="text" id="c_duration" name="duration" value="<?= e((string)($form_old['duration'] ?? '1h')) ?>" required list="duration-presets" placeholder="e.g., 30m, 1h, 2h">
          <datalist id="duration-presets">
            <option value="30m"><option value="1h"><option value="2h"><option value="6h"><option value="12h"><option value="24h">
          </datalist>
          <?php if(isset($form_errs['duration'])): ?><div class="error"><?= e($form_errs['duration']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="c_created_at">Date &amp; time (optional)</label>
          <input type="datetime-local" id="c_created_at" name="created_at" value="<?= e((string)($form_old['created_at'] ?? '')) ?>">
          <small style="color:var(--muted);">Leave blank to use current time</small>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-close="#create-modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="edit-modal" aria-hidden="true" aria-labelledby="edit-title" role="dialog">
  <div class="modal-card">
    <div class="modal-head">
      <strong id="edit-title"><i class="fa-regular fa-pen-to-square"></i> Edit Payment</strong>
      <button class="icon-btn" data-close="#edit-modal" aria-label="Close"><i class="fa-regular fa-xmark"></i></button>
    </div>
    <form method="post" class="modal-body" id="edit-form" novalidate>
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="update_payment">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-grid">
        <!-- Searchable User Picker (edit) -->
        <div class="field">
          <label for="e_user_input">User</label>
          <div class="combo" id="e_combo">
            <input type="text" id="e_user_input" placeholder="Search name or email" autocomplete="off">
            <input type="hidden" id="e_user_id" name="user_id">
            <button class="clear" type="button" title="Clear"><i class="fa-regular fa-xmark"></i></button>
            <div class="list" id="e_user_list" hidden></div>
          </div>
          <?php if(isset($edit_errs['user_id'])): ?><div class="error"><?= e($edit_errs['user_id']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="e_locker">Locker #</label>
          <select id="e_locker" name="locker_number" required>
            <option value="">Select</option>
            <?php foreach ($lockers as $lk): ?>
              <option value="<?= (int)$lk ?>"><?= (int)$lk ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($edit_errs['locker_number'])): ?><div class="error"><?= e($edit_errs['locker_number']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="e_method">Method</label>
          <select id="e_method" name="method" required>
            <option value="GCash">GCash</option>
            <option value="Maya">Maya</option>
          </select>
          <?php if(isset($edit_errs['method'])): ?><div class="error"><?= e($edit_errs['method']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="e_amount">Amount (₱)</label>
          <input type="number" step="0.01" id="e_amount" name="amount" required>
          <?php if(isset($edit_errs['amount'])): ?><div class="error"><?= e($edit_errs['amount']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="e_reference_no">Reference #</label>
          <input type="text" id="e_reference_no" name="reference_no" required>
          <?php if(isset($edit_errs['reference_no'])): ?><div class="error"><?= e($edit_errs['reference_no']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="e_duration">Duration</label>
          <input type="text" id="e_duration" name="duration" required list="duration-presets">
          <?php if(isset($edit_errs['duration'])): ?><div class="error"><?= e($edit_errs['duration']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="e_created_at">Date &amp; time (leave as is to keep)</label>
          <input type="datetime-local" id="e_created_at" name="created_at">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-close="#edit-modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const $  = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  // Flash
  <?php if ($flash): ?>
  Swal.fire({
    icon: <?= json_encode($flash['type']==='success' ? 'success' : 'error') ?>,
    title: <?= json_encode($flash['type']==='success' ? 'Success' : 'Oops') ?>,
    text: <?= json_encode($flash['msg'] ?? '') ?>,
    confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb'
  });
  <?php endif; ?>

  // Open/Close Modals
  const open = (id) => $(id)?.classList.add('open');
  const close = (id) => $(id)?.classList.remove('open');
  $$('#open-create, #open-create-2').forEach(b => b?.addEventListener('click', () => {
    // Auto-fill reference if empty
    const ref = $('#c_reference_no');
    if (ref && !ref.value) genRef(ref);
    open('#create-modal');
  }));
  $$('[data-close]').forEach(b => b.addEventListener('click', () => close(b.getAttribute('data-close'))));
  $$('.modal').forEach(m => {
    m.addEventListener('click', (e) => { if (e.target === m) close('#'+m.id); });
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close('#'+m.id); }, { once: true });
  });

  // Reference generator
  function genRef(inputEl) {
    const d = new Date();
    const pad = n => n.toString().padStart(2,'0');
    const val = `PAY-${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
    inputEl.value = val;
  }
  $('#gen-ref')?.addEventListener('click', () => genRef($('#c_reference_no')));

  // Copy to clipboard
  $$('[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      const sel = btn.getAttribute('data-copy');
      const el = $(sel);
      if (!el) return;
      const text = el.textContent.trim();
      navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ icon:'success', title:'Copied', text:'Reference copied to clipboard', timer:1200, showConfirmButton:false });
      });
    });
  });

  // Delete confirm
  $$('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      Swal.fire({
        title: 'Delete payment?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete',
      }).then((res) => { if (res.isConfirmed) btn.closest('form').submit(); });
    });
  });

  // View modal fill
  $$('.btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = $('#view-body'); body.innerHTML = '';
      const row = {
        Date: btn.dataset.date,
        User: btn.dataset.user,
        Email: btn.dataset.email,
        'Locker #': '#'+btn.dataset.locker,
        Method: btn.dataset.method,
        Amount: '₱ ' + (parseFloat(btn.dataset.amount || '0').toFixed(2)),
        'Reference #': btn.dataset.ref,
        Duration: btn.dataset.duration
      };
      const frag = document.createDocumentFragment();
      Object.entries(row).forEach(([k,v]) => {
        const wrap = document.createElement('div'); wrap.className = 'field';
        const lab = document.createElement('label'); lab.textContent = k;
        const val = document.createElement('div'); val.textContent = v; val.style.fontWeight = '800';
        wrap.append(lab,val); frag.append(wrap);
      });
      body.append(frag);
      open('#view-modal');
    });
  });

  // ---------------- Searchable user picker ----------------
  const USERS = <?= json_encode($usersForJs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

  function initUserPicker(comboId, inputId, listId, hiddenId) {
    const combo = $(comboId); if (!combo) return;
    const input = $(inputId), list = $(listId), hidden = $(hiddenId);
    const clearBtn = combo.querySelector('.clear');

    function render(items) {
      list.innerHTML = '';
      if (!items.length) {
        const d = document.createElement('div');
        d.className = 'item'; d.textContent = 'No matches'; d.style.color='var(--muted)'; d.style.cursor='default';
        list.appendChild(d); return;
      }
      items.slice(0, 12).forEach((u, idx) => {
        const div = document.createElement('div');
        div.className = 'item' + (idx===0 ? ' active' : '');
        div.setAttribute('role','option');
        div.dataset.id = u.id; div.textContent = u.label;
        list.appendChild(div);
      });
    }

    function openList() { list.hidden = false; }
    function closeList() { list.hidden = true; }

    function filter(q) {
      q = (q||'').toLowerCase().trim();
      const items = q ? USERS.filter(u => u.label.toLowerCase().includes(q)) : USERS;
      render(items); openList();
    }

    function selectByEl(el) {
      const id = parseInt(el?.dataset.id || '0', 10);
      if (!id) return;
      hidden.value = String(id);
      input.value  = USERS.find(u => u.id === id)?.label || '';
      closeList();
    }

    input.addEventListener('input', () => { hidden.value=''; filter(input.value); });
    input.addEventListener('focus', () => { filter(input.value); });
    input.addEventListener('keydown', (e) => {
      const options = Array.from(list.querySelectorAll('.item'));
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const cur = list.querySelector('.item.active');
        let idx = options.indexOf(cur);
        if (e.key === 'ArrowDown') idx = Math.min(options.length-1, idx+1);
        if (e.key === 'ArrowUp')   idx = Math.max(0, idx-1);
        options.forEach(o => o.classList.remove('active'));
        if (options[idx]) options[idx].classList.add('active');
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const cur = list.querySelector('.item.active') || options[0];
        if (cur) selectByEl(cur);
      } else if (e.key === 'Escape') {
        closeList();
      }
    });
    list.addEventListener('mousedown', (e) => {
      const el = e.target.closest('.item'); if (el) selectByEl(el);
    });
    document.addEventListener('click', (e) => { if (!combo.contains(e.target)) closeList(); });
    clearBtn?.addEventListener('click', () => { input.value=''; hidden.value=''; input.focus(); filter(''); });

    // If there is an initial hidden value, show label
    if (hidden.value) {
      const id = parseInt(hidden.value, 10);
      const label = USERS.find(u => u.id === id)?.label || '';
      if (label) input.value = label;
    }
  }

  // Init pickers
  initUserPicker('#c_combo', '#c_user_input', '#c_user_list', '#c_user_id');
  initUserPicker('#e_combo', '#e_user_input', '#e_user_list', '#e_user_id');

  // Edit modal prefill
  function findUserLabel(id) {
    id = parseInt(id,10);
    const u = USERS.find(u => u.id===id);
    return u ? u.label : '';
  }
  $$('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      $('#e_id').value = btn.dataset.id;
      $('#e_user_id').value = btn.dataset.user_id;
      $('#e_user_input').value = findUserLabel(btn.dataset.user_id);
      $('#e_locker').value = btn.dataset.locker;
      $('#e_method').value = btn.dataset.method;
      $('#e_amount').value = btn.dataset.amount;
      $('#e_reference_no').value = btn.dataset.ref;
      $('#e_duration').value = btn.dataset.duration;
      $('#e_created_at').value = btn.dataset.created_at;
      open('#edit-modal');
    });
  });

})();
</script>
