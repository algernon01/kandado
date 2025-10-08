<?php
header('Content-Type: application/json; charset=utf-8');
session_start();


require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CONFIG ---
$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$esp32_host = 'locker-esp32.local';
$TOTAL_LOCKERS = 4;
$esp32_secret = 'MYSECRET123';


require_once $_SERVER['DOCUMENT_ROOT'] . '/kandado/lib/email_lib.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/kandado/phpqrcode/qrlib.php';
$qr_folder = $_SERVER['DOCUMENT_ROOT'] . '/kandado/qr_image/';
if (!file_exists($qr_folder)) mkdir($qr_folder, 0777, true);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function jexit($payload, int $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// ------------------- DB CONNECTION -------------------
try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY'");
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    jexit(['error'=>'db_connect','message'=>$e->getMessage()],500);
}

/* ---------- Helpers ---------- */
function duration_minutes_map() {
    return [
        '5min'   => 5,      
        '20min'  => 20,
        '30min'  => 30,
        '1hour'  => 60,
        '2hours' => 120,
        '4hours' => 240,
        '8hours' => 480,
        '12hours'=> 720,
        '24hours'=> 1440,
        '2days'  => 2880,
        '7days'  => 10080
    ];
}
function price_map_php() {
    return [
        '5min'   => 0.50,   
        '20min'  => 2.00,
        '30min'  => 3.00,
        '1hour'  => 5.00,
        '2hours' => 10.00,
        '4hours' => 15.00,
        '8hours' => 20.00,
        '12hours'=> 25.00,
        '24hours'=> 30.00,
        '2days'  => 50.00,
        '7days'  => 150.00
    ];
}
function safe_duration_minutes($label) {
    $map = duration_minutes_map();
    return isset($map[$label]) ? $map[$label] : 60;
}
function safe_price($label) {
    $p = price_map_php();
    return isset($p[$label]) ? (float)$p[$label] : (float)$p['1hour'];
}
function now_ph() {
    date_default_timezone_set('Asia/Manila');
    return time();
}
function require_login() {
    if (!isset($_SESSION['user_id'])) jexit(['error'=>'not_logged_in'],401);
    return (int)$_SESSION['user_id'];
}

