  <?php
  // locker_history_admin.php — single-file admin view

  // ---------------- Boot ----------------
  if (session_status() === PHP_SESSION_NONE) session_start();

  // Admin-only
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
  }

  // Local display timezone (no effect on DB timestamps)
  @date_default_timezone_set('Asia/Manila');

  // Security headers
  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: no-referrer-when-downgrade');

  // ---------------- CSRF ----------------
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  function csrf_field() {
    $t = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="'.$t.'">';
  }
  function csrf_ok($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
  }

  // ---------------- DB (PDO) ----------------
  $host='localhost'; $dbname='kandado'; $user='root'; $pass='';
  try {
    $pdo = new PDO(
      "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
      $user,
      $pass,
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
  } catch (PDOException $e) {
    http_response_code(500);
    echo 'DB connection failed.';
    exit();
  }

  // ---------------- Helpers ----------------
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
  function toLabelDate($dt){
    $ts = strtotime($dt);
    $today = date('Y-m-d');
    $yest  = date('Y-m-d', strtotime('-1 day'));
    $d = date('Y-m-d', $ts);
    if ($d === $today) return 'Today - '.date('l, F j, Y');
    if ($d === $yest)  return 'Yesterday - '.date('l, F j, Y', $ts);
    return date('l, F j, Y', $ts);
  }
  function groupByDateLabel($rows){
    $g = [];
    foreach ($rows as $r) {
      $label = toLabelDate($r['used_at']);
      $g[$label][] = $r;
    }
    return $g;
  }
  function redirect($url){ header('Location: '.$url); exit(); }

  // ---------------- Inputs (GET) ----------------
  $view = isset($_GET['view']) && $_GET['view']==='archived' ? 'archived' : 'active';
  $archivedFlag = $view === 'archived' ? 1 : 0;

  $filter = $_GET['filter'] ?? 'all';
  $q = trim((string)($_GET['q'] ?? ''));
  $date_from = trim((string)($_GET['date_from'] ?? ''));
  $date_to   = trim((string)($_GET['date_to'] ?? ''));

  // Locker: accept "2" or "locker 2" or any text with a number; keep only the first number
  $lockerRaw = trim((string)($_GET['locker'] ?? ''));
  $locker = '';
  if ($lockerRaw !== '' && preg_match('/\d+/', $lockerRaw, $m)) {
    $locker = $m[0]; // first number sequence only
  }

  // Sorting (whitelist)
  $sort = $_GET['sort'] ?? 'used_at';
  $dir  = strtolower($_GET['dir'] ?? 'desc');
  $allowedSort = ['id','locker_number','user_fullname','user_email','code','duration_minutes','expires_at','used_at'];
  if(!in_array($sort, $allowedSort, true)) $sort = 'used_at';
  $dir = $dir === 'asc' ? 'ASC' : 'DESC';

  // Pagination
  $per_page = (int)($_GET['per_page'] ?? 50);
  if (!in_array($per_page, [25,50,100,200], true)) $per_page = 50;
  $page = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page-1) * $per_page;

  // ---------------- Actions (POST) ----------------
  $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';
    if (!csrf_ok($token)) {
      $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid CSRF token. Please try again.'];
      redirect($_SERVER['REQUEST_URI']);
    }
    if ($action === 'bulk_archive' || $action === 'bulk_unarchive') {
      $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($x)=>$x>0));
      if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $flag = $action === 'bulk_archive' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE locker_history SET archived=? WHERE id IN ($in)");
        $stmt->execute(array_merge([$flag], $ids));
        if ($flag === 1) $_SESSION['last_deleted'] = $ids; // enable Undo for archive
        $_SESSION['flash'] = ['type'=>'success','msg'=>($flag? 'Archived' : 'Restored').' '.count($ids).' record(s).'];
      } else {
        $_SESSION['flash'] = ['type'=>'info','msg'=>'No records were selected.'];
      }
      redirect(preg_replace('/[&?]page=\d+/','',$_SERVER['REQUEST_URI']));
    }
    if ($action === 'undo_last') {
      $ids = array_values(array_filter(array_map('intval', (array)($_SESSION['last_deleted'] ?? [])), fn($x)=>$x>0));
      if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE locker_history SET archived=0 WHERE id IN ($in)")->execute($ids);
        unset($_SESSION['last_deleted']);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Restored last archived selection.'];
      } else {
        $_SESSION['flash'] = ['type'=>'info','msg'=>'Nothing to undo.'];
      }
      redirect($_SERVER['REQUEST_URI']);
    }
  }

  $undoAvailable = !empty($_SESSION['last_deleted']);

  // ---------------- Stats ----------------
  $stats = $pdo->query("SELECT 
      COUNT(CASE WHEN MONTH(used_at)=MONTH(CURDATE()) AND YEAR(used_at)=YEAR(CURDATE()) AND archived=0 THEN 1 END) AS total_used_this_month,
      COUNT(CASE WHEN DATE(used_at)=CURDATE() AND archived=0 THEN 1 END) AS today_used,
      COUNT(CASE WHEN archived=1 THEN 1 END) AS archived_count,
      COUNT(CASE WHEN MONTH(used_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(used_at)=YEAR(CURDATE()-INTERVAL 1 MONTH) AND archived=0 THEN 1 END) AS last_month_used
    FROM locker_history")->fetch();

  // ---------------- Build WHERE ----------------
  [$whereSQL, $params] = (function() use($archivedFlag,$filter,$q,$date_from,$date_to,$locker){
    $w = ['archived = ?']; $p = [$archivedFlag];

    // search box
    if ($q !== '') {
      $like = '%'.$q.'%';
      switch ($filter) {
        case 'locker': $w[]='CAST(locker_number AS CHAR) LIKE ?'; $p[]=$like; break;
        case 'user':   $w[]='user_fullname LIKE ?'; $p[]=$like; break;
        case 'email':  $w[]='user_email LIKE ?'; $p[]=$like; break;
        case 'code':   $w[]='code LIKE ?'; $p[]=$like; break;
        default:
          $w[] = '(user_fullname LIKE ? OR user_email LIKE ? OR code LIKE ? OR CAST(locker_number AS CHAR) LIKE ?)';
          array_push($p, $like, $like, $like, $like);
      }
    }

    // locker filter (exact number)
    if ($locker !== '') { $w[]='locker_number = ?'; $p[] = (int)$locker; }

    // date range (inclusive)
    if ($date_from !== '') { $w[]='DATE(used_at) >= ?'; $p[]=$date_from; }
    if ($date_to   !== '') { $w[]='DATE(used_at) <= ?'; $p[]=$date_to; }

    return [implode(' AND ', $w), $p];
  })();

  // Count for pagination
  $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM locker_history WHERE $whereSQL");
  $countStmt->execute($params);
  $totalRows = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $per_page));
  if ($page > $totalPages) { $page = $totalPages; $offset = ($page-1)*$per_page; }

  $orderSQL = "ORDER BY $sort $dir"; // safe

  // Fetch records
  $listStmt = $pdo->prepare("SELECT id, locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at
                            FROM locker_history WHERE $whereSQL $orderSQL LIMIT ? OFFSET ?");
  $pos = 1;
  foreach ($params as $v) { $listStmt->bindValue($pos++, $v); }
  $listStmt->bindValue($pos++, $per_page, PDO::PARAM_INT);
  $listStmt->bindValue($pos++, $offset, PDO::PARAM_INT);
  $listStmt->execute();
  $records = $listStmt->fetchAll();
  $grouped = groupByDateLabel($records);

  // ---------------- UI ----------------
  include '../../includes/admin_header.php';
  ?>
  <!doctype html>
  <html lang="en">
  <head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Locker History · Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  :root{
    --brand-1:#1e3a8a; --brand-2:#3b82f6; --brand-3:#93c5fd;
    --green-1:#16a34a; --amber-1:#f59e0b; --red-1:#dc2626;
    --bg:#f6f8fb; --surface:#ffffff; --muted:#64748b; --text:#0f172a; --border:#e5e7eb;
    --shadow:0 10px 30px rgba(2,6,23,.08);
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{font-family:'Inter',system-ui,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
  main{margin-top:var(--header-h,60px);margin-left:var(--sidebar-w,280px);min-height:calc(100vh - var(--header-h,60px));padding:1.25rem clamp(1rem,2vw,2rem)}
  @media(max-width:860px){main{margin-left:0}}
  .container{max-width:1440px;margin:0 auto}
  .headerbar{display:flex;align-items:center;justify-content:space-between;margin:0 0 .8rem}
  .title{display:flex;gap:.6rem;align-items:center;font-weight:800;font-size:28px;color:#223b8f}
  .tabs{display:flex;gap:8px}
  .tab{border:1px solid var(--border);background:var(--surface);padding:.5rem .9rem;border-radius:999px;font-weight:800;color:#334155;text-decoration:none}
  .tab.active{background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:#fff;border-color:transparent}

  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:0 0 1rem}
  .stat{background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:16px 18px}
  .stat .n{font-size:1.6rem;font-weight:900;color:var(--brand-2);display:block}
  .stat .l{font-size:.9rem;color:var(--muted)}

  /* non-sticky toolbar (no overlap on scroll) */
  .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;justify-content:space-between;margin-bottom:.5rem}
  .controls{display:flex;flex-wrap:wrap;gap:8px;align-items:end}
  .control{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:8px 10px}
  .control label{display:block;font-size:.75rem;color:var(--muted);font-weight:700;margin-bottom:4px}
  .control input[type="text"], .control input[type="date"], .control select{border:1px solid var(--border);border-radius:10px;padding:10px;min-width:160px;outline:none}
  .control .inline{display:flex;gap:6px;align-items:center}
  .buttons{display:flex;gap:8px}
  .btn{display:inline-flex;align-items:center;gap:.5rem;padding:10px 14px;border:none;border-radius:12px;font-weight:900;cursor:pointer;text-decoration:none}
  .btn.primary{background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:#fff}
  .btn.warn{background:#fde68a;color:#5a4100}
  .btn.danger{background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff}
  .btn.ghost{background:var(--surface);border:1px solid var(--border);color:#0f172a}
  .btn:disabled{opacity:.5;cursor:not-allowed}

  .flash{margin:.6rem 0;padding:.75rem 1rem;border-radius:12px;border:1px solid}
  .flash.success{background:#ecfdf5;border-color:#34d399;color:#065f46}
  .flash.error{background:#fef2f2;border-color:#ef4444;color:#7f1d1d}
  .flash.info{background:#eff6ff;border-color:#60a5fa;color:#1e3a8a}

  .date-title{margin:1.25rem 0 .6rem;font-size:18px;font-weight:900;color:#233}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:1rem;margin-bottom:1.25rem;overflow-x:auto}
  .table{width:100%;border-collapse:collapse;font-size:.95rem}
  .table thead tr{background:var(--brand-1);color:#fff;text-align:left}
  .table th,.table td{padding:12px 14px;border-bottom:1px solid var(--border);white-space:nowrap}
  .table tbody tr:hover{background:#f8fafc}
  .code{background:#eef2ff;color:#1e3a8a;padding:4px 8px;border-radius:8px;font-weight:800;font-size:.85rem}
  .sortable{cursor:pointer}

  .meta{font-size:.8rem;color:#64748b}
  .selection{font-size:.9rem;color:#334155}

/* MOBILE — toolbar + table cards */
@media (max-width:720px){
  /* ---------- Toolbar / Controls ---------- */
  .toolbar{
    flex-direction:column;
    align-items:stretch;
    gap:12px;
  }

  /* Make the controls a clean, single column */
  .controls{
    display:grid;
    grid-template-columns:1fr;
    gap:12px;
    width:100%;
  }

  /* Remove the “box-in-a-box” look; keep things airy */
  .control{
    width:100%;
    padding:0;                /* no extra box around inputs */
    border:none;
    background:transparent;
    box-shadow:none;
  }
  .control label{
    display:block;
    font-size:.8rem;
    color:#64748b;
    font-weight:700;
    margin:0 0 .35rem;
  }
  .control input[type="text"],
  .control input[type="date"],
  .control select{
    width:100%;
    min-width:0;
    height:44px;
    border:1px solid var(--border);
    border-radius:12px;
    padding:10px 12px;
    outline:none;
    background:#fff;
  }

  /* Date range: label | input – to – input (no crowding) */
  .control .inline{
    display:grid;
    grid-template-columns:1fr auto 1fr;
    gap:10px;
    align-items:center;
  }
  .control .inline .meta{
    font-size:.85rem;
    color:#64748b;
    padding:0 2px;
  }

  /* Apply / Reset: full-width and comfy to tap */
  .controls .control:nth-last-child(2),
  .controls .control:last-child{
    display:flex;
  }
  .controls .control:nth-last-child(2) .btn,
  .controls .control:last-child .btn{
    width:100%;
    justify-content:center;
    padding:12px 14px;
    border-radius:14px;
  }

  /* Secondary buttons (Show Codes / Undo / Archive) stack nicely */
  .buttons{
    flex-direction:column;
    width:100%;
    gap:8px;
  }
  .buttons .btn{
    width:100%;
    justify-content:center;
  }

  /* Tighter headings */
  .date-title{font-size:1rem;margin:1rem 0 .5rem}
  .meta,.selection{font-size:.9rem}

  /* ---------- Table as mobile cards (refined) ---------- */
  .card{padding:.9rem}
  .table,
  .table thead,
  .table tbody,
  .table tr,
  .table th,
  .table td{display:block}
  .table thead{position:absolute;left:-9999px;top:-9999px}

  .table tr{
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    box-shadow:var(--shadow);
    margin:0 0 14px;
    overflow:hidden;
  }
  .table td{
    border:0;
    border-bottom:1px solid var(--border);
    padding:12px 14px;
    white-space:normal;
    display:flex;
    gap:10px;
    align-items:flex-start;
  }
  .table td:last-child{border-bottom:0}
  .table td::before{
    content:attr(data-label);
    font-weight:800;
    color:#475569;
    flex:0 0 120px;
    min-width:110px;
    max-width:150px;
  }
  .table td[data-label="Email"],
  .table td[data-label="User"]{overflow-wrap:anywhere; word-break:break-word}
  .table td .code{font-size:.95rem}
  .table td[data-label="Select"]{align-items:center}
  .table td[data-label="Select"]::before{flex:0 0 auto; margin-right:8px}
  input[type="checkbox"]{transform:scale(1.15)}
}


  </style>
  </head>
  <body>
  <main>
    <div class="container">

      <div class="headerbar">
        <div class="title"><i class="fa-solid fa-vault" style="color:var(--brand-2)"></i> Locker History</div>
        <nav class="tabs" aria-label="View">
          <?php
            $base = strtok($_SERVER['REQUEST_URI'], '?');
            $qsActive = $_GET; $qsActive['view']='active'; unset($qsActive['page']);
            $qsArch   = $_GET; $qsArch['view']='archived'; unset($qsArch['page']);
          ?>
          <a class="tab <?= $view==='active'?'active':'' ?>" href="<?= h($base.'?'.http_build_query($qsActive)) ?>">Active</a>
          <a class="tab <?= $view==='archived'?'active':'' ?>" href="<?= h($base.'?'.http_build_query($qsArch)) ?>">Archived</a>
        </nav>
      </div>

      <section class="stats">
        <div class="stat"><span class="n"><?= (int)$stats['total_used_this_month'] ?></span><span class="l">Used This Month</span></div>
        <div class="stat"><span class="n"><?= (int)$stats['today_used'] ?></span><span class="l">Used Today</span></div>
        <div class="stat"><span class="n"><?= (int)$stats['archived_count'] ?></span><span class="l">Archived</span></div>
        <div class="stat"><span class="n"><?= (int)$stats['last_month_used'] ?></span><span class="l">Used Last Month</span></div>
      </section>

      <?php if ($flash): ?>
        <div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
      <?php endif; ?>

      <!-- Toolbar: Filters & Actions -->
      <div class="toolbar">
        <form method="get" class="controls" id="filterForm">
          <input type="hidden" name="view" value="<?= h($view) ?>">
          <div class="control">
            <label for="filter">Field</label>
            <select name="filter" id="filter">
              <option value="all"   <?= $filter==='all'?'selected':'' ?>>All</option>
              <option value="locker"<?= $filter==='locker'?'selected':'' ?>>Locker</option>
              <option value="user"  <?= $filter==='user'?'selected':'' ?>>User</option>
              <option value="email" <?= $filter==='email'?'selected':'' ?>>Email</option>
              <option value="code"  <?= $filter==='code'?'selected':'' ?>>Code</option>
            </select>
          </div>
          <div class="control">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="Type and press Enter…">
          </div>
          <div class="control">
            <label>Used Date</label>
            <div class="inline">
              <input type="date" name="date_from" value="<?= h($date_from) ?>">
              <span class="meta">to</span>
              <input type="date" name="date_to" value="<?= h($date_to) ?>">
            </div>
          </div>
          <div class="control">
            <label for="locker">Locker #</label>
            <!-- Accepts 2 OR 'locker 2' (case-insensitive); shows friendly tooltip -->
            <input
              type="text"
              id="locker"
              name="locker"
              inputmode="numeric"
              pattern="(?:[Ll]ocker\s*)?\d+"
              title="Enter a number like 2 (you can also type 'locker 2')"
              value="<?= h($lockerRaw) ?>"
              placeholder="e.g. 4 or locker 4">
          </div>
          <div class="control">
            <label for="per_page">Per Page</label>
            <select name="per_page" id="per_page">
              <?php foreach([25,50,100,200] as $pp): ?>
                <option value="<?= $pp ?>" <?= $per_page===$pp?'selected':'' ?>><?= $pp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="control">
            <label>&nbsp;</label>
            <button class="btn ghost" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Apply</button>
          </div>
          <div class="control">
            <label>&nbsp;</label>
            <a class="btn ghost" href="<?= h($base.'?view='.$view) ?>"><i class="fa-solid fa-rotate-left"></i> Reset</a>
          </div>
        </form>

        <div class="buttons">
          <button class="btn warn" id="toggleCodes" type="button">
            <i class="fa-solid fa-eye"></i> Show Codes
          </button>
          <form method="post" id="undoForm" style="display:inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="undo_last">
            <button class="btn ghost" type="submit" <?= $undoAvailable? '' : 'disabled' ?>>
              <i class="fa-solid fa-rotate-left"></i> Undo Last Archive
            </button>
          </form>
          <form method="post" id="bulkForm" style="display:inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="<?= $view==='active' ? 'bulk_archive' : 'bulk_unarchive' ?>">
            <input type="hidden" name="ids[]" value="">
            <?php if ($view==='active'): ?>
              <button class="btn danger" id="bulkBtn" disabled><i class="fa-solid fa-box-archive"></i> Archive Selected</button>
            <?php else: ?>
              <button class="btn primary" id="bulkBtn" disabled><i class="fa-solid fa-box-open"></i> Unarchive Selected</button>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="meta" style="margin:.25rem 0 .75rem">
        Showing <strong><?= min($per_page, max(0, $totalRows - $offset)) ?></strong> of <strong><?= $totalRows ?></strong> records (page <?= $page ?> of <?= $totalPages ?>)
      </div>

      <!-- Groups -->
      <?php if ($records): ?>
        <div class="selection" id="selInfo" style="display:none"></div>
        <form id="rowsForm">
          <?php foreach($grouped as $label=>$rows): ?>
            <h2 class="date-title"><?= h($label) ?></h2>
            <div class="card">
              <div class="meta" style="margin:0 0 .5rem; display:flex; align-items:center; gap:.75rem">
                <label><input type="checkbox" class="select-all"> Select all in this group</label>
              </div>
              <table class="table">
                <thead>
                  <tr>
                    <th><input type="checkbox" class="select-all"></th>
                    <?php
                      $cols = [
                        'id'=>'ID','locker_number'=>'Locker','user_fullname'=>'User','user_email'=>'Email','code'=>'Code','duration_minutes'=>'Duration','expires_at'=>'Expires At','used_at'=>'Used At'
                      ];
                      $qs = $_GET;
                      unset($qs['page']);
                    ?>
                    <?php foreach ($cols as $key=>$labelCol):
                      $qs['sort']=$key; $qs['dir']= ($sort===$key && $dir==='ASC')?'desc':'asc';
                      $url = h($base.'?'.http_build_query($qs));
                    ?>
                      <th class="sortable"><a href="<?= $url ?>" style="color:#fff;text-decoration:none">
                        <?= h($labelCol) ?>
                        <?php if ($sort===$key): ?>
                          <i class="fa-solid fa-caret-<?= strtolower($dir)==='asc'?'up':'down' ?>"></i>
                        <?php endif; ?>
                      </a></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($rows as $r):
                    $id=(int)$r['id'];
                    $lockerN=(int)$r['locker_number'];
                    $user=h($r['user_fullname']??'');
                    $email=h($r['user_email']??'');
                    $code=h($r['code']??'');
                    $mins=(int)($r['duration_minutes']??0); $hH=floor($mins/60); $m=$mins%60; $dur=$hH>0? ("{$hH}h {$m}m") : ("{$m}m");
                    $exp=$r['expires_at']? date('M d, Y h:i A', strtotime($r['expires_at'])) : '-';
                    $used=date('M d, Y h:i A', strtotime($r['used_at']));
                  ?>
                  <tr>
                    <td data-label="Select"><input type="checkbox" class="row-check" value="<?= $id ?>"></td>
                    <td data-label="ID"><?= $id ?></td>
                    <td data-label="Locker">Locker <?= $lockerN ?></td>
                    <td data-label="User"><?= $user ?></td>
                    <td data-label="Email"><?= $email ?></td>
                    <td data-label="Code"><span class="code secret"><?= $code ?></span></td>
                    <td data-label="Duration"><?= h($dur) ?></td>
                    <td data-label="Expires At"><?= h($exp) ?></td>
                    <td data-label="Used At"><?= h($used) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
        </form>
      <?php else: ?>
        <p style="text-align:center;font-weight:800;color:#6b7280;padding:2rem 1rem">No records match your filters.</p>
      <?php endif; ?>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin:1rem 0 2rem;flex-wrap:wrap">
          <?php
            $qsp = $_GET; 
            $make = function($p) use($base,$qsp){ $qsp['page']=$p; return h($base.'?'.http_build_query($qsp)); };
            $start = max(1, $page-2); $end = min($totalPages, $page+2);
          ?>
          <a class="btn ghost" href="<?= $make(max(1,$page-1)) ?>" aria-label="Previous" <?= $page<=1? 'style="pointer-events:none;opacity:.5"':'' ?>>« Prev</a>
          <?php for($p=$start;$p<=$end;$p++): ?>
            <a class="btn <?= $p===$page? 'primary':'ghost' ?>" href="<?= $make($p) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <a class="btn ghost" href="<?= $make(min($totalPages,$page+1)) ?>" aria-label="Next" <?= $page>=$totalPages? 'style="pointer-events:none;opacity:.5"':'' ?>>Next »</a>
        </div>
      <?php endif; ?>

      <div class="meta">Now: <?= h(date('M d, Y h:i A')) ?></div>
    </div>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
  // ---------- Filter form: clean 'locker' on submit ----------
  // Accept 'locker 2', 'Locker2', etc. Keep only the FIRST number.
  document.getElementById('filterForm')?.addEventListener('submit', function(){
    const lockerEl = document.getElementById('locker');
    if (!lockerEl) return;
    const m = (lockerEl.value || '').match(/\d+/);
    lockerEl.value = m ? m[0] : ''; // so backend gets just the digits
  });

  // ---------- Selection handling ----------
  const rowChecks = Array.from(document.querySelectorAll('.row-check'));
  const groupSelectAll = Array.from(document.querySelectorAll('.select-all'));
  const bulkBtn = document.getElementById('bulkBtn');
  const bulkForm = document.getElementById('bulkForm');
  const selInfo = document.getElementById('selInfo');

  function updateSelectionUI(){
    const ids = rowChecks.filter(cb=>cb.checked).map(cb=>cb.value);
    if (bulkBtn) bulkBtn.disabled = ids.length===0;
    if (selInfo) {
      if (ids.length>0) { selInfo.style.display='block'; selInfo.textContent = ids.length + ' selected'; }
      else { selInfo.style.display='none'; selInfo.textContent=''; }
    }
  }
  rowChecks.forEach(cb=>cb.addEventListener('change', updateSelectionUI));

  groupSelectAll.forEach(sa=>{
    sa.addEventListener('change', function(){
      const card = this.closest('.card');
      if (!card) return;
      card.querySelectorAll('.select-all').forEach(x=>{ if (x!==this) x.checked=this.checked; });
      card.querySelectorAll('.row-check').forEach(cb=>cb.checked=this.checked);
      updateSelectionUI();
    });
  });

  // Submit bulk with confirmation
  if (bulkForm) {
    bulkForm.addEventListener('submit', (e)=>{
      const ids = rowChecks.filter(cb=>cb.checked).map(cb=>cb.value);
      if (ids.length===0) { e.preventDefault(); return; }
      e.preventDefault();
      const isArchive = (bulkForm.querySelector('input[name="action"]').value === 'bulk_archive');
      Swal.fire({
        title: isArchive? 'Archive selected records?' : 'Unarchive selected records?',
        text: isArchive? 'They will be hidden from Active. You can undo immediately.' : 'They will return to Active.',
        icon: 'warning', showCancelButton:true, confirmButtonColor: isArchive?'#dc2626':'#1e3a8a', cancelButtonColor:'#6b7280',
        confirmButtonText: isArchive? 'Yes, archive' : 'Yes, unarchive'
      }).then(res=>{
        if (res.isConfirmed) {
          bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el=>el.remove());
          ids.forEach(id=>{
            const i=document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=id; bulkForm.appendChild(i);
          });
          bulkForm.submit();
        }
      });
    });
  }

  // ---------- Toggle code visibility ----------
  const toggleBtn = document.getElementById('toggleCodes');
  const CODE_MASK = '••••••';
  const LS_KEY = 'codesHidden';

  function setCodesHidden(hide){
    document.querySelectorAll('.code.secret').forEach(el=>{
      if (hide) {
        if (!el.dataset.real) el.dataset.real = el.textContent;
        el.textContent = CODE_MASK;
      } else {
        el.textContent = el.dataset.real ?? '';
      }
    });
    if (toggleBtn) {
      toggleBtn.innerHTML = hide
        ? '<i class="fa-solid fa-eye"></i> Show Codes'
        : '<i class="fa-solid fa-eye-slash"></i> Hide Codes';
    }
    try { localStorage.setItem(LS_KEY, hide ? '1':'0'); } catch(_e){}
  }

  // initialize from storage (default hidden)
  let initialHidden = true;
  try {
    const v = localStorage.getItem(LS_KEY);
    if (v === '0') initialHidden = false;
  } catch(_e){}
  setCodesHidden(initialHidden);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      const hide = (localStorage.getItem(LS_KEY) ?? '1') === '1';
      setCodesHidden(!hide);
    });
  }

  // ---------- Toasts for last action ----------
  <?php if (!empty($flash) && $flash['type']==='success'): ?>
    Swal.fire({icon:'success', title:'Done', text:<?= json_encode($flash['msg']) ?>, confirmButtonColor:'#1e3a8a'});
  <?php endif; ?>
  </script>
  </body>
  </html>
