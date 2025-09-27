<?php

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}


ob_start();

date_default_timezone_set('Asia/Manila');


include '../../includes/admin_header.php';


$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}


$tzOffset = (new DateTime())->format('P'); // e.g. +08:00
$conn->query("SET time_zone = '{$conn->real_escape_string($tzOffset)}'");


function _ymd($s){
  $d = DateTime::createFromFormat('Y-m-d', $s);
  return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
function _range_default(){
  $end = new DateTime('today');
  $start = (clone $end)->modify('-29 days');
  return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}
function _bind_range_method(mysqli_stmt $stmt, $startDT, $endDT, $method){
  if ($method === 'all') $stmt->bind_param('ss', $startDT, $endDT);
  else $stmt->bind_param('sss', $startDT, $endDT, $method);
}
function _fetch_all_stmt(mysqli_stmt $stmt){
  $res = $stmt->get_result();
  $rows = [];
  if ($res) { while($row = $res->fetch_assoc()) $rows[] = $row; }
  return $rows;
}

if (isset($_GET['sales_json'])) {

  while (ob_get_level()) { ob_end_clean(); }

  header('Content-Type: application/json; charset=utf-8');

  $allowed = ['all','GCash','Maya','Wallet'];
  $method  = isset($_GET['method']) ? $_GET['method'] : 'all';
  $method  = in_array($method, $allowed, true) ? $method : 'all';

  $start = _ymd($_GET['start'] ?? '') ?: null;
  $end   = _ymd($_GET['end'] ?? '') ?: null;
  if (!$start || !$end) list($start, $end) = _range_default();


  $startDT = $start . ' 00:00:00';
  $endDT   = $end   . ' 23:59:59';

  $mCond = ($method === 'all') ? '' : ' AND method = ? ';


  $stmt = $conn->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(amount),0) AS revenue, COALESCE(AVG(amount),0) AS aov FROM payments WHERE created_at BETWEEN ? AND ? {$mCond}");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $kpis = $stmt->get_result()->fetch_assoc() ?: ['orders'=>0,'revenue'=>0,'aov'=>0];
  $stmt->close();

  $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) AS users FROM payments WHERE created_at BETWEEN ? AND ? {$mCond}");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $uc = $stmt->get_result()->fetch_assoc();
  $kpis['unique_customers'] = (int)($uc['users'] ?? 0);
  $stmt->close();


  $stmt = $conn->prepare("
    SELECT DATE(created_at) AS day, COALESCE(SUM(amount),0) AS revenue, COUNT(*) AS orders
    FROM payments
    WHERE created_at BETWEEN ? AND ? {$mCond}
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $dailyRows = _fetch_all_stmt($stmt);
  $stmt->close();


  $dailyMap = [];
  foreach ($dailyRows as $r) $dailyMap[$r['day']] = ['revenue'=>(float)$r['revenue'], 'orders'=>(int)$r['orders']];
  $daily = [];
  $iter = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
  foreach ($iter as $d) {
    $k = $d->format('Y-m-d');
    $daily[] = [
      'day' => $k,
      'revenue' => isset($dailyMap[$k]) ? $dailyMap[$k]['revenue'] : 0.0,
      'orders' => isset($dailyMap[$k]) ? $dailyMap[$k]['orders'] : 0
    ];
  }


  $stmt = $conn->prepare("
    SELECT method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS revenue
    FROM payments
    WHERE created_at BETWEEN ? AND ? {$mCond}
    GROUP BY method
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $by_method = _fetch_all_stmt($stmt);
  $stmt->close();


  $stmt = $conn->prepare("
    SELECT duration, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS revenue
    FROM payments
    WHERE created_at BETWEEN ? AND ? {$mCond}
    GROUP BY duration
    ORDER BY revenue DESC
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $by_duration = _fetch_all_stmt($stmt);
  $stmt->close();


  $stmt = $conn->prepare("
    SELECT u.id AS user_id,
           CONCAT(u.first_name,' ',u.last_name) AS name,
           u.email,
           COUNT(p.id) AS orders,
           COALESCE(SUM(p.amount),0) AS revenue
    FROM payments p
    JOIN users u ON u.id = p.user_id
    WHERE p.created_at BETWEEN ? AND ? {$mCond}
    GROUP BY u.id
    ORDER BY revenue DESC
    LIMIT 5
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $top_customers = _fetch_all_stmt($stmt);
  $stmt->close();

 
  $stmt = $conn->prepare("
    SELECT locker_number, COUNT(*) AS orders, COALESCE(SUM(amount),0) AS revenue
    FROM payments
    WHERE created_at BETWEEN ? AND ? {$mCond}
    GROUP BY locker_number
    ORDER BY revenue DESC
    LIMIT 5
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $top_lockers = _fetch_all_stmt($stmt);
  $stmt->close();

  $stmt = $conn->prepare("
    SELECT p.id, p.reference_no, p.method, p.amount, p.duration, p.locker_number, p.created_at,
           u.first_name, u.last_name, u.email
    FROM payments p
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.created_at BETWEEN ? AND ? {$mCond}
    ORDER BY p.created_at DESC
    LIMIT 100
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $recent = _fetch_all_stmt($stmt);
  $stmt->close();

  echo json_encode([
    'success' => true,
    'range' => ['start'=>$start, 'end'=>$end, 'method'=>$method],
    'kpis' => [
      'revenue' => (float)($kpis['revenue'] ?? 0),
      'orders' => (int)($kpis['orders'] ?? 0),
      'aov' => (float)($kpis['aov'] ?? 0),
      'unique_customers' => (int)($kpis['unique_customers'] ?? 0)
    ],
    'daily' => $daily,
    'by_method' => $by_method,
    'by_duration' => $by_duration,
    'top_customers' => $top_customers,
    'top_lockers' => $top_lockers,
    'recent' => $recent
  ]);
  exit();
}

if (isset($_GET['sales_csv'])) {

  while (ob_get_level()) { ob_end_clean(); }

  $allowed = ['all','GCash','Maya','Wallet'];
  $method  = isset($_GET['method']) ? $_GET['method'] : 'all';
  $method  = in_array($method, $allowed, true) ? $method : 'all';

  $start = _ymd($_GET['start'] ?? '') ?: null;
  $end   = _ymd($_GET['end'] ?? '') ?: null;
  if (!$start || !$end) list($start, $end) = _range_default();

  $startDT = $start . ' 00:00:00';
  $endDT   = $end   . ' 23:59:59';
  $mCond = ($method === 'all') ? '' : ' AND p.method = ? ';

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="sales_'.$start.'_to_'.$end.($method !== 'all' ? '_'.$method : '').'.csv"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['Payment ID','Date/Time','User ID','Name','Email','Method','Reference #','Duration','Locker #','Amount']);

  $stmt = $conn->prepare("
    SELECT p.id, p.created_at, u.id AS user_id, CONCAT(u.first_name,' ',u.last_name) AS name, u.email,
           p.method, p.reference_no, p.duration, p.locker_number, p.amount
    FROM payments p
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.created_at BETWEEN ? AND ? {$mCond}
    ORDER BY p.created_at ASC
  ");
  _bind_range_method($stmt, $startDT, $endDT, $method);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      fputcsv($out, [
        $r['id'],
        date('Y-m-d H:i:s', strtotime($r['created_at'])),
        $r['user_id'],
        $r['name'],
        $r['email'],
        $r['method'],
        $r['reference_no'],
        $r['duration'],
        $r['locker_number'],
        number_format((float)$r['amount'], 2, '.', '')
      ]);
    }
  }
  fclose($out);
  exit();
}


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


$total_lockers         = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr")->fetch_assoc()['c'];
$maintenance_lockers   = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE maintenance=1")->fetch_assoc()['c'];
$occupied_lockers      = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE status='occupied' AND maintenance=0")->fetch_assoc()['c'];
$available_lockers     = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE status='available' AND maintenance=0")->fetch_assoc()['c'];
$hold_lockers          = (int)$conn->query("SELECT COUNT(*) AS c FROM locker_qr WHERE status='hold' AND maintenance=0")->fetch_assoc()['c'];
$total_users           = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];


$locker_sql = "
    SELECT
        l.locker_number, l.status, l.code, l.item, l.expires_at, l.duration_minutes, l.maintenance,
        u.first_name, u.last_name, u.email
    FROM locker_qr l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.locker_number ASC
";
$locker_result = $conn->query($locker_sql);


$available_result = $conn->query("SELECT locker_number FROM locker_qr WHERE status='available' AND maintenance=0");


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


$recent_rs = $conn->query("
    SELECT locker_number, user_fullname, user_email, duration_minutes, expires_at, used_at, code
    FROM locker_history
    WHERE archived = 0
    ORDER BY used_at DESC
    LIMIT 150
");


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
<link rel="icon" href="/kandado/assets/icon/icon_tab.png" sizes="any">

<link rel="stylesheet" href="../../assets/css/admin_dashboard.css">
</head>
<body>
<main id="content" aria-label="Admin dashboard">

  <div class="page-head">
    <h1 class="page-title" style="color:#223b8f">Admin Dashboard</h1>
    <div class="page-actions">
      <span class="tag" style="color:#223b8f"><i class="fa-regular fa-calendar"></i><?= date('M d, Y') ?></span>
    </div>
  </div>

 
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


  <div class="section-grid">
  
    <section class="panel" aria-label="Manage lockers">
      <div class="panel-head">
        <div class="panel-title"><i class="fa-solid fa-screwdriver-wrench"></i>Manage Lockers</div>
      </div>

  
      <div class="filter-bar" role="toolbar" aria-label="Locker filters">
        <button class="filter-chip" data-filter="all" aria-pressed="true"><i class="fa-solid fa-layer-group"></i> All <span class="count"><?= (int)$total_lockers ?></span></button>
        <button class="filter-chip" data-filter="available" aria-pressed="false"><i class="fa-solid fa-unlock"></i> Available <span class="count"><?= (int)$available_lockers ?></span></button>
        <button class="filter-chip" data-filter="occupied" aria-pressed="false"><i class="fa-solid fa-lock"></i> Occupied <span class="count"><?= (int)$occupied_lockers ?></span></button>
        <button class="filter-chip" data-filter="hold" aria-pressed="false"><i class="fa-solid fa-hand"></i> Hold <span class="count"><?= (int)$hold_lockers ?></span></button>
        <button class="filter-chip" data-filter="maintenance" aria-pressed="false"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance <span class="count"><?= (int)$maintenance_lockers ?></span></button>
      </div>

      <div class="panel-body">
    
        <div style="display:flex; justify-content:flex-end; width:100%;">
          <button id="globalPowerAlert" class="global-power-toggle" type="button">
            <i class="fa-solid fa-bolt"></i> Power Notice to Users
          </button>
        </div>

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

   
    <section class="panel panel--sticky" aria-label="Force unlock and assign">
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

  <!-- ==========================
       SALES REPORT (new section)
       ========================== -->
  <section style="margin-top:16px;">
    <div class="panel">
      <div class="panel-head sales-head">
        <div class="panel-title"><i class="fa-solid fa-peso-sign"></i> Sales Report</div>
        <div class="sales-actions"></div>
      </div>

      <!-- Controls -->
      <div class="sales-controls" role="toolbar" aria-label="Sales filters">
        <button class="sales-chip" data-range="7d" aria-pressed="false"><i class="fa-solid fa-calendar-week"></i> 7D</button>
        <button class="sales-chip" data-range="30d" aria-pressed="true"><i class="fa-solid fa-calendar-days"></i> 30D</button>
        <button class="sales-chip" data-range="mtd" aria-pressed="false"><i class="fa-solid fa-calendar-check"></i> MTD</button>
        <button class="sales-chip" data-range="qtd" aria-pressed="false"><i class="fa-solid fa-calendar"></i> QTD</button>
        <button class="sales-chip" data-range="ytd" aria-pressed="false"><i class="fa-solid fa-calendar"></i> YTD</button>
        <button class="sales-chip" data-range="all" aria-pressed="false"><i class="fa-solid fa-infinity"></i> All</button>

        <div style="flex:1"></div>

        <label class="small" for="salesStart" style="min-width:80px;color:#223b8f;font-weight:800;">Start</label>
        <input type="date" id="salesStart" class="input" style="max-width:170px;">
        <label class="small" for="salesEnd" style="min-width:60px;color:#223b8f;font-weight:800;">End</label>
        <input type="date" id="salesEnd" class="input" style="max-width:170px;">

        <select id="salesMethod" class="select" style="max-width:160px;">
          <option value="all">All Methods</option>
          <option value="GCash">GCash</option>
          <option value="Maya">Maya</option>
          <option value="Wallet">Wallet</option>
        </select>

        <button id="applySales" class="sales-apply"><i class="fa-solid fa-filter"></i> Apply</button>
      </div>

      <!-- KPIs -->
      <div class="panel-body">
        <div class="sales-kpis" aria-label="Sales KPIs">
          <div class="kpi kpi--revenue">
            <div class="ico"><i class="fa-solid fa-coins"></i></div>
            <div class="meta">
              <div class="val" id="kpiRevenue">₱0.00</div>
              <div class="lbl">Total Revenue</div>
            </div>
          </div>
          <div class="kpi kpi--orders">
            <div class="ico"><i class="fa-solid fa-receipt"></i></div>
            <div class="meta">
              <div class="val" id="kpiOrders">0</div>
              <div class="lbl">Transactions</div>
            </div>
          </div>
          <div class="kpi kpi--aov">
            <div class="ico"><i class="fa-solid fa-scale-balanced"></i></div>
            <div class="meta">
              <div class="val" id="kpiAOV">₱0.00</div>
              <div class="lbl">Avg. Order Value</div>
            </div>
          </div>
          <div class="kpi kpi--customers">
            <div class="ico"><i class="fa-solid fa-user-check"></i></div>
            <div class="meta">
              <div class="val" id="kpiCustomers">0</div>
              <div class="lbl">Unique Customers</div>
            </div>
          </div>
        </div>

        <!-- Charts -->
        <div class="sales-grid">
          <div class="chart-card">
            <h4 class="chart-title">Revenue Over Time</h4>
            <canvas id="salesTimeChart" aria-label="Revenue over time" role="img"></canvas>
          </div>
          <div class="chart-card">
            <h4 class="chart-title">Sales by Method</h4>
            <canvas id="salesMethodChart" aria-label="Sales by method" role="img"></canvas>
          </div>
          <div class="chart-card">
            <h4 class="chart-title">Revenue by Duration</h4>
            <canvas id="salesDurationChart" aria-label="Revenue by duration" role="img"></canvas>
          </div>
        </div>

        <!-- Tables -->
        <div class="sales-tables">
          <div class="table-card">
            <h4 class="table-title"><i class="fa-solid fa-user-tie"></i> Top Customers</h4>
            <div class="table-viewport small">
              <table class="user-table" id="topCustomersTable">
                <thead><tr><th>User</th><th>Email</th><th>Orders</th><th>Total Spend</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="table-card">
            <h4 class="table-title"><i class="fa-solid fa-box-archive"></i> Top Lockers (by Revenue)</h4>
            <div class="table-viewport small">
              <table class="user-table" id="topLockersTable">
                <thead><tr><th>Locker #</th><th>Orders</th><th>Total Revenue</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent payments -->
        <div class="panel" style="margin-top:12px;">
          <div class="panel-head">
            <div class="panel-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Payments</div>
          </div>
          <div class="panel-body">
            <div class="activity-list" id="salesRecentList"></div>
            <div id="salesActivityPagination" aria-label="Sales activity pagination"></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
  window.DASHBOARD_DATA = {
    dates: <?= json_encode($dates) ?>,
    usage_counts: <?= json_encode($usage_counts) ?>,
    status: {
      occupied: <?= (int)$occupied ?>,
      available: <?= (int)$available ?>,
      hold: <?= (int)$hold ?>,
      maintenance: <?= (int)$maint ?>
    }
  };
</script>


<script src="../../assets/js/admin_dashboard.js"></script>
</body>
</html>
