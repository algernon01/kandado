<?php
/* ========================================================================
   SECURITY ALERTS (Admin) — PH time fixed (Asia/Manila)
   - Displays times in Asia/Manila, 12-hour format
   - If your DB stores UTC, set $STORED_TZ = 'UTC'
   - Everything else unchanged
   ======================================================================== */

/* ---- Session (secure-ish defaults) ---- */
if (session_status() === PHP_SESSION_NONE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $secure,
  ]);
  session_start();
}

/* ---- Security headers ---- */
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

/* ---- DB (PDO) ---- */
require_once '../../config/db.php'; // must define $pdo (PDO)
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

/* ---- Timezone configuration ----
   If your DB stores created_at as PH local time (common), use 'Asia/Manila'.
   If your DB stores UTC (recommended), set to 'UTC'.
*/
$STORED_TZ  = 'Asia/Manila'; // <<< PH time in DB; change to 'UTC' if DB stores UTC
$DISPLAY_TZ = 'Asia/Manila'; // always display as PH time

/* ------------------ Helpers (escaping) ------------------ */
if (!function_exists('sa_esc')) {
  function sa_esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sa_cause_label')) {
  function sa_cause_label($c){
    switch ($c) {
      case 'all': return 'All';
      case 'theft': return 'Theft';
      case 'door_slam': return 'Door Slam';
      case 'bump': return 'Bump';
      case 'tilt_only': return 'Tilt Only';
      default: return 'Other';
    }
  }
}
if (!function_exists('sa_url_with')) {
  function sa_url_with($overrides = []) {
    $p = $_GET; foreach ($overrides as $k=>$v){ if($v===null) unset($p[$k]); else $p[$k]=$v; }
    return 'security_alerts.php' . ($p ? ('?' . http_build_query($p)) : '');
  }
}

/* ------------------ TIME helpers ------------------ */
if (!function_exists('sa_is_offset')) {
  function sa_is_offset($spec){ return (bool)preg_match('/^[+-]\d{2}:\d{2}$/',$spec); }
}
if (!function_exists('sa_epoch_from_local')) {
  // Interpret naive "Y-m-d H:i:s" as local time in $tzSpec → epoch seconds (UTC)
  function sa_epoch_from_local(string $naive, string $tzSpec): int {
    if ($naive === '' || $naive === '0000-00-00 00:00:00') return 0;
    if (sa_is_offset($tzSpec)) {                    // e.g. +08:00
      $ts = strtotime($naive.' '.$tzSpec);
      return $ts === false ? 0 : $ts;
    }
    try { $tz = new DateTimeZone($tzSpec); } catch (Throwable $e) { $tz = new DateTimeZone('UTC'); }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $naive, $tz);
    if (!$dt) $dt = new DateTime($naive, $tz);
    return $dt->getTimestamp();
  }
}
if (!function_exists('sa_fmt_local_from_epoch')) {
  // 12-hour with AM/PM
  function sa_fmt_local_from_epoch(int $epoch, string $tzSpec, string $fmt='M d, Y g:i A'): string {
    if ($epoch <= 0) return '';
    if (sa_is_offset($tzSpec)) {
      [$sign,$h,$m] = [$tzSpec[0], (int)substr($tzSpec,1,2), (int)substr($tzSpec,4,2)];
      $mins = $h*60 + $m; if ($sign==='-') $mins = -$mins;
      return gmdate($fmt, $epoch + $mins*60);
    }
    try { $tz = new DateTimeZone($tzSpec); } catch (Throwable $e) { $tz = new DateTimeZone('UTC'); }
    $dt = new DateTime('@'.$epoch); $dt->setTimezone($tz); return $dt->format($fmt);
  }
}
if (!function_exists('sa_time_ago')) {
  // Keep “0s ago” working
  function sa_time_ago(int $seconds): string {
    $d = max(0, $seconds);
    if ($d < 60)   return $d.'s ago';
    if ($d < 3600) return floor($d/60).'m ago';
    if ($d < 86400)return floor($d/3600).'h ago';
    return floor($d/86400).'d ago';
  }
}
if (!function_exists('sa_boundary_str_for_storage')) {
  // Compute "7 days ago" in STORAGE TZ (so SQL compare is correct)
  function sa_boundary_str_for_storage(int $epoch, string $storedTz): string {
    if (sa_is_offset($storedTz)) {
      [$sign,$h,$m] = [$storedTz[0], (int)substr($storedTz,1,2), (int)substr($storedTz,4,2)];
      $mins = $h*60 + $m; if ($sign==='-') $mins = -$mins;
      return gmdate('Y-m-d H:i:s', $epoch + $mins*60);
    }
    try { $tz = new DateTimeZone($storedTz); } catch (Throwable $e) { $tz = new DateTimeZone('UTC'); }
    $dt = new DateTime('@'.$epoch); $dt->setTimezone($tz); return $dt->format('Y-m-d H:i:s');
  }
}

