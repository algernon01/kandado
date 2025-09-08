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
} catch (mysqli_sql_exception $e) {
    jexit(['error'=>'db_connect','message'=>$e->getMessage()],500);
}

/* ---------- Helpers ---------- */
function duration_minutes_map() {
    // Added 20min; kept original values
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
function now_ph() {
    date_default_timezone_set('Asia/Manila');
    return time();
}
function safe_duration_minutes($label) {
    $map = duration_minutes_map();
    return isset($map[$label]) ? $map[$label] : 60;
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

        // --- Save expired QR into history ---
        $stmt_history = $conn->prepare("INSERT INTO locker_history 
            (locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at) 
            VALUES (?,?,?,?,?,?,NOW())");
        $stmt_history->bind_param("issssi", 
            $locker_number, 
            $code, 
            $user_fullname, 
            $user_email, 
            $expires_at, 
            $duration_minutes
        );
        $stmt_history->execute();

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

            // --- SEND EMAIL TO USER (same design) ---
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

            // NEW: send expiry email when no item inside
            if ($user_email) {
                email_expired_released($user_email, $user_fullname, $locker_number);
            }
        }

        // --- Delete QR image file ---
        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) @unlink($qr_file);
    }
}

/* ------------------- NEW: Pre-expiry reminders (remaining-time tiers) ------------------- */
/*
Tiers (based on TOTAL scheduled duration for the current session):
- >= 60 minutes  -> send at 30m left AND 15m left (2 emails, with catch-up)
- 30–59 minutes  -> send at 15m left
- 20–29 minutes  -> send at 10m left
- < 20 minutes   -> no reminder

Notes:
- Flags (notify30_sent / notify15_sent / notify10_sent) prevent duplicates.
- On extend/generate/used/terminate/expire, flags are reset to 0 so reminders re-arm.
*/
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
            // 30-minute reminder window
            if ($remaining_min <= 30 && $remaining_min > 15 && !$sent30) {
                email_time_left($email, $name, $locker, 30, $expires_fmt);
                $sent30 = 1; $didUpdate = true;
            }
            // 15-minute reminder window (+ catch-up for missed 30)
            if ($remaining_min <= 15 && $remaining_min > 0 && !$sent15) {
                if (!$sent30) { email_time_left($email, $name, $locker, 30, $expires_fmt); $sent30 = 1; }
                email_time_left($email, $name, $locker, 15, $expires_fmt);
                $sent15 = 1; $didUpdate = true;
            }

        } elseif ($total_min >= 30) {
            // 30–59 minutes total: single reminder at 15 left
            if ($remaining_min <= 15 && $remaining_min > 0 && !$sent15) {
                email_time_left($email, $name, $locker, 15, $expires_fmt);
                $sent15 = 1; $didUpdate = true;
            }

        } elseif ($total_min >= 20) {
            // 20–29 minutes total: single reminder at 10 left
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

// Move expired QR codes on every request
moveExpiredQRs($conn, $qr_folder);

// NEW: send pre-expiry reminders on every request
checkAndSendReminders($conn);

/* ------------------- MARK QR AS USED (ESP32) ------------------- */
if(isset($_GET['used'])){
    $code = $_GET['used'];
    $secret = $_GET['secret'] ?? '';
    if($secret !== $esp32_secret) jexit(['error'=>'unauthorized'],401);
    if(empty($code)) jexit(['error'=>'no_code'],400);

    try{
        $conn->begin_transaction();
        // Fetch expires_at and duration_minutes
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

        // Insert history
        $stmt_hist = $conn->prepare("INSERT INTO locker_history (locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at) VALUES (?,?,?,?,?,?,NOW())");
        $stmt_hist->bind_param("isssis",$locker_number,$code,$user_fullname,$user_email,$expires_at,$duration_minutes);
        $stmt_hist->execute();

        // Release locker + reset reminder flags
        $stmt_update = $conn->prepare("UPDATE locker_qr SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL, notify30_sent=0, notify15_sent=0, notify10_sent=0 WHERE locker_number=?");
        $stmt_update->bind_param("i",$locker_number);
        $stmt_update->execute();

        $conn->commit();

        // Remove QR image
        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) @unlink($qr_file);

        jexit(['success'=>true]);
    }catch(mysqli_sql_exception $e){
        $conn->rollback();
        jexit(['error'=>'tx_failed','message'=>$e->getMessage()],500);
    }
}

