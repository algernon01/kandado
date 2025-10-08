<?php
// admin/violations.php
session_start();
date_default_timezone_set('Asia/Manila'); // PHP runs in PH time

$isAction = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

// Auth check — return JSON on AJAX, redirect on normal requests
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if ($isAction) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }
    header('Location: ../login.php');
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * We need $pdo but must not output layout/HTML for AJAX.
 * Use output buffering to include the bootstrap quietly.
 */
if ($isAction) {
    ob_start();
    include '../../includes/admin_header.php'; // provides $pdo
    ob_end_clean();
} else {
    include '../../includes/admin_header.php'; // regular layout render
}

// Ensure the MySQL session operates in Asia/Manila as well
if (isset($pdo)) {
    try {
        // Using offset works even if MySQL time zone tables aren't loaded
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // non-fatal
    }
}

/* ---------- AJAX ACTIONS (pure JSON) ---------- */
if ($isAction) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
            throw new Exception('Invalid CSRF token.');
        }
        if (!isset($pdo)) {
            throw new Exception('DB not initialized.');
        }

        $action  = $_POST['action'];
        $user_id = (int)($_POST['user_id'] ?? 0);
        $note    = trim($_POST['note'] ?? '');
        if ($user_id <= 0) {
            throw new Exception('Missing user_id');
        }

        $pdo->beginTransaction();

        // Ensure helper statements
        $insEvt = $pdo->prepare("
            INSERT INTO violation_events (user_id, locker_number, event, details)
            VALUES (?, 0, ?, ?)
        ");
        $pdo->prepare("
            INSERT INTO user_bans (user_id) VALUES (?)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ")->execute([$user_id]);

        switch ($action) {
            case 'unban':
                $pdo->prepare("
                    UPDATE user_bans SET banned_until = NULL, is_permanent = 0
                    WHERE user_id = ?
                ")->execute([$user_id]);
                $insEvt->execute([$user_id, 'unban', 'Manual unban by admin.']);
                break;

            case 'ban_1d':
                $pdo->prepare("
                    UPDATE user_bans SET is_permanent = 0,
                    banned_until = DATE_ADD(NOW(), INTERVAL 1 DAY)
                    WHERE user_id = ?
                ")->execute([$user_id]);
                $insEvt->execute([$user_id, 'ban_1d', 'Manual 1-day ban by admin.']);
                break;

            case 'ban_3d':
                $pdo->prepare("
                    UPDATE user_bans SET is_permanent = 0,
                    banned_until = DATE_ADD(NOW(), INTERVAL 3 DAY)
                    WHERE user_id = ?
                ")->execute([$user_id]);
                $insEvt->execute([$user_id, 'ban_3d', 'Manual 3-day ban by admin.']);
                break;

            case 'ban_perm':
                $pdo->prepare("
                    UPDATE user_bans SET is_permanent = 1, banned_until = NULL
                    WHERE user_id = ?
                ")->execute([$user_id]);
                $insEvt->execute([$user_id, 'ban_perm', 'Manual permanent ban by admin.']);
                break;

            case 'manual_note':
                if ($note === '') {
                    throw new Exception('Note is required.');
                }
                $insEvt->execute([$user_id, 'manual', $note]);
                break;

            default:
                throw new Exception('Unknown action.');
        }

        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit; // IMPORTANT: stop here so no HTML is sent
}

/* ---------- Helpers (PH time aware) ---------- */

function soft_pill($text, $bg, $ring, $fg = '#1f2937') {
    $label = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $style = '--pill-bg:' . $bg . ';--pill-ring:' . $ring . ';--pill-color:' . $fg;
    return '<span class="pill-soft" style="' . $style . '">' . $label . '</span>';
}
function badgeEvent($event) {
    $map = [
        'hold'     => ['Hold', '#dbe7ff', '#c8d9ff', '#1f3c88'],
        'ban_1d'   => ['Ban 1 Day', '#fde7c8', '#fcd9aa', '#8f4700'],
        'ban_3d'   => ['Ban 3 Days', '#f5d4b8', '#f0c39a', '#7b3410'],
        'ban_perm' => ['Permanent Ban', '#f9cbcb', '#f7b4b4', '#9f1239'],
        'unban'    => ['Unban', '#cfe9d8', '#bee2cb', '#1f7a43'],
        'manual'   => ['Note', '#e1e4ec', '#d5d9e3', '#475569'],
    ];
    $entry = $map[$event] ?? [ucwords(str_replace('_', ' ', $event)), '#e1e4ec', '#d5d9e3', '#1f2937'];
    return soft_pill($entry[0], $entry[1], $entry[2], $entry[3]);
}

function ph_tz(): DateTimeZone {
    static $tz;
    return $tz ??= new DateTimeZone('Asia/Manila');
}


function to_ph_dt(?string $ts): ?DateTimeImmutable {
    if (!$ts) return null;
    try {
        $hasTz = (bool)preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $ts);
        $dt = $hasTz ? new DateTimeImmutable($ts) : new DateTimeImmutable($ts, ph_tz());
        return $dt->setTimezone(ph_tz());
    } catch (Throwable $e) {
        return null;
    }
}

function fmt_ph(?DateTimeImmutable $dt, string $format = 'M j, Y g:i A'): string {
    return $dt ? $dt->format($format) : '';
}

/** Remaining time until a timestamp, shown in PH */
function timeLeftTextPH(?string $until): string {
    $target = to_ph_dt($until);
    if (!$target) return '';
    $now = new DateTimeImmutable('now', ph_tz());
    if ($target <= $now) return 'expired';
    $diff = $now->diff($target);
    $parts = [];
    if ($diff->d) $parts[] = $diff->d . 'd';
    if ($diff->h) $parts[] = $diff->h . 'h';
    if ($diff->i) $parts[] = $diff->i . 'm';
    if (!$parts)  $parts[] = $diff->s . 's';
    return implode(' ', $parts);
}

function userDisplay($row) {
    foreach (['full_name', 'name', 'username', 'first_name', 'email'] as $key) {
        if (!empty($row[$key])) {
            return htmlspecialchars($row[$key], ENT_QUOTES, 'UTF-8');
        }
    }
    if (!empty($row['first_name']) || !empty($row['last_name'])) {
        return htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    }
    return 'User #' . (int)($row['user_id'] ?? $row['id'] ?? 0);
}

if (!isset($pdo)) {
    die('<div style="padding:2rem;color:#b91c1c">Database handle ($pdo) not available.</div>');
}

/* ---------- Queries ---------- */

$permCount   = (int)$pdo->query("SELECT COUNT(*) FROM user_bans WHERE is_permanent = 1")->fetchColumn();
$tempCount   = (int)$pdo->query("SELECT COUNT(*) FROM user_bans WHERE banned_until IS NOT NULL AND banned_until > NOW()")->fetchColumn();
$activeCount = $permCount + $tempCount;

$activeStmt = $pdo->query("
  SELECT ub.*, u.*
  FROM user_bans ub
  JOIN users u ON u.id = ub.user_id
  WHERE ub.is_permanent = 1 OR (ub.banned_until IS NOT NULL AND ub.banned_until > NOW())
  ORDER BY ub.is_permanent DESC, ub.banned_until DESC
");

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 5;
$offset = ($page - 1) * $per;

$totalEvents = (int)$pdo->query("SELECT COUNT(*) FROM violation_events")->fetchColumn();
$totalPages  = max(1, (int)ceil($totalEvents / $per));

$histStmt = $pdo->prepare("
  SELECT ve.*, u.*, ve.created_at AS event_created_at
  FROM violation_events ve
  JOIN users u ON u.id = ve.user_id
  ORDER BY ve.created_at DESC
  LIMIT :lim OFFSET :off
");
$histStmt->bindValue(':lim', $per, PDO::PARAM_INT);
$histStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$histStmt->execute();

function pageUrl($p) {
    $qs = $_GET;
    $qs['page'] = $p;
    return '?' . http_build_query($qs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Violations</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="../../assets/css/violations.css?v=3" />
</head>
<body class="violations-page" data-csrf="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" data-endpoint="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
<div class="page-wrap">
  <main id="content" class="violations">

    <header class="violations__header">
      <div class="violations__intro">
        <p class="violations__eyebrow">Moderation</p>
        <h1 class="violations__title">Violations</h1>
        <p class="violations__subtitle">Monitor ban activity and review context in one place.</p>
      </div>
    </header>

    <section class="stats" aria-label="Ban overview">
      <article class="stat-card">
        <span class="stat-card__label">Active bans</span>
        <span class="stat-card__value"><?= number_format($activeCount) ?></span>
        <span class="stat-card__meta">Users currently restricted</span>
      </article>
      <article class="stat-card">
        <span class="stat-card__label">Temporary bans</span>
        <span class="stat-card__value"><?= number_format($tempCount) ?></span>
        <span class="stat-card__meta">Expiring automatically</span>
      </article>
      <article class="stat-card">
        <span class="stat-card__label">Permanent bans</span>
        <span class="stat-card__value"><?= number_format($permCount) ?></span>
        <span class="stat-card__meta">Require manual review</span>
      </article>
    </section>

    <section class="module module--bans">
      <header class="module__header">
        <div>
          <h2 class="module__title">Active Bans</h2>
          <p class="module__hint">Lift restrictions, escalate, or leave a note for teammates.</p>
        </div>
      </header>
      <div class="module__body">
        <div class="table-wrap">
          <table class="data-table data-table--bans">
            <thead>
              <tr>
                <th scope="col">User</th>
                <th scope="col">Offenses</th>
                <th scope="col">Holds</th>
                <th scope="col">Status</th>
                <th scope="col">Ends</th>
                <th scope="col" class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $hasActive = false;
            while ($row = $activeStmt->fetch(PDO::FETCH_ASSOC)):
              $hasActive = true;
              $isPerm = (int)$row['is_permanent'] === 1;

              $until = $row['banned_until'] ?? null;
              $untilDt      = to_ph_dt($until);
              $untilDisplay = fmt_ph($untilDt);
              $untilIso     = $untilDt ? $untilDt->format(DateTime::ATOM) : '';

              $remainingText = (!$isPerm && $untilDt) ? timeLeftTextPH($until) : '';
            ?>
              <tr>
                <td>
                  <span class="cell-primary"><?= userDisplay($row) ?></span>
                  <span class="cell-meta">ID: <?= (int)$row['user_id'] ?></span>
                </td>
                <td class="cell-number"><?= (int)$row['offense_count'] ?></td>
                <td class="cell-number"><?= (int)$row['holds_since_last_offense'] ?></td>
                <td>
                  <?= $isPerm
                        ? soft_pill('Permanent', '#f8d0d0', '#f2b8b8', '#7f1d1d')
                        : soft_pill('Temporary', '#fbe5c5', '#f5d4a5', '#7b3410'); ?>
                </td>
                <td>
                  <?php if ($isPerm): ?>
                    <span class="cell-meta">Manual review</span>
                  <?php elseif ($untilDisplay): ?>
                    <span class="mono"><?= htmlspecialchars($untilDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="cell-meta">
                      <span class="js-remaining" data-until="<?= htmlspecialchars($untilIso, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($remainingText ?: 'expired', ENT_QUOTES, 'UTF-8') ?>
                      </span>
                      remaining
                    </span>
                  <?php else: ?>
                    <span class="cell-meta">Date unavailable</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="action-group">
                    <button type="button" class="btn btn--ghost js-unban" data-id="<?= (int)$row['user_id'] ?>">Unban</button>
                    <button type="button" class="btn btn--ghost js-ban1" data-id="<?= (int)$row['user_id'] ?>">Ban 1d</button>
                    <button type="button" class="btn btn--ghost js-ban3" data-id="<?= (int)$row['user_id'] ?>">Ban 3d</button>
                    <button type="button" class="btn btn--danger js-banperm" data-id="<?= (int)$row['user_id'] ?>">Ban Permanent</button>
                    <button type="button" class="btn btn--ghost js-note" data-id="<?= (int)$row['user_id'] ?>">Add Note</button>
                    <button type="button" class="btn btn--primary js-view" data-id="<?= (int)$row['user_id'] ?>">Timeline</button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php if (!$hasActive): ?>
              <tr>
                <td colspan="6" class="table-empty">No active bans yet.</td>
              </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="module module--history">
      <header class="module__header">
        <div>
          <h2 class="module__title">Violation History</h2>
          <p class="module__hint">Latest activity logged across lockers and accounts.</p>
        </div>
        <div class="module__meta">Total events: <?= number_format($totalEvents) ?></div>
      </header>
      <div class="module__body">
        <div class="table-wrap">
          <table class="data-table" data-history-table>
            <thead>
              <tr>
                <th scope="col">Date / Time</th>
                <th scope="col">User</th>
                <th scope="col">Locker</th>
                <th scope="col">Event</th>
                <th scope="col">Details</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $hasEvents = false;
            while ($e = $histStmt->fetch(PDO::FETCH_ASSOC)):
                $hasEvents = true;
                $eventCreatedRaw = $e['event_created_at'] ?? $e['created_at'] ?? '';
                $eventCreatedDt  = to_ph_dt($eventCreatedRaw);
                $createdDisplay  = fmt_ph($eventCreatedDt);
                $createdIso      = $eventCreatedDt ? $eventCreatedDt->format(DateTime::ATOM) : '';
            ?>
              <tr>
                <td class="mono"><span class="cell-primary"<?= $createdIso ? ' data-event-iso="' . htmlspecialchars($createdIso, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($createdDisplay, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                  <span class="cell-primary"><?= userDisplay($e) ?></span>
                  <span class="cell-meta">ID: <?= (int)$e['user_id'] ?></span>
                </td>
                <td><span class="cell-number">#<?= (int)$e['locker_number'] ?></span></td>
                <td><?= badgeEvent($e['event']) ?></td>
                <td><?= htmlspecialchars($e['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endwhile; ?>
            <?php if (!$hasEvents): ?>
              <tr>
                <td colspan="5" class="table-empty">No violation events logged yet.</td>
              </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="pager">
          <div class="pager__info">
            <?php
              if ($totalEvents) {
                  $from = $offset + 1;
                  $to = min($offset + $per, $totalEvents);
                  echo 'Showing ' . number_format($from) . ' to ' . number_format($to) . ' of ' . number_format($totalEvents);
              } else {
                  echo 'No events to display';
              }
            ?>
          </div>
          <?php if ($totalPages > 1): ?>
          <nav class="pagination" aria-label="History pages">
            <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : pageUrl($page - 1) ?>">Prev</a>
            <?php
              $window = 2;
              $addedLeftDots = false;
              $addedRightDots = false;
              for ($p = 1; $p <= $totalPages; $p++) {
                if ($p === 1 || $p === $totalPages || ($p >= $page - $window && $p <= $page + $window)) {
                  echo '<a class="page-link ' . ($p === $page ? 'active' : '') . '" href="' . pageUrl($p) . '">' . $p . '</a>';
                } elseif ($p < $page - $window && !$addedLeftDots) {
                  echo '<span class="page-link disabled">...</span>';
                  $addedLeftDots = true;
                } elseif ($p > $page + $window && !$addedRightDots) {
                  echo '<span class="page-link disabled">...</span>';
                  $addedRightDots = true;
                }
              }
            ?>
            <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : pageUrl($page + 1) ?>">Next</a>
          </nav>
          <?php endif; ?>
        </div>
      </div>
    </section>

  </main>
</div>

<script src="../../assets/js/violations.js?v=5"></script>
</body>
</html>