/* -------------------------- CSRF --------------------------- */
if (!function_exists('sa_csrf_token')) {
  function sa_csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
  }
}
if (!function_exists('sa_csrf_field')) {
  function sa_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.sa_esc(sa_csrf_token()).'">';
  }
}
if (!function_exists('sa_require_csrf')) {
  function sa_require_csrf(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) { http_response_code(400); exit('Bad Request'); }
  }
}

/* ---------------------- POST actions (PRG) ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  sa_require_csrf();
  $action = $_POST['action'] ?? '';
  $ids = [];
  if (isset($_POST['id'])) { $ids = [(int)$_POST['id']]; }
  elseif (!empty($_POST['ids']) && is_array($_POST['ids'])) { foreach ($_POST['ids'] as $v) $ids[] = (int)$v; $ids = array_values(array_unique(array_filter($ids))); }

  // 7-day boundary in STORAGE TZ (based on epoch)
  $boundaryEpoch = time() - 7*24*3600;
  $boundaryStr   = sa_boundary_str_for_storage($boundaryEpoch, $STORED_TZ);

  $affected = 0;
  try {
    if ($action === 'mark_read' && $ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $pdo->prepare("UPDATE security_alerts SET is_read = 1 WHERE id IN ($in)");
      $stmt->execute($ids);
      $affected = $stmt->rowCount();
      $_SESSION['flash'] = "Marked {$affected} alert(s) as read."; $_SESSION['flash_type'] = 'ok';
    } elseif ($action === 'mark_unread' && $ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $pdo->prepare("UPDATE security_alerts SET is_read = 0 WHERE id IN ($in)");
      $stmt->execute($ids);
      $affected = $stmt->rowCount();
      $_SESSION['flash'] = "Marked {$affected} alert(s) as unread."; $_SESSION['flash_type'] = 'ok';
    } elseif ($action === 'mark_all_read') {
      $stmt = $pdo->prepare("UPDATE security_alerts SET is_read = 1 WHERE is_read = 0 AND created_at >= ?");
      $stmt->execute([$boundaryStr]);
      $affected = $stmt->rowCount();
      $_SESSION['flash'] = "Marked {$affected} recent alert(s) as read."; $_SESSION['flash_type'] = 'ok';
    } elseif ($action === 'delete' && $ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $pdo->prepare("DELETE FROM security_alerts WHERE id IN ($in) AND created_at >= ?");
      $params = array_merge($ids, [$boundaryStr]);
      $stmt->execute($params);
      $affected = $stmt->rowCount();
      $_SESSION['flash'] = "Deleted {$affected} alert(s)."; $_SESSION['flash_type'] = 'danger';
    } else {
      $_SESSION['flash'] = 'No action taken.'; $_SESSION['flash_type'] = 'ok';
    }
  } catch (Throwable $e) {
    error_log('[security_alerts] action error: '.$e->getMessage());
    $_SESSION['flash'] = 'Action failed. Please try again.'; $_SESSION['flash_type'] = 'danger';
  }

  $qs = $_GET ? ('?' . http_build_query($_GET)) : '';
  header("Location: security_alerts.php{$qs}"); exit;
}

/* -------------------------- GET side --------------------------- */
include '../../includes/admin_header.php';

$allowedCauses = ['all','theft','door_slam','bump','tilt_only','other'];
$cause = strtolower($_GET['cause'] ?? 'all'); if (!in_array($cause, $allowedCauses, true)) $cause = 'all';
$onlyUnread   = isset($_GET['only_unread']) && $_GET['only_unread'] == '1' ? 1 : 0;
$autoRefresh  = isset($_GET['autorefresh']) && $_GET['autorefresh'] == '1' ? 1 : 0;
$page    = max(1, (int)$_GET['page'] ?? 1);
$perPage = 20; $offset  = ($page - 1) * $perPage;

// 7-day boundary in STORAGE TZ for SQL
$boundaryEpoch = time() - 7*24*3600;
$boundaryStr   = sa_boundary_str_for_storage($boundaryEpoch, $STORED_TZ);

