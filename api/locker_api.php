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

// Include shared email helpers (centralized mail)
require_once $_SERVER['DOCUMENT_ROOT'] . '/kandado/lib/email_lib.php';

// Include phpqrcode
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
    // Strongly recommended: fail on zero dates instead of silently writing them
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY'");
    // Philippines time
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    jexit(['error'=>'db_connect','message'=>$e->getMessage()],500);
}

/* ---------- Helpers ---------- */
function duration_minutes_map() {
    return [
        '30s'    => 0.5,
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
    // Keep in sync with /assets/js/user_dashboard.js -> prices
    return [
        '30s'    => 0.50,
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

/* ---------- Wallet helpers ---------- */
function get_wallet_balance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (float)$row['balance'] : 0.0;
}

/**
 * Credit wallet (TOPUP). If (user_id, reference_no) already exists,
 * MERGE: amount += new amount, method/notes updated. Single row, growing amount.
 */
function wallet_credit($conn, $user_id, $amount, $method, $reference_no, $notes = null, $meta = null) {
    if ($amount <= 0) return ['ok'=>false, 'error'=>'invalid_amount'];

    try {
        $conn->begin_transaction();

        // Ensure wallet row exists & lock it
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

        $delta = round($amount, 2);
        $newBal = round($current + $delta, 2);
        $note   = $notes ?: ('Top-up via ' . $method);
        $metaJson = $meta ? json_encode($meta) : null;

        // Check if same (user_id, reference_no) exists -> MERGE
        $tx_id = null;
        if ($reference_no) {
            $stmtC = $conn->prepare("SELECT id FROM wallet_transactions WHERE user_id=? AND reference_no=? LIMIT 1");
            $stmtC->bind_param("is", $user_id, $reference_no);
            $stmtC->execute();
            $existing = $stmtC->get_result()->fetch_assoc();

            if ($existing) {
                $tx_id = (int)$existing['id'];
                $stmtU = $conn->prepare("
                    UPDATE wallet_transactions
                       SET method=?, amount=amount+?, notes=?
                     WHERE id=?");
                $stmtU->bind_param("sdsi", $method, $delta, $note, $tx_id);
                $stmtU->execute();
            }
        }

        if (!$tx_id) {
            $type = 'topup';
            $stmtTx = $conn->prepare("
                INSERT INTO wallet_transactions
                    (user_id, type, method, amount, reference_no, notes, meta)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $type = 'topup';
            $stmtTx->bind_param("issdsss", $user_id, $type, $method, $delta, $reference_no, $note, $metaJson);
            $stmtTx->execute();
            $tx_id = $conn->insert_id;
        }

        // Update wallet balance
        $stmtUp = $conn->prepare("UPDATE user_wallets SET balance=? WHERE user_id=?");
        $stmtUp->bind_param("di", $newBal, $user_id);
        $stmtUp->execute();

        $conn->commit();
        return ['ok'=>true, 'balance'=>$newBal, 'tx_id'=>$tx_id];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();

        // If unique constraint exists and duplicate happens, respond idempotently
        if ($e->getCode() === 1062 && $reference_no) {
            $bal = get_wallet_balance($conn, $user_id);
            return ['ok'=>true, 'idempotent'=>true, 'balance'=>$bal];
        }
        return ['ok'=>false, 'error'=>'wallet_credit_failed', 'message'=>$e->getMessage()];
    }
}

/**
 * Debit wallet (RESERVATION/EXTEND). Idempotency by reference_no (if provided).
 */
function wallet_debit($conn, $user_id, $amount, $reference_no, $notes = null, $meta = null) {
    if ($amount <= 0) return ['ok'=>false, 'error'=>'invalid_amount'];

    try {
        $conn->begin_transaction();

        // If same ref exists -> idempotent success (no double debit)
        if ($reference_no) {
            $stmt = $conn->prepare("SELECT id FROM wallet_transactions WHERE user_id=? AND reference_no=? LIMIT 1");
            $stmt->bind_param("is", $user_id, $reference_no);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $bal = get_wallet_balance($conn, $user_id);
                $conn->commit();
                return ['ok'=>true, 'idempotent'=>true, 'balance'=>$bal];
            }
        }

        // Lock row
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

        // Insert ledger
        $type = 'debit';
        $method = 'Wallet';
        $metaJson = $meta ? json_encode($meta) : null;
        $stmtTx = $conn->prepare("
            INSERT INTO wallet_transactions (user_id, type, method, amount, reference_no, notes, meta)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtTx->bind_param("issdsss", $user_id, $type, $method, $amount, $reference_no, $notes, $metaJson);
        $stmtTx->execute();

        // Update wallet
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
    // i s s s s i  -> expires_at is correctly bound as string (DATETIME)
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

        // --- Get user info ---
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

        // --- Save expired QR into history (centralized helper) ---
        insert_history($conn, $locker_number, $code, $user_fullname, $user_email, $expires_at, (int)$duration_minutes);

        // Refresh item state (defensive)
        $stmt_item = $conn->prepare("SELECT item FROM locker_qr WHERE locker_number=? LIMIT 1");
        $stmt_item->bind_param("i", $locker_number);
        $stmt_item->execute();
        $itemRow = $stmt_item->get_result()->fetch_assoc();
        $item = (int)($itemRow['item'] ?? 0);

        if ($item === 1) {
            // Item left inside → HOLD
            $stmt_update = $conn->prepare("
                UPDATE locker_qr 
                SET code=NULL, user_id=NULL, status='hold', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0
                WHERE locker_number=?");
            $stmt_update->bind_param("i", $locker_number);
            $stmt_update->execute();

            if($user_email){
                email_on_hold($user_email, $user_fullname, $locker_number);
            }
        } else {
            // No item → AVAILABLE
            $stmt_update = $conn->prepare("
                UPDATE locker_qr 
                SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0
                WHERE locker_number=?");
            $stmt_update->bind_param("i", $locker_number);
            $stmt_update->execute();

            if ($user_email) {
                email_expired_released($user_email, $user_fullname, $locker_number);
            }
        }

        // --- Delete QR image file ---
        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) @unlink($qr_file);
    }
}

/* ------------------- NEW: Pre-expiry reminders ------------------- */
function checkAndSendReminders($conn){
    date_default_timezone_set('Asia/Manila');
    $now = time();

    $sql = "
      SELECT l.locker_number, l.code, l.user_id, l.expires_at, l.duration_minutes,
             l.notify30_sent, l.notify15_sent, l.notify10_sent,
             u.first_name, u.last_name, u.email
      FROM locker_qr l
      LEFT JOIN users u ON u.id = l.user_id
      WHERE l.status='occupied' AND l.expires_at IS NOT NULL AND l.expires_at > NOW()
    ";
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()){
        $locker        = (int)$row['locker_number'];
        $expires_ts    = strtotime($row['expires_at']);
        if ($expires_ts === false) continue;

        $remaining_sec = $expires_ts - $now;
        if ($remaining_sec <= 0) continue;

        $remaining_min = floor($remaining_sec / 60);
        $total_min     = (int)$row['duration_minutes'];
        $sent30        = (int)$row['notify30_sent'];
        $sent15        = (int)$row['notify15_sent'];
        $sent10        = (int)$row['notify10_sent'];
        $name          = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
        $email         = $row['email'] ?? '';
        $expires_fmt   = date('F j, Y h:i A', $expires_ts);

        $didUpdate = false;

        if ($total_min >= 60) {
            if ($remaining_min <= 30 && $remaining_min > 15 && !$sent30) {
                email_time_left($email, $name, $locker, 30, $expires_fmt);
                $sent30 = 1; $didUpdate = true;
            }
            if ($remaining_min <= 15 && $remaining_min > 0 && !$sent15) {
                if (!$sent30) { email_time_left($email, $name, $locker, 30, $expires_fmt); $sent30 = 1; }
                email_time_left($email, $name, $locker, 15, $expires_fmt);
                $sent15 = 1; $didUpdate = true;
            }
        } elseif ($total_min >= 30) {
            if ($remaining_min <= 15 && $remaining_min > 0 && !$sent15) {
                email_time_left($email, $name, $locker, 15, $expires_fmt);
                $sent15 = 1; $didUpdate = true;
            }
        } elseif ($total_min >= 20) {
            if ($remaining_min <= 10 && $remaining_min > 0 && !$sent10) {
                email_time_left($email, $name, $locker, 10, $expires_fmt);
                $sent10 = 1; $didUpdate = true;
            }
        }

        if ($didUpdate) {
            $stmt = $conn->prepare("UPDATE locker_qr SET notify30_sent=?, notify15_sent=?, notify10_sent=? WHERE locker_number=?");
            $stmt->bind_param("iiii", $sent30, $sent15, $sent10, $locker);
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
    $reference_no = $_GET['ref'] ?? null; // PSP or client ref (stable to merge)
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

        // Correctly write to history
        insert_history($conn, $locker_number, $code, $user_fullname, $user_email, $expires_at, (int)$duration_minutes);

        $stmt_update = $conn->prepare("UPDATE locker_qr SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL, notify30_sent=0, notify15_sent=0, notify10_sent=0 WHERE locker_number=?");
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

        // Wallet debit
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

        $stmt_upd = $conn->prepare("UPDATE locker_qr SET expires_at=?, duration_minutes=duration_minutes+?, notify30_sent=0, notify15_sent=0, notify10_sent=0 WHERE locker_number=?");
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

        // History (correct types)
        insert_history($conn, $locker_number, $code, $user_fullname, $user_email, $expires_at, (int)$duration_minutes);

        if ($item === 1) {
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, user_id=NULL, status='hold', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0
                WHERE locker_number=?
            ");
            $next_status = 'hold';
        } else {
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0
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
if(isset($_GET['generate'])){
    $user_id = require_login();

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

    // Prevent multiple active lockers for same user
    $stmt = $conn->prepare("SELECT locker_number, code FROM locker_qr WHERE user_id=? AND status='occupied' LIMIT 1");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $existing=$stmt->get_result()->fetch_assoc();
    if($existing){
        $existing_code=$existing['code'];
        $existing_file='/kandado/qr_image/qr_'.$existing_code.'.png';
        jexit([
            'error'=>'already_has_locker',
            'message'=>'You already have a locker. Please use it before generating a new one.',
            'code'=>$existing_code,
            'qr_url'=>$existing_file
        ],400);
    }

    // Get requested duration + price from server map
    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = safe_duration_minutes($requested);
    $amount = safe_price($requested);

    date_default_timezone_set('Asia/Manila');
    $expires_at = date('Y-m-d H:i:s', time()+$duration_minutes*60);
    $reserve_date = date('F j, Y');
    $reserve_time = date('h:i A');
    $expires_at_formatted = date('F j, Y h:i A', strtotime($expires_at));

    // Call ESP32
    $url = "http://{$esp32_host}/generate?locker=".($locker-1);
    $ch = curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_TIMEOUT,3);
    $response=curl_exec($ch);
    $curl_err=curl_error($ch);
    $http_code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data=json_decode($response,true);
    if(!$data || !isset($data['code'])){
        jexit([
            'error'=>'esp32_invalid_response',
            'http_code'=>$http_code,
            'raw'=>$response,
            'curl_error'=>$curl_err
        ],502);
    }

    $code = $data['code'];
    $reference_no = $_GET['ref'] ?? uniqid('WAL'); // stable idempotency key

    // Reserve + wallet debit in one transaction
    try {
        $conn->begin_transaction();

        // Still available?
        $stmtLock = $conn->prepare("SELECT status FROM locker_qr WHERE locker_number=? FOR UPDATE");
        $stmtLock->bind_param("i", $locker);
        $stmtLock->execute();
        $rowLock = $stmtLock->get_result()->fetch_assoc();
        if (!$rowLock || $rowLock['status'] !== 'available') {
            $conn->rollback();
            jexit(['error'=>'locker_unavailable'],409);
        }

        // Wallet debit
        $deb = wallet_debit($conn, $user_id, round($amount,2), $reference_no, "Reserve locker #$locker ($requested)");
        if (!$deb['ok']) {
            $conn->rollback();
            if ($deb['error'] === 'insufficient_funds') {
                jexit([
                    'error'=>'insufficient_balance',
                    'message'=>'Your wallet balance is not enough to reserve.',
                    'balance'=>$deb['balance'],
                    'needed'=>$amount
                ], 402);
            }
            jexit(['error'=>'wallet_debit_failed','message'=>$deb['message'] ?? null],500);
        }

        // Update locker + reset reminder flags
        $stmt = $conn->prepare("UPDATE locker_qr SET code=?, user_id=?, status='occupied', expires_at=?, duration_minutes=?, notify30_sent=0, notify15_sent=0, notify10_sent=0 WHERE locker_number=?");
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
    }

    // Save QR image + email (unchanged)
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