/* ---- Named locks (app-level mutex) ---- */
function acquire_lock(mysqli $conn, string $name, int $timeout_sec = 6): bool {
    $stmt = $conn->prepare("SELECT GET_LOCK(?, ? ) AS got");
    $stmt->bind_param("si", $name, $timeout_sec);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return isset($row['got']) && (int)$row['got'] === 1;
}
function release_lock(mysqli $conn, string $name): void {
    try {
        $stmt = $conn->prepare("DO RELEASE_LOCK(?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    } catch (\Throwable $e) {
        // ignore
    }
}

/* ===== BAN GUARD (inline; no extra includes) ===== */
function _ban_get_row(mysqli $conn, int $uid): array {
    $stmt = $conn->prepare("SELECT offense_count, holds_since_last_offense, banned_until, is_permanent FROM user_bans WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: ['offense_count'=>0,'holds_since_last_offense'=>0,'banned_until'=>null,'is_permanent'=>0];
}
function _ban_is_active(array $ban): bool {
    if ((int)$ban['is_permanent'] === 1) return true;
    if (!empty($ban['banned_until'])) return (strtotime($ban['banned_until']) > time());
    return false;
}
function require_not_banned_inline(mysqli $conn, int $uid) {
    $ban = _ban_get_row($conn, $uid);
    if (_ban_is_active($ban)) {
        // Pull latest ban event for a human-readable reason
        $stmt = $conn->prepare("
            SELECT event, details, created_at
            FROM violation_events
            WHERE user_id=? AND event IN ('ban_1d','ban_3d','ban_perm')
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $ev = $stmt->get_result()->fetch_assoc();
        $reason = $ev['details'] ?? 'Repeated locker holds.';

        jexit([
            'error'         => 'account_banned',
            'message'       => "You have been banned: {$reason}",
            'banned_until'  => $ban['banned_until'],
            'is_permanent'  => (int)$ban['is_permanent'],
            'offense_count' => (int)$ban['offense_count']
        ], 423); // 423 Locked
    }
}

/* ---------- Wallet helpers ---------- */
function get_wallet_balance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (float)$row['balance'] : 0.0;
}

/**
 * Credit wallet (TOPUP) — single running topup row + idempotent by reference_no
 */
function wallet_credit($conn, $user_id, $amount, $method, $reference_no, $notes = null, $meta = null) {
    if ($amount <= 0) return ['ok'=>false, 'error'=>'invalid_amount'];

    try {
        $conn->begin_transaction();

        // Create/lock wallet row
        $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id=? FOR UPDATE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            $stmtIns = $conn->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, 0.00)");
            $stmtIns->bind_param("i", $user_id);
            $stmtIns->execute();
            $current = 0.00;
        } else {
            $current = (float)$row['balance'];
        }

        if ($reference_no) {
            $stmtRef = $conn->prepare("SELECT id FROM wallet_transactions WHERE reference_no=? LIMIT 1");
            $stmtRef->bind_param("s", $reference_no);
            $stmtRef->execute();
            if ($stmtRef->get_result()->fetch_assoc()) {
                $conn->commit();
                return ['ok'=>true, 'idempotent'=>true, 'balance'=>$current];
            }
        }

        $delta = round($amount, 2);
        $newBal = round($current + $delta, 2);
        $note   = $notes ?: ('Top-up via ' . $method);
        $metaJson = $meta ? json_encode($meta) : null;

        $tx_id = null;
        $stmtFind = $conn->prepare("SELECT id FROM wallet_transactions WHERE user_id=? AND type='topup' LIMIT 1");
        $stmtFind->bind_param("i", $user_id);
        $stmtFind->execute();
        $existing = $stmtFind->get_result()->fetch_assoc();

        if ($existing) {
            $tx_id = (int)$existing['id'];
            try {
                $stmtU = $conn->prepare("
                    UPDATE wallet_transactions
                       SET method=?, amount=amount+?, reference_no=?, notes=?, meta=COALESCE(?, meta)
                     WHERE id=?
                ");
                $stmtU->bind_param("sdsssi", $method, $delta, $reference_no, $note, $metaJson, $tx_id);
                $stmtU->execute();
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() === 1062) {
                    $stmtU2 = $conn->prepare("
                        UPDATE wallet_transactions
                           SET method=?, amount=amount+?, notes=?, meta=COALESCE(?, meta)
                         WHERE id=?
                    ");
                    $stmtU2->bind_param("sdssi", $method, $delta, $note, $metaJson, $tx_id);
                    $stmtU2->execute();
                } else {
                    throw $e;
                }
            }
        } else {
            $type = 'topup';
            $stmtTx = $conn->prepare("
                INSERT INTO wallet_transactions
                    (user_id, type, method, amount, reference_no, notes, meta)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtTx->bind_param("issdsss", $user_id, $type, $method, $delta, $reference_no, $note, $metaJson);
            $stmtTx->execute();
            $tx_id = $conn->insert_id;
        }

        $stmtUp = $conn->prepare("UPDATE user_wallets SET balance=? WHERE user_id=?");
        $stmtUp->bind_param("di", $newBal, $user_id);
        $stmtUp->execute();

        $conn->commit();
        return ['ok'=>true, 'balance'=>$newBal, 'tx_id'=>$tx_id];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ($e->getCode() === 1062 && $reference_no) {
            $bal = get_wallet_balance($conn, $user_id);
            return ['ok'=>true, 'idempotent'=>true, 'balance'=>$bal];
        }
        return ['ok'=>false, 'error'=>'wallet_credit_failed', 'message'=>$e->getMessage()];
    }
}

/**
 * Debit wallet (RESERVATION/EXTEND) — single running debit row + idempotent by reference_no
 */
function wallet_debit($conn, $user_id, $amount, $reference_no, $notes = null, $meta = null) {
    if ($amount <= 0) return ['ok'=>false, 'error'=>'invalid_amount'];

    try {
        $conn->begin_transaction();

        if ($reference_no) {
            $stmt = $conn->prepare("SELECT id FROM wallet_transactions WHERE reference_no=? LIMIT 1");
            $stmt->bind_param("s", $reference_no);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $bal = get_wallet_balance($conn, $user_id);
                $conn->commit();
                return ['ok'=>true, 'idempotent'=>true, 'balance'=>$bal];
            }
        }

        $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id=? FOR UPDATE");
        $stmt->bind_param("i",$user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            $conn->rollback();
            return ['ok'=>false, 'error'=>'insufficient_funds', 'balance'=>0.00];
        }

        $current = (float)$row['balance'];
        if ($current < $amount) {
            $conn->rollback();
            return ['ok'=>false, 'error'=>'insufficient_funds', 'balance'=>$current, 'needed'=>$amount];
        }

        $newBal = round($current - $amount, 2);

        $type = 'debit';
        $method = 'Wallet';
        $metaJson = $meta ? json_encode($meta) : null;

        $stmtFind = $conn->prepare("SELECT id FROM wallet_transactions WHERE user_id=? AND type='debit' LIMIT 1");
        $stmtFind->bind_param("i", $user_id);
        $stmtFind->execute();
        $existing = $stmtFind->get_result()->fetch_assoc();

        if ($existing) {
            $tx_id = (int)$existing['id'];
            try {
                $stmtU = $conn->prepare("
                    UPDATE wallet_transactions
                       SET method=?, amount=amount+?, reference_no=?, notes=?, meta=COALESCE(?, meta)
                     WHERE id=?
                ");
                $stmtU->bind_param("sdsssi", $method, $amount, $reference_no, $notes, $metaJson, $tx_id);
                $stmtU->execute();
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() === 1062) {
                    $stmtU2 = $conn->prepare("
                        UPDATE wallet_transactions
                           SET method=?, amount=amount+?, notes=?, meta=COALESCE(?, meta)
                         WHERE id=?
                    ");
                    $stmtU2->bind_param("sdssi", $method, $amount, $notes, $metaJson, $tx_id);
                    $stmtU2->execute();
                } else {
                    throw $e;
                }
            }
        } else {
            $stmtTx = $conn->prepare("
                INSERT INTO wallet_transactions (user_id, type, method, amount, reference_no, notes, meta)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtTx->bind_param("issdsss", $user_id, $type, $method, $amount, $reference_no, $notes, $metaJson);
            $stmtTx->execute();
        }

        $stmtUp = $conn->prepare("UPDATE user_wallets SET balance=? WHERE user_id=?");
        $stmtUp->bind_param("di", $newBal, $user_id);
        $stmtUp->execute();

        $conn->commit();
        return ['ok'=>true, 'balance'=>$newBal];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return ['ok'=>false, 'error'=>'wallet_debit_failed', 'message'=>$e->getMessage()];
    }
}

/* ---------- ONE place to write locker_history ---------- */
function insert_history($conn, int $locker_number, string $code = null, string $user_fullname = '', string $user_email = '', ?string $expires_at = null, ?int $duration_minutes = null){
    $stmt = $conn->prepare("
        INSERT INTO locker_history
            (locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at)
        VALUES (?,?,?,?,?,?,NOW())
    ");
    $stmt->bind_param("issssi",
        $locker_number,
        $code,
        $user_fullname,
        $user_email,
        $expires_at,
        $duration_minutes
    );
    $stmt->execute();
}

/* ------------------- checkUserLocker ------------------- */
if(isset($_GET['checkUserLocker'])){
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $conn->prepare("SELECT locker_number, code, expires_at FROM locker_qr WHERE user_id=? AND status='occupied' LIMIT 1");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if($existing){
        jexit([
            'hasLocker'=>true,
            'lockerNumber'=>$existing['locker_number'],
            'code'=>$existing['code'],
            'expires_at'=>$existing['expires_at']
        ]);
    } else {
        jexit(['hasLocker'=>false]);
    }
}

/* ------------------- HELPER: Move expired QR to history ------------------- */
function moveExpiredQRs($conn, $qr_folder){
    date_default_timezone_set('Asia/Manila');

    $stmt = $conn->prepare("
        SELECT locker_number, code, user_id, expires_at, duration_minutes, item 
        FROM locker_qr 
        WHERE expires_at <= NOW() AND code IS NOT NULL
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    while($row = $res->fetch_assoc()){
        $locker_number    = (int)$row['locker_number'];
        $code             = $row['code'];
        $user_id          = $row['user_id'] ?? null;
        $expires_at       = $row['expires_at'];
        $duration_minutes = $row['duration_minutes'];
        $item             = (int)$row['item'];

        $user_fullname = '';
        $user_email = '';
        if($user_id){
            $stmt_user = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id=? LIMIT 1");
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            if($u=$stmt_user->get_result()->fetch_assoc()){
                $user_fullname = $u['first_name'].' '.$u['last_name'];
                $user_email = $u['email'];
            }
        }

        insert_history($conn, $locker_number, $code, $user_fullname, $user_email, $expires_at, (int)$duration_minutes);

        $stmt_item = $conn->prepare("SELECT item FROM locker_qr WHERE locker_number=? LIMIT 1");
        $stmt_item->bind_param("i", $locker_number);
        $stmt_item->execute();
        $itemRow = $stmt_item->get_result()->fetch_assoc();
        $item = (int)($itemRow['item'] ?? 0);

        if ($item === 1) {
            $stmt_update = $conn->prepare("
                UPDATE locker_qr 
                SET code=NULL, status='hold', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0
                WHERE locker_number=?");
            $stmt_update->bind_param("i", $locker_number);
            $stmt_update->execute();

            if($user_email){
                email_on_hold($user_email, $user_fullname, $locker_number);
            }
        } else {
            $stmt_update = $conn->prepare("
                UPDATE locker_qr 
                SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0
                WHERE locker_number=?");
            $stmt_update->bind_param("i", $locker_number);
            $stmt_update->execute();

            if ($user_email) {
                email_expired_released($user_email, $user_fullname, $locker_number);
            }
        }

        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) @unlink($qr_file);
    }
}

/* ------------------- NEW: Pre-expiry reminders ------------------- */
function checkAndSendReminders($conn){
    date_default_timezone_set('Asia/Manila');
    $now = time();

    $in_exact_minute = function(int $remaining_sec, int $threshold_min): bool {
        $T = $threshold_min * 60;
        return ($remaining_sec <= $T) && ($remaining_sec > $T - 60);
    };

    $sql = "
    SELECT l.locker_number, l.code, l.user_id, l.expires_at, l.duration_minutes,
           l.notify30_sent, l.notify15_sent, l.notify10_sent, l.notify2_sent,
           u.first_name, u.last_name, u.email
    FROM locker_qr l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE l.status='occupied' AND l.expires_at IS NOT NULL AND l.expires_at > NOW()
    ";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        $locker         = (int)$row['locker_number'];
        $expires_ts     = strtotime($row['expires_at']);
        if ($expires_ts === false) continue;

        $remaining_sec  = $expires_ts - $now;
        if ($remaining_sec <= 0) continue;

        $total_min      = (int)$row['duration_minutes'];
        $sent30         = (int)$row['notify30_sent'];
        $sent15         = (int)$row['notify15_sent'];
        $sent10         = (int)$row['notify10_sent'];
        $sent2          = (int)$row['notify2_sent'];

        $name           = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
        $email          = $row['email'] ?? '';
        $expires_fmt    = date('F j, Y h:i A', $expires_ts);

        $didUpdate = false;

        if ($total_min >= 60) {
            if (!$sent30 && $in_exact_minute($remaining_sec, 30)) {
                email_time_left($email, $name, $locker, 30, $expires_fmt);
                $sent30 = 1; $didUpdate = true;
            }
            if (!$sent15 && $in_exact_minute($remaining_sec, 15)) {
                if (!$sent30) { email_time_left($email, $name, $locker, 30, $expires_fmt); $sent30 = 1; }
                email_time_left($email, $name, $locker, 15, $expires_fmt);
                $sent15 = 1; $didUpdate = true;
            }
        } elseif ($total_min >= 30) {
            if (!$sent15 && $in_exact_minute($remaining_sec, 15)) {
                email_time_left($email, $name, $locker, 15, $expires_fmt);
                $sent15 = 1; $didUpdate = true;
            }
        } elseif ($total_min >= 20) {
            if (!$sent10 && $in_exact_minute($remaining_sec, 10)) {
                email_time_left($email, $name, $locker, 10, $expires_fmt);
                $sent10 = 1; $didUpdate = true;
            }
        } elseif ($total_min >= 5) {
            if (!$sent2 && $in_exact_minute($remaining_sec, 2)) {
                email_time_left($email, $name, $locker, 2, $expires_fmt);
                $sent2 = 1; $didUpdate = true;
            }
        }

        if ($didUpdate) {
            $stmt = $conn->prepare("
                UPDATE locker_qr
                SET notify30_sent=?, notify15_sent=?, notify10_sent=?, notify2_sent=?
                WHERE locker_number=?
            ");
            $stmt->bind_param("iiiii", $sent30, $sent15, $sent10, $sent2, $locker);
            $stmt->execute();
        }
    }
}

// housekeeping every request
moveExpiredQRs($conn, $qr_folder);
checkAndSendReminders($conn);

/* ------------------- WALLET & PRICES ENDPOINTS ------------------- */
if (isset($_GET['wallet'])) {
    $uid = require_login();
    $balance = get_wallet_balance($conn, $uid);
    jexit(['success'=>true, 'balance'=>$balance]);
}

if (isset($_GET['wallet_topup'])) {
    $uid = require_login();
    $method = $_GET['method'] ?? 'GCash';
    if (!in_array($method, ['GCash','Maya','Admin'], true)) {
        jexit(['error'=>'invalid_method'],400);
    }
    $amount = (float)($_GET['amount'] ?? 0);
    $reference_no = $_GET['ref'] ?? null;
    $res = wallet_credit($conn, $uid, round($amount,2), $method, $reference_no, 'Top-up via '.$method);
    if (!$res['ok']) jexit(['error'=>$res['error'], 'message'=>$res['message'] ?? null], 500);
    jexit(['success'=>true, 'balance'=>$res['balance'], 'idempotent'=>!empty($res['idempotent'])]);
}

if (isset($_GET['prices'])) {
    jexit(['success'=>true, 'prices'=>price_map_php()]);
}

/* ------------------- MARK QR AS USED (ESP32) ------------------- */
if(isset($_GET['used'])){
    $code = $_GET['used'];
    $secret = $_GET['secret'] ?? '';
    if($secret !== $esp32_secret) jexit(['error'=>'unauthorized'],401);
    if(empty($code)) jexit(['error'=>'no_code'],400);

    // Optional: per-locker lock while finalizing "used"
    try{
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT locker_number, user_id, expires_at, duration_minutes FROM locker_qr WHERE code=? LIMIT 1");
        $stmt->bind_param("s",$code);
        $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc();
        if(!$row){ $conn->rollback(); jexit(['error'=>'code_not_found'],404); }

        $locker_number = (int)$row['locker_number'];
        $user_id = $row['user_id'] ?? null;
        $expires_at = $row['expires_at'];
        $duration_minutes = $row['duration_minutes'];

        $user_fullname = ''; $user_email = '';
        if($user_id){
            $stmt_user = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id=? LIMIT 1");
            $stmt_user->bind_param("i",$user_id);
            $stmt_user->execute();
            if($u=$stmt_user->get_result()->fetch_assoc()){
                $user_fullname = $u['first_name'].' '.$u['last_name'];
                $user_email = $u['email'];
            }
        }

        insert_history($conn, $locker_number, $code, $user_fullname, $user_email, $expires_at, (int)$duration_minutes);

        $stmt_update = $conn->prepare("UPDATE locker_qr SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL, notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0 WHERE locker_number=?");
        $stmt_update->bind_param("i",$locker_number);
        $stmt_update->execute();

        $conn->commit();

        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) @unlink($qr_file);

        jexit(['success'=>true]);
    }catch(mysqli_sql_exception $e){
        $conn->rollback();
        jexit(['error'=>'tx_failed','message'=>$e->getMessage()],500);
    }
}

/* ------------------- EXTEND LOCKER TIME (IDEMPOTENT, WALLET) ------------------- */
if(isset($_GET['extend'])){
    $user_id = require_login();
    require_not_banned_inline($conn, $user_id); 
    $locker  = (int)$_GET['extend'];

    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = safe_duration_minutes($requested);
    $amount = safe_price($requested);

    $stmt = $conn->prepare("SELECT code, expires_at FROM locker_qr WHERE locker_number=? AND user_id=? AND status='occupied' LIMIT 1");
    $stmt->bind_param("ii", $locker, $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if(!$existing){
        jexit(['error'=>'not_owner','message'=>'You cannot extend this locker or it is not active.'],403);
    }

    $currentExpiresTs = strtotime($existing['expires_at']);
    if ($currentExpiresTs === false) $currentExpiresTs = now_ph();

    $reference_no = $_GET['ref'] ?? uniqid("WXT");
    $method = 'Wallet';

    try {
        $conn->begin_transaction();

        $stmt_chk = $conn->prepare("SELECT id FROM payments WHERE user_id=? AND locker_number=? AND reference_no=? LIMIT 1");
        $stmt_chk->bind_param("iis", $user_id, $locker, $reference_no);
        $stmt_chk->execute();
        $dup = $stmt_chk->get_result()->fetch_assoc();

        if ($dup) {
            $stmt_state = $conn->prepare("SELECT code, expires_at FROM locker_qr WHERE locker_number=? LIMIT 1");
            $stmt_state->bind_param("i", $locker);
            $stmt_state->execute();
            $row = $stmt_state->get_result()->fetch_assoc();
            $expires_ts = $row && $row['expires_at'] ? strtotime($row['expires_at']) : $currentExpiresTs;

            $qr_url = '/kandado/qr_image/qr_'.($row['code'] ?? $existing['code']).'.png';

            $conn->commit();
            jexit([
                'success'=>true,
                'code'=> $row['code'] ?? $existing['code'],
                'qr_url'=>$qr_url,
                'expires_at'=> date('Y-m-d H:i:s', $expires_ts),
                'expires_at_ms'=> $expires_ts * 1000,
                'duration_minutes'=>0,
                'idempotent'=>true
            ]);
        }

        $deb = wallet_debit($conn, $user_id, round($amount,2), $reference_no, "Extend locker #$locker ($requested)");
        if (!$deb['ok']) {
            $conn->rollback();
            if ($deb['error'] === 'insufficient_funds') {
                jexit([
                    'error'=>'insufficient_balance',
                    'message'=>'Your wallet balance is not enough to extend.',
                    'balance'=>$deb['balance'],
                    'needed'=>$amount
                ], 402);
            }
            jexit(['error'=>'wallet_debit_failed','message'=>$deb['message'] ?? null],500);
        }

        $newExpiresTs = $currentExpiresTs + (int)round($duration_minutes * 60);
        $expires_at   = date('Y-m-d H:i:s', $newExpiresTs);

        $stmt_upd = $conn->prepare("UPDATE locker_qr SET expires_at=?, duration_minutes=duration_minutes+?, notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0 WHERE locker_number=?");
        $stmt_upd->bind_param("sii", $expires_at, $duration_minutes, $locker);
        $stmt_upd->execute();

        $created_at = date('Y-m-d H:i:s');
        $dur_for_db = (string)$duration_minutes;
        $stmt_pay = $conn->prepare("INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at) VALUES (?,?,?,?,?,?,?)");
        $stmt_pay->bind_param("iisdsss", $user_id, $locker, $method, $amount, $reference_no, $dur_for_db, $created_at);
        $stmt_pay->execute();

        $conn->commit();

        $qr_url = '/kandado/qr_image/qr_'.$existing['code'].'.png';
        jexit([
            'success'=>true,
            'code'=>$existing['code'],
            'qr_url'=>$qr_url,
            'expires_at'=>$expires_at,
            'expires_at_ms'=>$newExpiresTs * 1000,
            'duration_minutes'=>$duration_minutes,
            'balance'=>$deb['balance']
        ]);
    } catch(mysqli_sql_exception $e){
        $conn->rollback();
        jexit(['error'=>'extend_failed','message'=>$e->getMessage()],500);
    }
}

/* ====================== TERMINATE LOCKER NOW ====================== */
if (isset($_GET['terminate'])) {
    $user_id = require_login();

    $locker      = (int)$_GET['terminate'];
    $reason      = $_GET['reason'] ?? 'user_request';

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            SELECT locker_number, code, user_id, expires_at, duration_minutes, item
            FROM locker_qr
            WHERE locker_number = ? AND user_id = ? AND status = 'occupied'
            LIMIT 1
        ");
        $stmt->bind_param("ii", $locker, $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            $conn->rollback();
            jexit(['error' => 'not_owner_or_not_active', 'message' => 'You cannot terminate this locker or it is not active.'], 403);
        }

        $locker_number    = (int)$row['locker_number'];
        $code             = $row['code'];
        $expires_at       = $row['expires_at'];
        $duration_minutes = $row['duration_minutes'];
        $item             = (int)$row['item'];

        $user_fullname = ''; $user_email = '';
        $stmt_user = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        if ($u = $stmt_user->get_result()->fetch_assoc()) {
            $user_fullname = $u['first_name'].' '.$u['last_name'];
            $user_email    = $u['email'];
        }

        insert_history($conn, $locker_number, $code, $user_fullname, $user_email, $expires_at, (int)$duration_minutes);

        if ($item === 1) {
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, status='hold', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0
                WHERE locker_number=?
            ");
            $next_status = 'hold';
        } else {
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0
                WHERE locker_number=?
            ");
            $next_status = 'available';
        }
        $stmt_upd->bind_param("i", $locker_number);
        $stmt_upd->execute();

        $conn->commit();

        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if ($code && file_exists($qr_file)) @unlink($qr_file);

        jexit([
            'success' => true,
            'status'  => $next_status,
            'message' => $next_status === 'hold'
                ? 'Locker terminated. Locker is on hold because an item is still inside.'
                : 'Locker terminated and released.'
        ]);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        jexit(['error' => 'terminate_failed', 'message' => $e->getMessage()], 500);
    }
}

/* ------------------- GENERATE QR (reserve using WALLET) ------------------- */
/*  >>> FIXED: serialize ESP32 calls + idempotency, correct HTTP code constant <<<  */
if(isset($_GET['generate'])){
    $user_id = require_login();
    require_not_banned_inline($conn, $user_id); 
    $locker = (int)$_GET['generate'];
    if($locker<1||$locker>$TOTAL_LOCKERS) jexit(['error'=>'invalid_locker'],400);

    // Block reservation if locker is under maintenance
    $stmt_chk = $conn->prepare("SELECT maintenance FROM locker_qr WHERE locker_number=? LIMIT 1");
    $stmt_chk->bind_param("i", $locker);
    $stmt_chk->execute();
    $row_chk = $stmt_chk->get_result()->fetch_assoc();
    if ($row_chk && (int)$row_chk['maintenance'] === 1) {
        jexit(['error'=>'under_maintenance','message'=>'This locker is temporarily unavailable due to maintenance.'], 409);
    }

    // Prevent multiple active lockers for same user (include HOLD)
    $stmt = $conn->prepare("SELECT locker_number, code, status FROM locker_qr WHERE user_id=? AND status IN ('occupied','hold') LIMIT 1");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $existing=$stmt->get_result()->fetch_assoc();
    if($existing){
        if ($existing['status'] === 'hold') {
            jexit([
                'error'=>'has_hold',
                'message'=>'You still have a locker on hold (item detected inside). Please have staff clear it before avail for a new one.',
                'locker'=>(int)$existing['locker_number']
            ],409);
        }
        $existing_code=$existing['code'];
        $existing_file='/kandado/qr_image/qr_'.$existing_code.'.png';
        jexit([
            'error'=>'already_has_locker',
            'message'=>'You already have a locker. Please use it before generating a new one.',
            'code'=>$existing_code,
            'qr_url'=>$existing_file
        ],400);
    }

    // Duration + price
    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = safe_duration_minutes($requested);
    $amount = safe_price($requested);

    date_default_timezone_set('Asia/Manila');
    $expires_at = date('Y-m-d H:i:s', time()+$duration_minutes*60);
    $reserve_date = date('F j, Y');
    $reserve_time = date('h:i A');
    $expires_at_formatted = date('F j, Y h:i A', strtotime($expires_at));

    // Idempotency for generate via reference_no (if client supplies it)
    $reference_no = $_GET['ref'] ?? uniqid('WAL'); // stable when provided by client
    // If same ref exists, return current state
    $stmt_ref = $conn->prepare("SELECT locker_number FROM payments WHERE user_id=? AND locker_number=? AND reference_no=? LIMIT 1");
    $stmt_ref->bind_param("iis", $user_id, $locker, $reference_no);
    $stmt_ref->execute();
    $dup = $stmt_ref->get_result()->fetch_assoc();
    if ($dup) {
        // Find the current QR/code for that locker (if any)
        $stmt_cur = $conn->prepare("SELECT code, expires_at FROM locker_qr WHERE locker_number=? LIMIT 1");
        $stmt_cur->bind_param("i", $locker);
        $stmt_cur->execute();
        $cur = $stmt_cur->get_result()->fetch_assoc();
        if ($cur && $cur['code']) {
            $qr_url = '/kandado/qr_image/qr_'.$cur['code'].'.png';
            jexit([
                'success'=>true,
                'code'=>$cur['code'],
                'qr_url'=>$qr_url,
                'expires_at'=>$cur['expires_at'],
                'duration_minutes'=>0,
                'idempotent'=>true
            ]);
        }
        // If no code found, fall-through to regenerate
    }

    // Helper: call ESP32 with retries (correct HTTP code constant)
    $callEsp32Generate = function(int $lockerIndex) {
        $attempts = 5;
        $timeout  = 2;
        $backoffMs= 180;

        for ($i=1; $i<=$attempts; $i++) {
            $url = "http://{$GLOBALS['esp32_host']}/generate?locker=".$lockerIndex;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $response = curl_exec($ch);
            $curl_err = curl_error($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 403) {
                return ['ok'=>false, 'type'=>'locker_unavailable', 'http'=>$http_code, 'raw'=>$response, 'err'=>$curl_err];
            }

            $data = is_string($response) ? json_decode($response, true) : null;
            if ($http_code === 200 && is_array($data) && isset($data['code']) && is_string($data['code']) && $data['code'] !== '') {
                return ['ok'=>true, 'data'=>$data];
            }

            usleep(($backoffMs + rand(0,120)) * 1000);
        }
        return ['ok'=>false, 'type'=>'esp32_unreachable', 'http'=>$http_code ?? 0, 'raw'=>$response ?? null, 'err'=>$curl_err ?? null];
    };

    $globalLock = 'esp32_generate';   // serialize device access
    $gotGlobal = false;

    try {
        // Acquire global device mutex before touching DB row to avoid long row locks
        $gotGlobal = acquire_lock($conn, $globalLock, 8);
        if (!$gotGlobal) {
            jexit(['error'=>'device_busy','message'=>'Device is handling another request. Please try again.'], 423);
        }

        $conn->begin_transaction();

        // Hard lock the locker row so only the first requester for THIS locker proceeds
        $stmtLock = $conn->prepare("SELECT status FROM locker_qr WHERE locker_number=? FOR UPDATE");
        $stmtLock->bind_param("i", $locker);
        $stmtLock->execute();
        $rowLock = $stmtLock->get_result()->fetch_assoc();
        if (!$rowLock) {
            $conn->rollback();
            jexit(['error'=>'locker_unavailable','message'=>'This locker was just occupied by another user.'],409);
        }
        if ($rowLock['status'] !== 'available') {
            $conn->rollback();
            jexit(['error'=>'locker_unavailable','message'=>'This locker was just occupied by another user.'],409);
        }

        // With the lock held and global device mutex, ask ESP32 for a code
        $gen = $callEsp32Generate($locker-1);
        if (!$gen['ok']) {
            $conn->rollback();
            if ($gen['type'] === 'locker_unavailable') {
                jexit(['error'=>'locker_unavailable','message'=>'This locker was just occupied by another user.'],409);
            }
            jexit([
                'error'=>'esp32_unreachable',
                'http_code'=>$gen['http'],
                'raw'=>$gen['raw'],
                'curl_error'=>$gen['err']
            ],502);
        }

        $data = $gen['data'];
        $code = $data['code'];

        // Wallet debit (aggregates into single DEBIT row)
        $deb = wallet_debit($conn, $user_id, round($amount,2), $reference_no, "Reserve locker #$locker ($requested)");
        if (!$deb['ok']) {
            $conn->rollback();
            jexit(
                $deb['error'] === 'insufficient_funds'
                ? ['error'=>'insufficient_balance','message'=>'Your wallet balance is not enough to avail.','balance'=>$deb['balance'],'needed'=>$amount]
                : ['error'=>'wallet_debit_failed','message'=>$deb['message'] ?? null],
                $deb['error'] === 'insufficient_funds' ? 402 : 500
            );
        }

        // Update locker + reset reminder flags
        $stmt = $conn->prepare("UPDATE locker_qr SET code=?, user_id=?, status='occupied', expires_at=?, duration_minutes=?, notify30_sent=0, notify15_sent=0, notify10_sent=0, notify2_sent=0 WHERE locker_number=?");
        $stmt->bind_param("sisii",$code,$user_id,$expires_at,$duration_minutes,$locker);
        $stmt->execute();

        // Record payment
        $created_at = date('Y-m-d H:i:s');
        $method = 'Wallet';
        $dur_for_db = (string)$duration_minutes;
        $stmtPay = $conn->prepare("INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at) VALUES (?,?,?,?,?,?,?)");
        $stmtPay->bind_param("iisdsss", $user_id, $locker, $method, $amount, $reference_no, $dur_for_db, $created_at);
        $stmtPay->execute();

        $conn->commit();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        jexit(['error'=>'reserve_failed','message'=>$e->getMessage()],500);
    } finally {
        if ($gotGlobal) release_lock($conn, $globalLock);
    }

    // Save QR image + email
    $qr_filename=$qr_folder.'qr_'.$code.'.png';
    QRcode::png($code,$qr_filename,QR_ECLEVEL_L,6);
    $qr_url='/kandado/qr_image/qr_'.$code.'.png';

    $stmt_user = $conn->prepare("SELECT first_name,last_name,email FROM users WHERE id=? LIMIT 1");
    $stmt_user->bind_param("i",$user_id);
    $stmt_user->execute();
    $u=$stmt_user->get_result()->fetch_assoc();
    $user_fullname=$u['first_name'].' '.$u['last_name'];
    $user_email=$u['email'];
    email_qr($user_email, $user_fullname, $locker, $qr_filename, $code, $reserve_date, $reserve_time, $expires_at_formatted);

    $data['qr_url']=$qr_url;
    $data['expires_at']=$expires_at;
    $data['duration_minutes']=$duration_minutes;
    $data['balance']=get_wallet_balance($conn, $user_id);

    jexit($data);
}

/* ------------------- TAMPER / SECURITY ALERT (from ESP32) ------------------- */
if (isset($_GET['tamper'])) {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== $esp32_secret) jexit(['error' => 'unauthorized'], 401);

    $conn->query("SET time_zone = '+00:00'");

    $cause = strtolower(trim($_GET['cause'] ?? 'other'));
    $allowed = ['theft','door_slam','bump','tilt_only','other'];
    if (!in_array($cause, $allowed, true)) $cause = 'other';

    $locker_number = isset($_GET['locker']) ? (int)$_GET['locker'] : 0;
    if ($locker_number < 0 || $locker_number > $TOTAL_LOCKERS) $locker_number = 0;

    $details = $_GET['details'] ?? null;
    if ($details !== null) $details = mb_substr($details, 0, 255, 'UTF-8');

    $ts = isset($_GET['ts']) ? (int)$_GET['ts'] : 0;
    $hasTs = ($ts >= 946684800 && $ts <= 4102444800);

    $meta = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ];
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES);

    try {
        if ($hasTs) {
            $stmt = $conn->prepare("
                INSERT INTO security_alerts (locker_number, cause, details, meta, created_at)
                VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))
            ");
            $stmt->bind_param("isssi", $locker_number, $cause, $details, $metaJson, $ts);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO security_alerts (locker_number, cause, details, meta)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $locker_number, $cause, $details, $metaJson);
        }

        $stmt->execute();
        jexit(['success' => true]);
    } catch (mysqli_sql_exception $e) {
        jexit(['error' => 'tamper_insert_failed', 'message' => $e->getMessage()], 500);
    }
}