/* WHERE (+filters) */
$where  = "created_at >= ?"; $params = [$boundaryStr];
if ($cause !== 'all') { $where .= " AND cause = ?"; $params[] = $cause; }
if ($onlyUnread)      { $where .= " AND is_read = 0"; }

/* Count & fetch */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM security_alerts WHERE $where");
$stmt->execute($params); $total = (int)$stmt->fetchColumn();

$sql = "SELECT id, cause, is_read, created_at FROM security_alerts WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = max(1, (int)ceil($total / $perPage));

/* Per-cause counts (7d; honor unread filter) */
$countWhere = "created_at >= ?" . ($onlyUnread ? " AND is_read = 0" : "");
$stmt = $pdo->prepare("SELECT cause, COUNT(*) AS cnt FROM security_alerts WHERE $countWhere GROUP BY cause");
$stmt->execute([$boundaryStr]);
$causeCounts = array_fill_keys($allowedCauses, 0); $allTotal = 0;
foreach ($stmt as $row) { $cc = $row['cause'] ?? 'other'; $n = (int)$row['cnt']; $allTotal += $n; if (isset($causeCounts[$cc])) $causeCounts[$cc] = $n; }
$causeCounts['all'] = $allTotal;

/* Flash (for SweetAlert toast) */
$flash = $_SESSION['flash'] ?? null; $flashType = $_SESSION['flash_type'] ?? 'ok'; unset($_SESSION['flash'], $_SESSION['flash_type']);

