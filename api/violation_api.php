<?php
/**
 * /kandado/api/violation_api.php
 * Standalone JSON API for violation counts and bans.
 *
 * Routes:
 *   ?status                 -> current user's ban+counts
 *   ?events                 -> last 50 events of current user
 *   ?admin_status&user_id=U -> same, but for any user (admin only)
 *   ?admin_events&user_id=U -> last 100 events for a user (admin only)
 *   ?admin_unban&user_id=U[&reset=1] -> lift ban (and optionally reset offenses) (admin only)
 *
 * Requires tables: violation_events, user_bans (from the SQL I provided).
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- CONFIG: match locker_api.php ---------- */
$host   = 'localhost';
$dbname = 'kandado';
$user   = 'root';
$pass   = '';

/* Optional: Composer (safe if missing) */
$composerAutoload = $_SERVER['DOCUMENT_ROOT'] . '/kandado/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

/* ---------- Small helpers ---------- */
if (!function_exists('jexit')) {
    function jexit($payload, int $code = 200) {
        http_response_code($code);
        echo json_encode($payload);
        exit;
    }
}
if (!function_exists('db')) {
    function db(): mysqli {
        global $host, $user, $pass, $dbname;
        $conn = new mysqli($host, $user, $pass, $dbname);
        $conn->set_charset('utf8mb4');
        // Match your sql_mode & timezone usage
        $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY'");
        $conn->query("SET time_zone = '+08:00'"); // Asia/Manila
        return $conn;
    }
}
if (!function_exists('require_login')) {
    function require_login(): int {
        if (!isset($_SESSION['user_id'])) {
            jexit(['error'=>'not_logged_in'], 401);
        }
        return (int)$_SESSION['user_id'];
    }
}
if (!function_exists('get_role')) {
    function get_role(mysqli $conn, int $uid): string {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? $row['role'] : 'user';
    }
}
if (!function_exists('require_admin')) {
    function require_admin(mysqli $conn, int $uid): void {
        if (get_role($conn, $uid) !== 'admin') {
            jexit(['error'=>'forbidden'], 403);
        }
    }
}

/* ---------- Ban helpers (local) ---------- */
function ban_get_row(mysqli $conn, int $uid): array {
    $stmt = $conn->prepare("
        SELECT offense_count, holds_since_last_offense, banned_until, is_permanent
        FROM user_bans WHERE user_id=? LIMIT 1
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return [
            'offense_count' => 0,
            'holds_since_last_offense' => 0,
            'banned_until' => null,
            'is_permanent' => 0
        ];
    }
    return $row;
}
function ban_is_active(array $ban): bool {
    if ((int)$ban['is_permanent'] === 1) return true;
    if (!empty($ban['banned_until'])) {
        return (strtotime($ban['banned_until']) > time());
    }
    return false;
}
function seconds_left(?string $until): ?int {
    if (empty($until)) return null;
    $delta = strtotime($until) - time();
    return $delta > 0 ? $delta : 0;
}

/* ---------- DB ---------- */
$conn = db();

/* ---------- ROUTES ---------- */

/* Self status */
if (isset($_GET['status'])) {
    $uid = require_login();

    $ban = ban_get_row($conn, $uid);

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM violation_events WHERE user_id=? AND event='hold'");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $tot = (int)$stmt->get_result()->fetch_assoc()['c'];

    $active = ban_is_active($ban);

    jexit([
        'success' => true,
        'user_id' => $uid,
        'total_hold_violations'    => $tot,
        'holds_since_last_offense' => (int)$ban['holds_since_last_offense'],
        'offense_count'            => (int)$ban['offense_count'],
        'is_currently_banned'      => $active,
        'banned_until'             => $ban['banned_until'],
        'is_permanent'             => (int)$ban['is_permanent'],
        'active_ban_seconds_left'  => $active && !$ban['is_permanent'] ? seconds_left($ban['banned_until']) : null
    ]);
}

/* Self events (recent) */
if (isset($_GET['events'])) {
    $uid = require_login();

    $stmt = $conn->prepare("
        SELECT id, locker_number, event, details, created_at
        FROM violation_events
        WHERE user_id=?
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    jexit(['success'=>true, 'events'=>$rows]);
}

/* Admin: status for any user */
if (isset($_GET['admin_status'])) {
    $admin = require_login(); require_admin($conn, $admin);

    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id <= 0) jexit(['error'=>'bad_user'], 400);

    $ban = ban_get_row($conn, $user_id);

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM violation_events WHERE user_id=? AND event='hold'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $tot = (int)$stmt->get_result()->fetch_assoc()['c'];

    $active = ban_is_active($ban);

    jexit([
        'success' => true,
        'user_id' => $user_id,
        'total_hold_violations'    => $tot,
        'holds_since_last_offense' => (int)$ban['holds_since_last_offense'],
        'offense_count'            => (int)$ban['offense_count'],
        'is_currently_banned'      => $active,
        'banned_until'             => $ban['banned_until'],
        'is_permanent'             => (int)$ban['is_permanent'],
        'active_ban_seconds_left'  => $active && !$ban['is_permanent'] ? seconds_left($ban['banned_until']) : null
    ]);
}

/* Admin: events list */
if (isset($_GET['admin_events'])) {
    $admin = require_login(); require_admin($conn, $admin);

    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id <= 0) jexit(['error'=>'bad_user'], 400);

    $stmt = $conn->prepare("
        SELECT id, locker_number, event, details, created_at
        FROM violation_events
        WHERE user_id=?
        ORDER BY id DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    jexit(['success'=>true, 'events'=>$rows]);
}

/* Admin: unban (optionally reset offenses) */
if (isset($_GET['admin_unban'])) {
    $admin = require_login(); require_admin($conn, $admin);

    $user_id = (int)($_GET['user_id'] ?? 0);
    $reset   = (int)($_GET['reset'] ?? 0); // 1 => also reset offense count & strike bucket

    if ($user_id <= 0) jexit(['error'=>'bad_user'], 400);

    if ($reset === 1) {
        $stmt = $conn->prepare("
            INSERT INTO user_bans (user_id, offense_count, holds_since_last_offense, banned_until, is_permanent)
            VALUES (?, 0, 0, NULL, 0)
            ON DUPLICATE KEY UPDATE offense_count=0, holds_since_last_offense=0, banned_until=NULL, is_permanent=0
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO user_bans (user_id, banned_until, is_permanent)
            VALUES (?, NULL, 0)
            ON DUPLICATE KEY UPDATE banned_until=NULL, is_permanent=0
        ");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $msg = $reset ? 'Admin unban + reset offenses' : 'Admin unban';
    $stmt2 = $conn->prepare("INSERT INTO violation_events (user_id, locker_number, event, details) VALUES (?, 0, 'unban', ?)");
    $stmt2->bind_param("is", $user_id, $msg);
    $stmt2->execute();

    jexit(['success'=>true]);
}

/* Fallback */
jexit(['error'=>'no_route'], 404);
