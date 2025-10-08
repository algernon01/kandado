<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- DB CONFIG ---
$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

// --- SECRETS ---
$SERVICE_SECRET = 'CHANGE_ME_SERVICE_123'; // used by your app when reporting a hold
$ADMIN_SECRET   = 'CHANGE_ME_ADMIN_456';   // used by admin tools / internal status checks

function jexit($payload, int $code = 200) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

try {
  $conn = new mysqli($host, $user, $pass, $dbname);
  $conn->set_charset('utf8mb4');
  // Force PH timezone at SQL layer too:
  $conn->query("SET SESSION time_zone = '+08:00'");
  $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY'");
} catch (mysqli_sql_exception $e) {
  jexit(['error'=>'db_connect','message'=>$e->getMessage()], 500);
}

function require_login_user_id(): int {
  if (!isset($_SESSION['user_id'])) jexit(['error'=>'not_logged_in'], 401);
  return (int)$_SESSION['user_id'];
}

function get_violation_row(mysqli $conn, int $user_id, bool $forUpdate = false): array {
  $sql = "SELECT user_id, holds_in_cycle, offense_no, banned_until, is_blocked, total_holds
          FROM user_violations WHERE user_id=? " . ($forUpdate ? "FOR UPDATE" : "");
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) {
    $stmtIns = $conn->prepare("INSERT INTO user_violations (user_id) VALUES (?)");
    $stmtIns->bind_param("i", $user_id);
    $stmtIns->execute();
    return [
      'user_id'        => $user_id,
      'holds_in_cycle' => 0,
      'offense_no'     => 0,
      'banned_until'   => null,
      'is_blocked'     => 0,
      'total_holds'    => 0,
    ];
  }
  return $row;
}

function log_event(mysqli $conn, int $user_id, string $event, ?string $details = null, ?int $locker = null): void {
  $stmt = $conn->prepare("INSERT INTO violation_events (user_id, locker_number, event, details) VALUES (?,?,?,?)");
  $stmt->bind_param("iiss", $user_id, $locker, $event, $details);
  $stmt->execute();
}

function lift_if_expired(mysqli $conn, array $row): array {
  if (!empty($row['banned_until'])) {
    $now = time();
    $banTs = strtotime($row['banned_until']); // PH time
    if ($banTs !== false && $banTs <= $now) {
      $stmt = $conn->prepare("UPDATE user_violations SET banned_until=NULL WHERE user_id=?");
      $stmt->bind_param("i", $row['user_id']);
      $stmt->execute();
      log_event($conn, (int)$row['user_id'], 'ban_lifted', 'Ban period elapsed');
      $row['banned_until'] = null;
    }
  }
  return $row;
}