/* ------------------- EXTEND LOCKER TIME (IDEMPOTENT) ------------------- */
if(isset($_GET['extend'])){
    if(!isset($_SESSION['user_id'])) jexit(['error'=>'not_logged_in'],401);

    $locker  = (int)$_GET['extend'];
    $user_id = (int)$_SESSION['user_id'];

    // duration
    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = safe_duration_minutes($requested);

    // Fetch current locker (must be occupied by this user)
    $stmt = $conn->prepare("SELECT code, expires_at FROM locker_qr WHERE locker_number=? AND user_id=? AND status='occupied' LIMIT 1");
    $stmt->bind_param("ii", $locker, $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if(!$existing){
        jexit(['error'=>'not_owner','message'=>'You cannot extend this locker or it is not active.'],403);
    }

    $currentExpiresTs = strtotime($existing['expires_at']);
    if ($currentExpiresTs === false) $currentExpiresTs = now_ph();

    // Payment/meta
    $method       = $_GET['method'] ?? 'GCash';
    $reference_no = $_GET['ref']    ?? uniqid("PAY"); // idempotency key
    $amount       = (float)($_GET['amount'] ?? 20);

    try {
        $conn->begin_transaction();

        // IDEMPOTENCY: if the same reference_no already exists for this user+locker, don't add again
        $stmt_chk = $conn->prepare("SELECT id FROM payments WHERE user_id=? AND locker_number=? AND reference_no=? LIMIT 1");
        $stmt_chk->bind_param("iis", $user_id, $locker, $reference_no);
        $stmt_chk->execute();
        $dup = $stmt_chk->get_result()->fetch_assoc();

        if ($dup) {
            // No-op: fetch fresh state and return as success
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
                'duration_minutes'=>0, // duplicate extend, no extra time
                'idempotent'=>true
            ]);
        }

        // Compute new expiration
        $newExpiresTs = $currentExpiresTs + (int)round($duration_minutes * 60);
        $expires_at   = date('Y-m-d H:i:s', $newExpiresTs);

        // Update locker + reset reminder flags (re-arm for new expiry)
        $stmt_upd = $conn->prepare("UPDATE locker_qr SET expires_at=?, duration_minutes=duration_minutes+?, notify30_sent=0, notify15_sent=0, notify10_sent=0 WHERE locker_number=?");
        $stmt_upd->bind_param("sii", $expires_at, $duration_minutes, $locker);
        $stmt_upd->execute();

        // Record payment (keep schema)
        $created_at = date('Y-m-d H:i:s');
        $stmt_pay = $conn->prepare("INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at) VALUES (?,?,?,?,?,?,?)");
        $dur_for_db = (string)$duration_minutes;
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
            'duration_minutes'=>$duration_minutes
        ]);
    } catch(mysqli_sql_exception $e){
        $conn->rollback();
        jexit(['error'=>'extend_failed','message'=>$e->getMessage()],500);
    }
}