/* Accent colors */
$badgeColors = [ 'theft' => '#ef4444', 'door_slam' => '#6366f1', 'bump' => '#3b82f6', 'tilt_only' => '#10b981', 'other' => '#64748b' ];
?>
<style>
:root{ --w-normal: 400; --w-medium: 500; }
#alerts{ padding:24px 20px 40px; }
#alerts .wrap{ max-width:1160px; margin:0 auto; }
@media (min-width:1600px){ #alerts .wrap{ max-width:1240px; } }
@media (max-width:1080px){ #alerts{ padding:16px; } }
.card{ background:#fff; border-radius:14px; box-shadow:0 8px 24px rgba(22,28,45,.06); }
.page-head{ display:flex; align-items:center; gap:12px; margin:0 0 14px; }
.page-head .title{ font-size:28px; font-weight:var(--w-medium); color:#2b3a67; display:flex; align-items:center; gap:12px; }
.page-head .icon{ width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; background:#eef2ff; color:#2f54eb; border-radius:10px; }
.page-head .icon svg{ width:22px; height:22px; }
label.small{ font-size:12px; font-weight:var(--w-medium); color:#5b6b8d; }
select, .checkbox{ height:42px; border:1px solid #e6e9f4; border-radius:12px; background:#fff; padding:0 12px; font-size:14px; font-weight:var(--w-normal); }
.toolbar{ padding:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.btn{ --h:42px; height:var(--h); border:0; border-radius:999px; padding:0 16px; font-weight:var(--w-medium); font-size:14px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:10px; }
a.btn{ text-decoration:none; }
.btn.apply{ background:#3056ff; color:#fff; }
.btn.reset{ background:#eef2ff; color:#2a3db6; }
/* NEW: Stop alerting button */
.btn.stop{ background:#ffe1e1; color:#b02a2a; }
.pill{ --h:42px; height:var(--h); display:inline-flex; align-items:center; gap:10px; padding:0 16px; border-radius:999px; font-weight:var(--w-medium); font-size:14px; border:1px solid transparent; text-decoration:none; }
.pill svg{ width:18px; height:18px; }
.pill.blue{ color:#1e40af; background:#eaf1ff; border-color:#d9e6ff; }
.pill.amber{ color:#92400e; background:#fff1d6; border-color:#ffe4b5; }
.pill.red{ color:#9f1d1d; background:#ffe1e1; border-color:#ffc9c9; }
.pill-group{ display:flex; gap:12px; align-items:center; justify-content:center; flex-wrap:wrap; }
.muted{ color:#8592b0; font-size:12px; font-weight:var(--w-normal); }
.quick-chips{ display:flex; gap:10px; flex-wrap:wrap; padding:12px 16px 16px; }
.chip{ display:inline-flex; align-items:center; gap:8px; border:1px solid #e6e9f4; background:#fff; border-radius:999px; padding:8px 12px; text-decoration:none; color:#2d3a69; font-weight:var(--w-medium); font-size:12px; }
.chip.active{ background:#2f54eb; border-color:#2f54eb; color:#fff; }
.headerline{ display:flex; gap:10px; align-items:center; padding:12px 16px; }
.table-wrap{ overflow:auto; }
.table{ width:100%; border-collapse:separate; border-spacing:0 10px; }
.table thead th{ position:sticky; top:0; background:#f9fbff; z-index:1; padding:12px; text-transform:uppercase; font-size:12px; letter-spacing:.03em; color:#6f7da6; text-align:center; font-weight:var(--w-medium); border-top-left-radius:12px; border-top-right-radius:12px; }
.table tbody tr{ background:#fff; box-shadow:0 3px 12px rgba(0,0,0,.05); border-radius:12px; }
.table tbody td{ padding:14px 12px; vertical-align:middle; text-align:center; font-weight:var(--w-normal); }
.col-time small{ display:block; color:#6f7da6; }
.badge{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-weight:var(--w-medium); font-size:12px; background:#eef2ff; color:#1f2937; }
.badge .dot{ width:10px; height:10px; border-radius:999px; display:inline-block; }
.row-unread{ border-left:4px solid #3056ff; }
.pagination{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px; }
.pager{ display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.pager a, .pager span{ padding:6px 10px; border-radius:8px; border:1px solid #e2e6f2; background:#fff; color:#2d3a69; text-decoration:none; font-weight:var(--w-medium); font-size:13px; }
.pager a.active{ background:#2f54eb; color:#fff; border-color:#2f54eb; }
.pager .sep{ pointer-events:none; border:none; background:transparent; padding:0 2px; }

.swal2-container{ z-index:2147483647 !important; }
.swal2-title{ font-weight:var(--w-medium) !important; }
.swal2-html-container{ font-weight:var(--w-normal) !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<main id="content">
  <section id="alerts">
    <div class="wrap">
      <div class="page-head">
        <div class="title" style = "font-weight: 800;">
          <span class="icon">
            <i class="fas fa-shield-halved"></i>
          </span>
          Security Alerts
        </div>
      </div>

      <div class="card">
        <form class="toolbar" method="get" action="security_alerts.php">
          <div>
            <label for="cause" class="small">Cause</label><br>
            <select id="cause" name="cause">
              <?php foreach ($allowedCauses as $c): ?>
                <option value="<?= sa_esc($c) ?>" <?= $cause===$c?'selected':'' ?>><?= sa_esc(sa_cause_label($c)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:flex; align-items:center; gap:10px; padding-top:10px">
            <input class="checkbox" type="checkbox" id="only_unread" name="only_unread" value="1" <?= $onlyUnread?'checked':'' ?>>
            <label for="only_unread" style="margin:0">Show unread only</label>
          </div>

          <button class="btn apply" type="submit">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.2l-3.5-3.5-1.4 1.4L9 19 20.3 7.7l-1.4-1.4z"/></svg>
            Apply
          </button>

          <a class="btn reset" href="security_alerts.php">Reset</a>

          <!-- NEW: Stop alerting button (right of Reset) -->
          <button class="btn stop" type="button" id="btnStopAlerting" title="Silence active alarms">Stop alerting</button>

          <div class="top-right" style="margin-left:auto">
            <label style="display:flex; align-items:center; gap:10px; font-weight:var(--w-medium);">
              <input type="checkbox" id="autorefresh" <?= $autoRefresh ? 'checked' : '' ?>
                     onclick="location.href='<?= sa_esc(sa_url_with(['autorefresh'=>$autoRefresh?null:1])) ?>'">
              Auto-refresh
            </label>
            <span class="muted">Showing last 7 days • Asia/Manila (UTC+8)</span>
          </div>
        </form>

        <div class="quick-chips">
          <?php foreach ($allowedCauses as $c): ?>
            <?php $active = ($cause === $c) ? 'active' : ''; ?>
            <a class="chip <?= $active ?>" href="<?= sa_esc(sa_url_with(['cause'=>$c, 'page'=>1])) ?>">
              <?= sa_esc(sa_cause_label($c)) ?>
              <span class="muted" style="font-weight:var(--w-medium);"> <?= (int)($causeCounts[$c] ?? 0) ?> </span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card" style="margin-top:14px;">
        <div class="headerline">
          <div class="muted">
            <?php $start = $total ? ($offset + 1) : 0; $end = min($offset + $perPage, $total); echo "Showing {$start}–{$end} of {$total}"; ?>
          </div>

          <div class="top-right" style="margin-left:auto">
            <form id="formAllRead" method="post">
              <?= sa_csrf_field(); ?>
              <input type="hidden" name="action" value="mark_all_read">
              <button type="button" id="btnMarkAllRead" class="btn" style="background:#ffe6e6;color:#b02a2a">Mark all as read</button>
            </form>
          </div>
        </div>

        <!-- Bulk action bar (separate form) -->
        <form id="bulkForm" method="post" class="headerline" style="gap:12px;">
          <?= sa_csrf_field(); ?>
          <input type="hidden" name="action" id="bulkAction" value="">
          <button type="button" class="pill blue"  onclick="submitBulk('mark_read')">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5-1.4 1.4L9 19 20.3 7.7l-1.4-1.4z"/></svg>
            Mark read
          </button>
          <button type="button" class="pill amber" onclick="submitBulk('mark_unread')">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 6V3L8 7l4 4V8c2.76 0 5 2.24 5 5a5 5 0 01-9.9 1H5.02A7.002 7.002 0 0012 22a7 7 0 000-14z"/></svg>
            Mark unread
          </button>
          <button type="button" class="pill red" onclick="submitBulk('delete')" title="Delete selected">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg>
            Delete
          </button>
          <span class="muted" id="bulkCount">0 selected</span>
        </form>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th style="width:48px;"><input type="checkbox" id="checkAll" title="Select all on page"></th>
                <th>Time</th>
                <th>Cause</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="4" style="padding:56px 12px; text-align:center; color:#6f7da6">No alerts for the current filters in the last 7 days.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <?php
                $isUnread = ((int)$r['is_read']) === 0;
                $c = $r['cause'] ?? 'other';
                $dot = $badgeColors[$c] ?? '#64748B';
                $epoch = sa_epoch_from_local($r['created_at'], $STORED_TZ);   // parse using the DB’s TZ
                $ago   = sa_time_ago(time() - $epoch);
                $local = sa_fmt_local_from_epoch($epoch, $DISPLAY_TZ);        
              ?>
              <tr class="<?= $isUnread ? 'row-unread' : '' ?>">
                <td><input type="checkbox" class="rowcheck" name="ids[]" value="<?= (int)$r['id'] ?>" form="bulkForm"></td>
                <td class="col-time">
                  <strong style="font-weight:var(--w-medium);"><?= sa_esc($ago) ?></strong>
                  <small><?= sa_esc($local) ?></small>
                </td>
                <td>
                  <span class="badge">
                    <span class="dot" style="background:<?= sa_esc($dot) ?>;"></span>
                    <?= sa_esc(sa_cause_label($c)) ?>
                    <?php if ($isUnread): ?><span class="muted" style="margin-left:4px;">• Unread</span><?php endif; ?>
                  </span>
                </td>
                <td>
                  <div class="pill-group">
                    <?php if ($isUnread): ?>
                      <form method="post" style="display:inline">
                        <?= sa_csrf_field(); ?>
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="pill blue">
                          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5-1.4 1.4L9 19 20.3 7.7l-1.4-1.4z"/></svg>
                          Mark read
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="post" style="display:inline">
                        <?= sa_csrf_field(); ?>
                        <input type="hidden" name="action" value="mark_unread">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="pill amber">
                          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 6V3L8 7l4 4V8c2.76 0 5 2.24 5 5a5 5 0 01-9.9 1H5.02A7.002 7.002 0 0012 22a7 7 0 000-14z"/></svg>
                          Mark unread
                        </button>
                      </form>
                    <?php endif; ?>

                    <form method="post" style="display:inline" data-confirm="Delete this alert permanently?">
                      <?= sa_csrf_field(); ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button type="submit" class="pill red">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg>
                        Delete
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="pagination">
          <div class="muted">
            <?php $start = $total ? ($offset + 1) : 0; $end = min($offset + $perPage, $total); echo "Showing {$start}–{$end} of {$total}"; ?>
          </div>
          <div class="pager">
            <?php
              $window=2; $from=max(1,$page-$window); $to=min($totalPages,$page+$window);
              if ($page>1){ echo '<a href="'.sa_esc(sa_url_with(['page'=>1])).'">First</a>'; echo '<a href="'.sa_esc(sa_url_with(['page'=>$page-1])).'">Prev</a>'; }
              if ($from>1){ echo '<a href="'.sa_esc(sa_url_with(['page'=>1])).'">1</a><span class="sep">…</span>'; }
              for ($p=$from; $p<=$to; $p++){ $cls=$p===$page?'class="active"':''; echo '<a '.$cls.' href="'.sa_esc(sa_url_with(['page'=>$p])).'">'.(int)$p.'</a>'; }
              if ($to<$totalPages){ echo '<span class="sep">…</span><a href="'.sa_esc(sa_url_with(['page'=>$totalPages])).'">'.(int)$totalPages.'</a>'; }
              if ($page<$totalPages){ echo '<a href="'.sa_esc(sa_url_with(['page'=>$page+1])).'">Next</a>'; echo '<a href="'.sa_esc(sa_url_with(['page'=>$totalPages])).'">Last</a>'; }
            ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  const $ = (s,root=document)=>root.querySelector(s);
  const $$ = (s,root=document)=>Array.from(root.querySelectorAll(s));

  const checkAll=$('#checkAll'), rowChecks=$$('.rowcheck');
  const bulkForm=$('#bulkForm'), bulkAction=$('#bulkAction'), bulkCount=$('#bulkCount');
  const formAllRead=$('#formAllRead'), btnAllRead=$('#btnMarkAllRead');

  function refreshBulk(){
    const n=rowChecks.filter(c=>c.checked).length;
    if(checkAll){ checkAll.checked=n===rowChecks.length&&n>0; checkAll.indeterminate=n>0&&n<rowChecks.length; }
    if(bulkCount) bulkCount.textContent=n+' selected';
  }
  if (checkAll) checkAll.addEventListener('change', ()=>{ rowChecks.forEach(c=>c.checked=checkAll.checked); refreshBulk(); });
  rowChecks.forEach(c=>c.addEventListener('change', refreshBulk)); refreshBulk();

  window.submitBulk=function(action){
    const picked=rowChecks.some(c=>c.checked);
    if(!picked){ Swal.fire({icon:'info', title:'Select at least one alert', timer:1600, showConfirmButton:false}); return; }
    if(action==='delete'){
      Swal.fire({icon:'warning', title:'Delete selected alert(s)?', text:'This action cannot be undone.', showCancelButton:true, confirmButtonText:'Delete', cancelButtonText:'Cancel'})
        .then(r=>{ if(r.isConfirmed){ bulkAction.value=action; bulkForm.submit(); } });
      return;
    }
    bulkAction.value=action; bulkForm.submit();
  };

  if (btnAllRead) {
    btnAllRead.addEventListener('click', ()=>{
      Swal.fire({icon:'question', title:'Mark all alerts (7 days) as read?', showCancelButton:true, confirmButtonText:'Yes, mark all', cancelButtonText:'Cancel'})
        .then(r=>{ if(r.isConfirmed) formAllRead.submit(); });
    });
  }

  $$('form[data-confirm]').forEach(f=>{
    f.addEventListener('submit', (e)=>{
      e.preventDefault();
      const msg=f.getAttribute('data-confirm')||'Are you sure?';
      Swal.fire({icon:'warning', title:msg, showCancelButton:true, confirmButtonText:'Yes', cancelButtonText:'Cancel'})
        .then(r=>{ if(r.isConfirmed) f.submit(); });
    });
  });

  const urlParams=new URLSearchParams(location.search);
  if (urlParams.get('autorefresh')==='1') setInterval(()=>location.reload(), 30000);

  // NEW: Stop alerting handler
  const btnStop = $('#btnStopAlerting');
  if (btnStop) {
    btnStop.addEventListener('click', ()=>{
      Swal.fire({
        icon:'warning',
        title:'Stop alerting now?',
        text:'This will attempt to silence active alarms.',
        showCancelButton:true,
        confirmButtonText:'Stop',
        cancelButtonText:'Cancel'
      }).then(r=>{
        if(!r.isConfirmed) return;
        fetch('/kandado/api/locker_api.php?stop_alert=1&secret=MYSECRET123', { method:'GET' })
          .then(res=>{
            if(res.ok){
              Swal.fire({toast:true, position:'top-end', icon:'success', title:'Alerts stopped', showConfirmButton:false, timer:1600});
            } else {
              Swal.fire({icon:'error', title:'Failed to stop alerts', text:'Server returned '+res.status});
            }
          })
          .catch(()=>{
            Swal.fire({icon:'error', title:'Network error', text:'Please try again.'});
          });
      });
    });
  }
})();
</script>

<?php if ($flash): ?>
<script>
  Swal.fire({
    toast:true, position:'top-end',
    icon:'<?= $flashType === 'ok' ? 'success' : 'error' ?>',
    title: <?= json_encode($flash) ?>,
    showConfirmButton:false, timer:2500, timerProgressBar:true
  });
</script>
<?php endif; ?>