function apply_offense(mysqli $conn, array $row): array {
  $user_id = (int)$row['user_id'];
  $offense = (int)$row['offense_no'] + 1;

  if ($offense >= 3) {
    $stmt = $conn->prepare("
      UPDATE user_violations
         SET offense_no=?, holds_in_cycle=0, banned_until=NULL, is_blocked=1
       WHERE user_id=?");
    $stmt->bind_param("ii", $offense, $user_id);
    $stmt->execute();
    log_event($conn, $user_id, 'ban_applied', 'Offense 3: permanently blocked');
    $row['offense_no'] = 3;
    $row['holds_in_cycle'] = 0;
    $row['banned_until'] = null;
    $row['is_blocked'] = 1;
    return $row;
  }

  $days = ($offense === 1) ? 1 : 3;
  $ban_until = date('Y-m-d H:i:s', time() + $days * 86400);

  $stmt = $conn->prepare("
    UPDATE user_violations
       SET offense_no=?, holds_in_cycle=0, banned_until=?, is_blocked=0
     WHERE user_id=?");
  $stmt->bind_param("isi", $offense, $ban_until, $user_id);
  $stmt->execute();

  log_event($conn, $user_id, 'ban_applied', "Offense {$offense}: banned until {$ban_until}");
  $row['offense_no']     = $offense;
  $row['holds_in_cycle'] = 0;
  $row['banned_until']   = $ban_until;
  $row['is_blocked']     = 0;
  return $row;
}

function decorate_status(array $row): array {
  $now = time();
  $banned_until_ts = $row['banned_until'] ? strtotime($row['banned_until']) : null;
  $is_time_banned = $banned_until_ts ? ($banned_until_ts > $now) : false;
  $remaining_sec = $is_time_banned ? max(0, $banned_until_ts - $now) : 0;

  return array_merge($row, [
    'is_banned' => ((int)$row['is_blocked'] === 1) || $is_time_banned,
    'banned_until_ts' => $banned_until_ts,
    'banned_seconds_remaining' => $remaining_sec,
    'now_ph' => date('Y-m-d H:i:s', $now),
  ]);
}

/* --------------------- ROUTES --------------------- */

if (isset($_GET['status'])) {
  $user_id = null;

  // Preferred: admin/service check of arbitrary user
  if (isset($_GET['user_id']) && isset($_GET['secret']) && $_GET['secret'] === $ADMIN_SECRET) {
    $user_id = (int)$_GET['user_id'];
  } else {
    // Fallback: session-bound query
    $user_id = require_login_user_id();
  }

  try {
    $conn->begin_transaction();
    $row = get_violation_row($conn, $user_id, true);
    $row = lift_if_expired($conn, $row);
    $conn->commit();

    jexit(['success'=>true, 'status'=>decorate_status($row)]);
  } catch (mysqli_sql_exception $e) {
    $conn->rollback();
    jexit(['error'=>'status_failed','message'=>$e->getMessage()], 500);
  }
}

if (isset($_GET['record_hold'])) {
  if (!isset($_GET['secret']) || $_GET['secret'] !== $SERVICE_SECRET) {
    jexit(['error'=>'forbidden'], 403);
  }
  $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
  if ($user_id <= 0) jexit(['error'=>'missing_user_id'], 400);
  $locker  = isset($_GET['locker']) ? (int)$_GET['locker'] : null;

  try {
    $conn->begin_transaction();

    $row = get_violation_row($conn, $user_id, true);
    $row = lift_if_expired($conn, $row);

    $row['holds_in_cycle'] = (int)$row['holds_in_cycle'] + 1;
    $row['total_holds']    = (int)$row['total_holds'] + 1;

    $stmtUp = $conn->prepare("
      UPDATE user_violations
         SET holds_in_cycle=?, total_holds=?
       WHERE user_id=?");
    $stmtUp->bind_param("iii", $row['holds_in_cycle'], $row['total_holds'], $user_id);
    $stmtUp->execute();

    log_event($conn, $user_id, 'hold_detected', 'Hold recorded', $locker);

    if ($row['holds_in_cycle'] >= 3) {
      $row = apply_offense($conn, $row);
    }

    $conn->commit();
    jexit(['success'=>true, 'status'=>decorate_status($row)]);
  } catch (mysqli_sql_exception $e) {
    $conn->rollback();
    jexit(['error'=>'record_hold_failed','message'=>$e->getMessage()], 500);
  }
}

if (isset($_GET['admin_unblock'])) {
  if (!isset($_GET['secret']) || $_GET['secret'] !== $ADMIN_SECRET) jexit(['error'=>'forbidden'], 403);
  $user_id = (int)$_GET['admin_unblock'];
  if ($user_id <= 0) jexit(['error'=>'missing_user_id'], 400);

  try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("UPDATE user_violations SET is_blocked=0, banned_until=NULL WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    log_event($conn, $user_id, 'unblocked', 'Admin unblocked user');
    $row = get_violation_row($conn, $user_id, true);
    $conn->commit();
    jexit(['success'=>true, 'status'=>decorate_status($row)]);
  } catch (mysqli_sql_exception $e) {
    $conn->rollback();
    jexit(['error'=>'admin_unblock_failed','message'=>$e->getMessage()], 500);
  }
}

if (isset($_GET['admin_reset'])) {
  if (!isset($_GET['secret']) || $_GET['secret'] !== $ADMIN_SECRET) jexit(['error'=>'forbidden'], 403);
  $user_id = (int)$_GET['admin_reset'];
  if ($user_id <= 0) jexit(['error'=>'missing_user_id'], 400);

  try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("
      UPDATE user_violations
         SET holds_in_cycle=0, offense_no=0, banned_until=NULL, is_blocked=0
       WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    log_event($conn, $user_id, 'reset', 'Admin reset violations');
    $row = get_violation_row($conn, $user_id, true);
    $conn->commit();
    jexit(['success'=>true, 'status'=>decorate_status($row)]);
  } catch (mysqli_sql_exception $e) {
    $conn->rollback();
    jexit(['error'=>'admin_reset_failed','message'=>$e->getMessage()], 500);
  }
}

jexit(['error'=>'no_route'], 404);