/* ====================== TERMINATE LOCKER NOW ====================== */
if (isset($_GET['terminate'])) {
    if (!isset($_SESSION['user_id'])) jexit(['error' => 'not_logged_in'], 401);

    $locker      = (int)$_GET['terminate'];
    $user_id     = (int)$_SESSION['user_id'];
    $reason      = $_GET['reason'] ?? 'user_request';

    try {
        $conn->begin_transaction();

        // Verify ownership + active
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

        // Get user info (for history)
        $user_fullname = ''; $user_email = '';
        $stmt_user = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        if ($u = $stmt_user->get_result()->fetch_assoc()) {
            $user_fullname = $u['first_name'].' '.$u['last_name'];
            $user_email    = $u['email'];
        }

        // Log to history (used_at = NOW())
        $stmt_hist = $conn->prepare("
            INSERT INTO locker_history (locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at)
            VALUES (?,?,?,?,?,?,NOW())
        ");
        $stmt_hist->bind_param("isssis", $locker_number, $code, $user_fullname, $user_email, $expires_at, $duration_minutes);
        $stmt_hist->execute();

        // Update locker state + reset reminder flags
        if ($item === 1) {
            // Item left inside → put on hold
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, user_id=NULL, status='hold', expires_at=NULL, duration_minutes=NULL,
                    notify30_sent=0, notify15_sent=0, notify10_sent=0
                WHERE locker_number=?
            ");
            $next_status = 'hold';
        } else {
            // No item → release
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

        // Delete QR image
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

/* ------------------- GENERATE QR ------------------- */
if(isset($_GET['generate'])){
    if(!isset($_SESSION['user_id'])) jexit(['error'=>'not_logged_in'],401);

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

    $user_id = (int)$_SESSION['user_id'];

    // ======= PAYMENT SIMULATION =======
    $method = $_GET['method'] ?? 'GCash';
    $reference_no = $_GET['ref'] ?? uniqid("PAY");
    $amount = (float)($_GET['amount'] ?? 20);
    $durationLabel = $_GET['duration'] ?? '1hour';

    // Save payment record
    $created_at = date('Y-m-d H:i:s');  // PH time
    $stmt = $conn->prepare("INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("iisdsss", $user_id, $locker, $method, $amount, $reference_no, $durationLabel, $created_at);
    $stmt->execute();

    // Check if user already has a locker
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

    // Get requested duration in minutes
    $map = duration_minutes_map();
    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = isset($map[$requested]) ? $map[$requested] : 60;

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

    // Save QR image
    $qr_filename=$qr_folder.'qr_'.$data['code'].'.png';
    QRcode::png($data['code'],$qr_filename,QR_ECLEVEL_L,6);
    $data['qr_url']='/kandado/qr_image/qr_'.$data['code'].'.png';

    // Update locker in DB + reset reminder flags
    $stmt = $conn->prepare("UPDATE locker_qr SET code=?, user_id=?, status='occupied', expires_at=?, duration_minutes=?, notify30_sent=0, notify15_sent=0, notify10_sent=0 WHERE locker_number=?");
    $stmt->bind_param("sisii",$data['code'],$user_id,$expires_at,$duration_minutes,$locker);
    $stmt->execute();

    // ---------------- SEND EMAIL (same QR design, centralized) ----------------
    $stmt_user = $conn->prepare("SELECT first_name,last_name,email FROM users WHERE id=? LIMIT 1");
    $stmt_user->bind_param("i",$user_id);
    $stmt_user->execute();
    $u=$stmt_user->get_result()->fetch_assoc();
    $user_fullname=$u['first_name'].' '.$u['last_name'];
    $user_email=$u['email'];

    email_qr($user_email, $user_fullname, $locker, $qr_filename, $data['code'], $reserve_date, $reserve_time, $expires_at_formatted);

    $data['expires_at']=$expires_at;
    $data['duration_minutes']=$duration_minutes;

    jexit($data);
}

/* ------------------- FETCH LOCKERS ------------------- */
$lockerStatus=[];

if (isset($_GET['esp32']) && ($_GET['secret'] ?? '') === $esp32_secret) {
    // ESP32 view
    $stmt = $conn->prepare("
        SELECT locker_number, code, status, expires_at, duration_minutes, item, maintenance
        FROM locker_qr
        ORDER BY locker_number ASC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $locker = (int)$row['locker_number'];
        // ISO 8601 so JS Date(...) is reliable across browsers
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
    // User/dashboard view
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

        // Human-friendly label
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
?>


