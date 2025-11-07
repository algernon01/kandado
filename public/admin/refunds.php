<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/db.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function peso($n){ return '₱' . number_format((float)$n, 2); }
function dt_out($dt){
    if (!$dt) return '—';
    return date('Y-m-d H:i', strtotime($dt));
}

function ensure_refunds_table(PDO $pdo): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS locker_refunds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            locker_number INT NOT NULL,
            locker_code VARCHAR(100) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            refunded_minutes INT NOT NULL DEFAULT 0,
            reference_no VARCHAR(60) NOT NULL,
            reason VARCHAR(60) NOT NULL DEFAULT 'early_termination',
            balance_after DECIMAL(12,2) DEFAULT NULL,
            meta LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(meta)),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $pdo->exec($sql);
}

ensure_refunds_table($pdo);

function format_minutes_label($minutes): string {
    $minutes = max(0, (int)$minutes);
    if ($minutes < 60) return $minutes . ' min';
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    if ($hours < 24) {
        $hourLabel = $hours . ' hr' . ($hours === 1 ? '' : 's');
        return $mins ? $hourLabel . ' ' . $mins . ' min' : $hourLabel;
    }
    $days = intdiv($hours, 24);
    $remHours = $hours % 24;
    $parts = [];
    $parts[] = $days . ' day' . ($days === 1 ? '' : 's');
    if ($remHours) $parts[] = $remHours . ' hr' . ($remHours === 1 ? '' : 's');
    if ($mins) $parts[] = $mins . ' min';
    return implode(' ', $parts);
}

function build_pager_nav(int $current, int $pages, array $qs, string $base = 'refunds.php'): string {
    $current = max(1, $current);
    $pages   = max(1, $pages);

    $cleanQs = [];
    foreach ($qs as $key => $value) {
        if ($value === null || $value === '') continue;
        $cleanQs[$key] = $value;
    }

    $makeUrl = function(int $page) use ($cleanQs, $base) {
        $cleanQs['page'] = $page;
        $query = http_build_query($cleanQs);
        return h($base . ($query ? ('?' . $query) : ''));
    };

    $html = '';
    if ($current > 1) {
        $html .= '<a class="m-link" href="'.$makeUrl($current-1).'" aria-label="Previous page">‹</a>';
    } else {
        $html .= '<span class="m-link disabled" aria-hidden="true">‹</span>';
    }

    $window = 1;
    $show = [1, $pages, $current];
    for ($i = $current - $window; $i <= $current + $window; $i++) {
        if ($i > 0 && $i <= $pages) $show[] = $i;
    }
    $show = array_values(array_unique($show));
    sort($show);

    $prev = 0;
    foreach ($show as $p) {
        if ($prev && $p > $prev + 1) {
            $html .= '<span class="m-link gap" aria-hidden="true">…</span>';
        }
        $html .= '<a class="m-link '.($p === $current ? 'active' : '').'" href="'.$makeUrl($p).'">'.$p.'</a>';
        $prev = $p;
    }

    if ($current < $pages) {
        $html .= '<a class="m-link" href="'.$makeUrl($current+1).'" aria-label="Next page">›</a>';
    } else {
        $html .= '<span class="m-link disabled" aria-hidden="true">›</span>';
    }

    return $html;
}

$q       = trim($_GET['q'] ?? '');
$locker  = isset($_GET['locker']) ? (int)$_GET['locker'] : 0;
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');
$per     = min(100, max(5, (int)($_GET['per'] ?? 15)));
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q OR lr.reference_no LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($locker > 0) {
    $where[] = "lr.locker_number = :locker";
    $params[':locker'] = $locker;
}
if ($from !== '') {
    $where[] = "lr.created_at >= :from";
    $params[':from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = "lr.created_at < :to";
    $params[':to'] = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
}

$cond = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM locker_refunds lr INNER JOIN users u ON u.id = lr.user_id $cond");
foreach ($params as $key => $value) {
    $type = ($key === ':locker') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $countStmt->bindValue($key, $value, $type);
}
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($totalRows / $per));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per;

$listSql = "
    SELECT lr.*, u.first_name, u.last_name, u.email
    FROM locker_refunds lr
    INNER JOIN users u ON u.id = lr.user_id
    $cond
    ORDER BY lr.created_at DESC
    LIMIT :per OFFSET :off
