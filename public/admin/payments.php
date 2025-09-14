<?php
/**
 * payments.php — Admin payments panel (fixed + searchable user picker + inline copy + friendly action colors)
 *
 * NOTE: Duration is now entered as a human string like "2d 3h 15m" and is stored as total minutes.
 *       It is displayed as "Xd Yh Zm" (omitting zero parts, e.g., "7d 20m", "1h 5m", "30m").
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


function duration_to_minutes(string $raw): ?int {
  $s = strtolower(trim($raw));
  if ($s === '') return null;

  // Legacy format DD:HH:MM
  if (preg_match('/^(\d+):([0-1]?\d|2[0-3]):([0-5]?\d)$/', $s, $m)) {
    $d = (int)$m[1]; $h = (int)$m[2]; $min = (int)$m[3];
    return $d * 1440 + $h * 60 + $min;
  }

  // "2d 3h 15m" (order-insensitive)
  if (preg_match_all('/(\d+)\s*([dhm])/i', $s, $matches, PREG_SET_ORDER)) {
    $total = 0;
    foreach ($matches as $part) {
      $n = (int)$part[1];
      switch (strtolower($part[2])) {
        case 'd': $total += $n * 1440; break;
        case 'h': $total += $n * 60;   break;
        case 'm': $total += $n;        break;
      }
    }
    return $total;
  }

  // Plain digits -> treat as minutes
  if (ctype_digit($s)) return (int)$s;

  return null;
}

/** Convert minutes (or any supported string) to "Xd Yh Zm", omitting zeros. */
function minutes_to_human($value): string {
  if ($value === null || $value === '') return '';
  $mins = is_numeric($value) ? (int)$value : duration_to_minutes((string)$value);
  if ($mins === null || $mins < 0) return '';

  $d = intdiv($mins, 1440);
  $mins -= $d * 1440;
  $h = intdiv($mins, 60);
  $m = $mins - $h * 60;

  $parts = [];
  if ($d > 0) $parts[] = $d . 'd';
  if ($h > 0) $parts[] = $h . 'h';
  // Always show minutes (even if 0) when everything else is 0
  if ($m > 0 || !$parts) $parts[] = $m . 'm';

  return implode(' ', $parts);
}
/* ----------------------------------------------------------------------- */

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
      $r['locker_number'], $r['method'], $r['amount'], $r['reference_no'], minutes_to_human($r['duration'])
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

  // --------- Duration (ONLY change): accept "2d 3h 15m" and store minutes ---------
  $durationRaw = trim((string)($in['duration'] ?? ''));
  if ($durationRaw === '') {
    $errors['duration'] = 'Duration is required.';
  } else {
    $mins = duration_to_minutes($durationRaw);
    if ($mins === null) {
      $errors['duration'] = 'Invalid duration. Use e.g., "2d 3h 15m", "1h", or "30m".';
    } else {
      $clean['duration'] = (string)$mins; // store as minutes
    }
  }
  // ---------------------------------------------------------------------------

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

// Build small array for the JS user picker (id + label)
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

$limit = (int)$perPage;
$off   = (int)$offset;

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

<!-- external styles -->
<link rel="stylesheet" href="../../assets/css/payments.css">