/* ------------------- FETCH LOCKERS ------------------- */
$lockerStatus=[];

if (isset($_GET['esp32']) && ($_GET['secret'] ?? '') === $esp32_secret) {
    $stmt = $conn->prepare("
        SELECT locker_number, code, status, expires_at, duration_minutes, item, maintenance
        FROM locker_qr
        ORDER BY locker_number ASC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $locker = (int)$row['locker_number'];
        $expires_iso = $row['expires_at'] ? date('c', strtotime($row['expires_at'])) : null;

        $lockerStatus[$locker] = [
            'code'             => $row['code'],
            'status'           => $row['status'],
            'expires_at'       => $expires_iso,
            'duration_minutes' => $row['duration_minutes'] !== null ? (float)$row['duration_minutes'] : null,
            'item'             => (int)$row['item'],
            'maintenance'      => (int)$row['maintenance'],
        ];
    }
} else {
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("
        SELECT locker_number, code, status, user_id, expires_at, duration_minutes, item, maintenance
        FROM locker_qr
        ORDER BY locker_number ASC
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $locker = (int)$row['locker_number'];
        $status = $row['status'];

        $label = "Unknown";
        if ($status === "available")    $label = "✅ Available";
        elseif ($status === "occupied") $label = "🔒 In Use";
        elseif ($status === "hold")     $label = "⚠ On Hold (Item Inside)";

        $expires_iso = $row['expires_at'] ? date('c', strtotime($row['expires_at'])) : null;

        $lockerStatus[$locker] = [
            'code'             => $row['code'],
            'status'           => $status,
            'status_label'     => $label,
            'user_id'          => $row['user_id'],
            'expires_at'       => $expires_iso,
            'duration_minutes' => $row['duration_minutes'] !== null ? (float)$row['duration_minutes'] : null,
            'item'             => (int)$row['item'],
            'maintenance'      => (int)$row['maintenance'],
        ];
    }
}