";
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $type = ($key === ':locker') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $listStmt->bindValue($key, $value, $type);
}
$listStmt->bindValue(':per', $per, PDO::PARAM_INT);
$listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$globalStats = $pdo->query("SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt FROM locker_refunds")->fetch(PDO::FETCH_ASSOC);
$last7 = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM locker_refunds WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$avgRefund = $globalStats['cnt'] > 0 ? ((float)$globalStats['total'] / (int)$globalStats['cnt']) : 0.0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Refunds · Admin</title>
  <link rel="icon" href="../../assets/icon/icon_tab.png" sizes="any">
  <link rel="stylesheet" href="../../assets/css/refunds.css">
</head>
<body>

<?php include '../../includes/admin_header.php'; ?>

<main id="content" class="refunds-page" role="main">
  <div class="page-head">
    <h1><i class="fa-solid fa-rotate-left"></i> Refund History</h1>
    <div class="muted">Track locker terminations that generated wallet credits.</div>
  </div>

  <section class="stats-grid" aria-label="Refund metrics">
    <article class="stat-card">
      <h3>Total refunded</h3>
      <div class="value"><?= peso($globalStats['total'] ?? 0) ?></div>
      <p><?= (int)($globalStats['cnt'] ?? 0) ?> events recorded</p>
    </article>
    <article class="stat-card">
      <h3>Last 7 days</h3>
      <div class="value"><?= peso($last7 ?? 0) ?></div>
      <p>Credits issued recently</p>
    </article>
    <article class="stat-card">
      <h3>Average refund</h3>
      <div class="value"><?= peso($avgRefund) ?></div>
      <p>Per termination</p>
    </article>
  </section>

  <form class="filters" method="get" action="refunds.php">
    <div class="group">
      <label for="filter-q">Search</label>
      <input id="filter-q" type="text" name="q" value="<?= h($q) ?>" placeholder="Name, email or reference">
    </div>
    <div class="group">
      <label for="filter-locker">Locker #</label>
      <input id="filter-locker" type="number" name="locker" min="0" value="<?= $locker ?: '' ?>" placeholder="All">
    </div>
    <div class="group">
      <label for="filter-from">From</label>
      <input id="filter-from" type="date" name="from" value="<?= h($from) ?>">
    </div>
    <div class="group">
      <label for="filter-to">To</label>
      <input id="filter-to" type="date" name="to" value="<?= h($to) ?>">
    </div>
    <div class="group">
      <label for="filter-per">Per page</label>
      <select id="filter-per" name="per">
        <?php foreach ([15,25,50,100] as $opt): ?>
          <option value="<?= $opt ?>" <?= $per===$opt?'selected':'' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn ghost" href="refunds.php">Reset</a>
  </form>

  <section class="table-card" aria-live="polite">
    <?php if (!$rows): ?>
      <div class="empty-state">
        <i class="fa-regular fa-face-smile-beam" style="font-size:2rem;"></i>
        <p>No refund records yet.</p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>User</th>
              <th>Locker</th>
              <th>Amount</th>
              <th>Minutes</th>
              <th>Reference</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php $meta = $row['meta'] ? json_decode($row['meta'], true) : []; ?>
              <tr>
                <td><?= h(dt_out($row['created_at'])) ?></td>
                <td>
                  <div style="font-weight:600;"><?= h($row['first_name'].' '.$row['last_name']) ?></div>
                  <div class="meta"><?= h($row['email']) ?></div>
                </td>
                <td><span class="pill">#<?= (int)$row['locker_number'] ?></span></td>
                <td style="font-weight:700;"><?= peso($row['amount']) ?></td>
                <td><?= h(format_minutes_label($row['refunded_minutes'])) ?></td>
                <td><code><?= h($row['reference_no']) ?></code></td>
                <td>
                  <div class="reason-note"><?= h(ucwords(str_replace('_',' ', $row['reason']))) ?></div>
                  <?php if (!empty($meta['remaining_minutes'])): ?>
                    <div class="meta">Unused: <?= h(format_minutes_label($meta['remaining_minutes'])) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($meta['segments']) && is_array($meta['segments'])): ?>
                    <div class="meta">
                      Segments:
                      <?php foreach ($meta['segments'] as $seg): ?>
                        <span><?= h(format_minutes_label($seg['unused_minutes'] ?? 0)) ?>/<?= peso($seg['refund_amount'] ?? 0) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($row['balance_after'])): ?>
                    <div class="meta">Balance after: <?= peso($row['balance_after']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
        $pagerNav = build_pager_nav($page, $pages, [
          'q'=>$q ?: null,
          'locker'=>$locker ?: null,
          'from'=>$from ?: null,
          'to'=>$to ?: null,
          'per'=>$per ?: null
        ]);
      ?>
      <div class="pager">
        <div class="pages">Page <?= $page ?> of <?= $pages ?> • <?= number_format($totalRows) ?> refunds</div>
        <div class="nav"><?= $pagerNav ?></div>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