<!-- bootstrap app data for external JS -->
<script>
window.APP = {
  USERS: <?= json_encode($usersForJs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>,
  FLASH: <?= json_encode($flash ? [
    'type'  => ($flash['type']==='success' ? 'success' : 'error'),
    'title' => ($flash['type']==='success' ? 'Success' : 'Oops'),
    'msg'   => ($flash['msg'] ?? '')
  ] : null, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>,
  CSRF: <?= json_encode($CSRF) ?>
};
</script>

<!-- external script (deferred) -->
<script src="../../assets/js/payments.js" defer></script>
<title>Payments · Admin</title>
<link rel="icon" type="image/png" sizes="any" href="../../assets/icon/icon_tab.png">
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
        <div class="label">Total Revenue (All-time)</div>
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
      <div class="metric-trend">rolling 7-day window</div>
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
          <label for="min">Amount (min-max)</label>
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
        <div style="margin-top:.7rem;"><button class="btn btn-primary" id="open-create-2"><i class="fa-solid fa-plus" style ="color:white;"></i> Add Payment</button></div>
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
              <th style="width:200px;">Actions</th>
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

                <!-- Reference # + Copy inline -->
                <td data-label="Reference #">
                  <div class="ref-inline">
                    <code id="ref-<?= (int)$r['id'] ?>"><?= e($r['reference_no']) ?></code>
                    <button class="icon-btn copy-btn" title="Copy reference" aria-label="Copy reference"
                            data-copy="#ref-<?= (int)$r['id'] ?>">
                      <i class="fa-regular fa-copy"></i>
                    </button>
                  </div>
                </td>

                <td data-label="Duration"><?= e(minutes_to_human($r['duration'])) ?></td>

                <td class="actions" data-label="Actions">
                  <button class="icon-btn with-text view btn-view"
                          aria-label="View payment"
                          data-id="<?= (int)$r['id'] ?>"
                          data-date="<?= e(dt_out($r['created_at'])) ?>"
                          data-user="<?= e($userFull) ?>"
                          data-email="<?= e($r['email']) ?>"
                          data-locker="<?= (int)$r['locker_number'] ?>"
                          data-method="<?= e($r['method']) ?>"
                          data-amount="<?= e((string)$r['amount']) ?>"
                          data-ref="<?= e($r['reference_no']) ?>"
                          data-duration="<?= e(minutes_to_human($r['duration'])) ?>">
                    <i class="fa-regular fa-eye"></i><span class="label">View</span>
                  </button>

                  <button class="icon-btn with-text edit btn-edit"
                          data-id="<?= (int)$r['id'] ?>"
                          data-user_id="<?= (int)$r['user_id'] ?>"
                          data-locker="<?= (int)$r['locker_number'] ?>"
                          data-method="<?= e($r['method']) ?>"
                          data-amount="<?= e((string)$r['amount']) ?>"
                          data-ref="<?= e($r['reference_no']) ?>"
                          data-duration="<?= e(minutes_to_human($r['duration'])) ?>"
                          data-created_at="<?= e(date('Y-m-d\TH:i', strtotime($r['created_at']))) ?>">
                    <i class="fa-regular fa-pen-to-square"></i><span class="label">Edit</span>
                  </button>

                  <form method="post" class="inline del-form" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                    <input type="hidden" name="action" value="delete_payment">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="button" class="icon-btn with-text delete btn-delete">
                      <i class="fa-regular fa-trash-can"></i><span class="label">Delete</span>
                    </button>
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
      <button class="icon-btn" data-close="#view-modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
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
      <button class="icon-btn" data-close="#create-modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
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
            <button class="clear" type="button" title="Clear"><i class="fa-solid fa-xmark"></i></button>
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
          <label for="c_duration">Duration (e.g., 2d 3h 15m)</label>
          <input type="text" id="c_duration" name="duration"
                 value="<?= e((string)(isset($form_old['duration']) && $form_old['duration'] !== '' ? minutes_to_human($form_old['duration']) : '1h')) ?>"
                 required list="duration-presets" placeholder="e.g., 30m, 1h, 2d 3h 15m">
          <datalist id="duration-presets">
            <option value="30m"><option value="1h"><option value="2h"><option value="6h"><option value="12h"><option value="1d"><option value="7d">
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
      <button class="icon-btn" data-close="#edit-modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
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
            <button class="clear" type="button" title="Clear"><i class="fa-solid fa-xmark"></i></button>
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
          <label for="e_duration">Duration (e.g., 2d 3h 15m)</label>
          <input type="text" id="e_duration" name="duration" required list="duration-presets" placeholder="e.g., 30m, 1h, 2d 3h 15m">
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