/* ---- STOP ALERTING bridge (add at TOP of /kandado/api/locker_api.php) ---- */
if (isset($_GET['stop_alert'])) {
  header('Content-Type: application/json; charset=utf-8');

  // Simple shared secret check (matches your front-end call)
  $SECRET = 'MYSECRET123';
  if (!isset($_GET['secret']) || $_GET['secret'] !== $SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']); exit;
  }

  // Targets to try: mDNS host then a plain hostname.
  // If your server can’t resolve .local, replace with your ESP32’s IP (e.g. http://192.168.1.50/stop_alert)
  $targets = [
    'http://locker-esp32.local/stop_alert',
    'http://locker-esp32/stop_alert',
  ];

  $ok = false; $lastUrl = null; $http = null; $err = null;

  foreach ($targets as $url) {
    $lastUrl = $url;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 2,
      CURLOPT_TIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    if ($resp !== false) {
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($http >= 200 && $http < 300) { $ok = true; curl_close($ch); break; }
    } else {
      $err = curl_error($ch);
    }
    curl_close($ch);
  }

  echo json_encode([
    'success' => $ok,
    'forwarded_to' => $lastUrl,
    'http' => $http,
    'error' => $ok ? null : ($err ?: 'no response')
  ]);
  exit;
}
/* ------------------- UPDATE ITEM (Slave 2) ------------------- */
if(isset($_GET['update_item'])){
    $locker_number=(int)$_GET['update_item'];
    $item=isset($_GET['item'])?(int)$_GET['item']:0;

    $stmt=$conn->prepare("UPDATE locker_qr SET item=? WHERE locker_number=?");
    $stmt->bind_param("ii",$item,$locker_number);
    $stmt->execute();

    jexit(['success'=>true,'locker'=>$locker_number,'item'=>$item]);
}

jexit($lockerStatus);
