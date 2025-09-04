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
// ------------------- HELPER: Move expired QR to history -------------------
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

        // --- Reset locker depending on item state ---
            // Refresh item state from DB just in case
        $stmt_item = $conn->prepare("SELECT item FROM locker_qr WHERE locker_number=? LIMIT 1");
        $stmt_item->bind_param("i", $locker_number);
        $stmt_item->execute();
        $itemRow = $stmt_item->get_result()->fetch_assoc();
        $item = (int)($itemRow['item'] ?? 0);

       if ($item === 1) {
    // Item left inside → HOLD
    $stmt_update = $conn->prepare("
        UPDATE locker_qr 
        SET code=NULL, user_id=NULL, status='hold', expires_at=NULL, duration_minutes=NULL 
        WHERE locker_number=?");
    $stmt_update->bind_param("i", $locker_number);
    $stmt_update->execute();

    // --- SEND EMAIL TO USER ---
    if($user_email){
        try{
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'lockerkandado01@gmail.com';
            $mail->Password = 'xgzhnjxyapnphnco';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('lockerkandado01@gmail.com','Kandado');
            $mail->addAddress($user_email, $user_fullname);

            $mail->isHTML(true);
            $mail->Subject = "Locker #{$locker_number} On Hold - Item Inside";
           $mail->Body = "
                        <html>
                        <head>
                        <style>
                            body {
                            font-family: 'Segoe UI', 'Roboto', sans-serif;
                            background-color: #f4f6f8;
                            color: #1f2937;
                            margin: 0;
                            padding: 0;
                            }
                            .email-container {
                            max-width: 600px;
                            margin: 40px auto;
                            background-color: #ffffff;
                            border-radius: 8px;
                            overflow: hidden;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                            }
                            .header {
                            background-color: #0f172a;
                            color: #ffffff;
                            padding: 20px;
                            text-align: center;
                            font-size: 24px;
                            font-weight: 700;
                            }
                            .content {
                            padding: 30px 20px;
                            line-height: 1.6;
                            font-size: 16px;
                            }
                            .content p {
                            margin: 15px 0;
                            }
                            .highlight {
                            color: #2563eb;
                            font-weight: 600;
                            }
                            .footer {
                            background-color: #f1f5f9;
                            color: #64748b;
                            font-size: 12px;
                            text-align: center;
                            padding: 15px;
                            }
                        </style>
                        </head>
                        <body>
                        <div class='email-container'>
                            <div class='header'>Locker Hold Notification</div>
                            <div class='content'>
                            <p>Hi <strong>{$user_fullname}</strong>,</p>
                            <p>Your locker <span class='highlight'>#{$locker_number}</span> is currently <strong>on hold</strong> because your QR code has expired, but there is still an item inside.</p>
                            <p>Please visit the admin to retrieve your belongings as soon as possible.</p>
                            <p>Thank you for trusting <strong>Kandado</strong> for your locker needs.</p>
                            </div>
                            <div class='footer'>
                            &copy; " . date('Y') . " Kandado. All rights reserved.
                            </div>
                        </div>
                        </body>
                        </html>
                        ";

                        $mail->send();
                    } catch(Exception $e){
                        error_log("Mailer Error (Hold Notification): ".$mail->ErrorInfo);
                    }
                }
            } else {
                // No item → AVAILABLE
                $stmt_update = $conn->prepare("
                    UPDATE locker_qr 
                    SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL 
                    WHERE locker_number=?");
                $stmt_update->bind_param("i", $locker_number);
                $stmt_update->execute();
            }


        $stmt_update->bind_param("i", $locker_number);
        $stmt_update->execute();

        // --- Delete QR image file ---
        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) unlink($qr_file);
    }
}


// Move expired QR codes on every request
moveExpiredQRs($conn, $qr_folder);

// ------------------- MARK QR AS USED (ESP32) -------------------
if(isset($_GET['used'])){
    $code = $_GET['used'];
    $secret = $_GET['secret'] ?? '';
    if($secret !== $esp32_secret) jexit(['error'=>'unauthorized'],401);
    if(empty($code)) jexit(['error'=>'no_code'],400);

    try{
        $conn->begin_transaction();
        // UPDATED: Fetch expires_at and duration_minutes
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

        // UPDATED: Insert expires_at and duration_minutes
        $stmt_hist = $conn->prepare("INSERT INTO locker_history (locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at) VALUES (?,?,?,?,?,?,NOW())");
        $stmt_hist->bind_param("isssis",$locker_number,$code,$user_fullname,$user_email,$expires_at,$duration_minutes);
        $stmt_hist->execute();

        $stmt_update = $conn->prepare("UPDATE locker_qr SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL WHERE locker_number=?");
        $stmt_update->bind_param("i",$locker_number);
        $stmt_update->execute();

        $conn->commit();

        $qr_file = $qr_folder.'qr_'.$code.'.png';
        if(file_exists($qr_file)) unlink($qr_file);

        jexit(['success'=>true]);
    }catch(mysqli_sql_exception $e){
        $conn->rollback();
        jexit(['error'=>'tx_failed','message'=>$e->getMessage()],500);
    }
}


// ------------------- EXTEND LOCKER TIME -------------------
if(isset($_GET['extend'])){
    if(!isset($_SESSION['user_id'])) jexit(['error'=>'not_logged_in'],401);

    $locker = (int)$_GET['extend'];
    $user_id = (int)$_SESSION['user_id'];

    // Requested duration options in minutes
    $durationOptions = [
        '30s'=>0.5,  // for testing
        '30min'=>30,
        '45min'=>45,
        '1hour'=>60,
        '3hours'=>180,
        '4hours'=>240,
        '5hours'=>300
    ];
    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = $durationOptions[$requested] ?? 60;

    // Fetch current locker info
    $stmt = $conn->prepare("SELECT code, expires_at FROM locker_qr WHERE locker_number=? AND user_id=? AND status='occupied' LIMIT 1");
    $stmt->bind_param("ii", $locker, $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if(!$existing){
        jexit(['error'=>'not_owner','message'=>'You cannot extend this locker or it is not active.'],403);
    }

    // Calculate new expiration
    $currentExpires = strtotime($existing['expires_at']);
    $newExpires = $currentExpires + $duration_minutes*60;
    $expires_at = date('Y-m-d H:i:s', $newExpires);

    // Begin transaction
    $conn->begin_transaction();
    try{
        // Update locker
        $stmt = $conn->prepare("UPDATE locker_qr SET expires_at=?, duration_minutes=duration_minutes+? WHERE locker_number=?");
        $stmt->bind_param("sii", $expires_at, $duration_minutes, $locker);
        $stmt->execute();

        // ------------------- UPDATE PAYMENT -------------------
        $method = $_GET['method'] ?? 'GCash';
        $reference_no = $_GET['ref'] ?? uniqid("PAY"); // fallback
        $amount = (float)($_GET['amount'] ?? 20);      // amount for this extension

        // Check latest payment for this user + locker
        $stmt_pay = $conn->prepare("SELECT id, amount, duration FROM payments WHERE user_id=? AND locker_number=? ORDER BY created_at DESC LIMIT 1");
        $stmt_pay->bind_param("ii", $user_id, $locker);
        $stmt_pay->execute();
        $existingPayment = $stmt_pay->get_result()->fetch_assoc();

        if($existingPayment){
            $new_duration = $existingPayment['duration'] + $duration_minutes;
            $stmt_update = $conn->prepare("UPDATE payments SET amount=amount+?, duration=?, reference_no=? WHERE id=?");
            $stmt_update->bind_param("disi", $amount, $new_duration, $reference_no, $existingPayment['id']);
            $stmt_update->execute();
        } else {
            $created_at = date('Y-m-d H:i:s');
            $stmt_insert = $conn->prepare("INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at) VALUES (?,?,?,?,?,?,?)");
            $stmt_insert->bind_param("iisdsss", $user_id, $locker, $method, $amount, $reference_no, $duration_minutes, $created_at);
            $stmt_insert->execute();
        }

        $conn->commit();

        $qr_url = '/kandado/qr_image/qr_'.$existing['code'].'.png';
        jexit([
            'success'=>true,
            'code'=>$existing['code'],
            'qr_url'=>$qr_url,
            'expires_at'=>$expires_at,
            'duration_minutes'=>$duration_minutes
        ]);
    } catch(mysqli_sql_exception $e){
        $conn->rollback();
        jexit(['error'=>'extend_failed','message'=>$e->getMessage()],500);
    }
}

/* ====================== NEW: TERMINATE LOCKER NOW ====================== */
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

        // Update locker state
        if ($item === 1) {
            // Item left inside → put on hold
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, user_id=NULL, status='hold', expires_at=NULL, duration_minutes=NULL
                WHERE locker_number=?
            ");
            $next_status = 'hold';
        } else {
            // No item → release
            $stmt_upd = $conn->prepare("
                UPDATE locker_qr
                SET code=NULL, user_id=NULL, status='available', expires_at=NULL, duration_minutes=NULL
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
            'status'  => $next_status, // 'available' or 'hold'
            'message' => $next_status === 'hold'
                ? 'Locker terminated. Locker is on hold because an item is still inside.'
                : 'Locker terminated and released.'
        ]);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        jexit(['error' => 'terminate_failed', 'message' => $e->getMessage()], 500);
    }
}
/* ==================== END NEW: TERMINATE LOCKER NOW ==================== */


// ------------------- GENERATE QR -------------------
if(isset($_GET['generate'])){
    if(!isset($_SESSION['user_id'])) jexit(['error'=>'not_logged_in'],401);

    $locker = (int)$_GET['generate'];
    if($locker<1||$locker>$TOTAL_LOCKERS) jexit(['error'=>'invalid_locker'],400);

    $user_id = (int)$_SESSION['user_id'];

    // ======= NEW: PAYMENT SIMULATION =======
    $method = $_GET['method'] ?? 'GCash';       // payment method
    $reference_no = $_GET['ref'] ?? uniqid("PAY"); // fake ref
    $amount = (float)($_GET['amount'] ?? 20);   // amount based on duration
    $durationLabel = $_GET['duration'] ?? '1hour';

    // Save payment record before generating QR
    $created_at = date('Y-m-d H:i:s');  // PH time
    $stmt = $conn->prepare("INSERT INTO payments (user_id, locker_number, method, amount, reference_no, duration, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("iisdsss", $user_id, $locker, $method, $amount, $reference_no, $durationLabel, $created_at);
    $stmt->execute();

    // ========================================

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


    // Get requested duration in minutes (default 60)
    $durationOptions = [
        '30s'=>0.5,  // For testing
        '30min'=>30,
        '45min'=>45,
        '1hour'=>60,
        '3hours'=>180,
        '4hours'=>240,
        '5hours'=>300
    ];
    $requested = $_GET['duration'] ?? '1hour';
    $duration_minutes = $durationOptions[$requested] ?? 60;
    date_default_timezone_set('Asia/Manila');
    $expires_at = date('Y-m-d H:i:s', time()+$duration_minutes*60);
    $reserve_date = date('F j, Y');           // e.g., August 26, 2025
    $reserve_time = date('h:i A');
    $expires_at_formatted = date('F j, Y h:i A', strtotime($expires_at)); // formatted expiration time

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

    // Update locker in DB
    $stmt = $conn->prepare("UPDATE locker_qr SET code=?, user_id=?, status='occupied', expires_at=?, duration_minutes=? WHERE locker_number=?");
    $stmt->bind_param("sisii",$data['code'],$user_id,$expires_at,$duration_minutes,$locker);
    $stmt->execute();

    // ---------------- SEND EMAIL ----------------
    $stmt_user = $conn->prepare("SELECT first_name,last_name,email FROM users WHERE id=? LIMIT 1");
    $stmt_user->bind_param("i",$user_id);
    $stmt_user->execute();
    $u=$stmt_user->get_result()->fetch_assoc();
    $user_fullname=$u['first_name'].' '.$u['last_name'];
    $user_email=$u['email'];

    try{
        $mail=new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host='smtp.gmail.com';
        $mail->SMTPAuth=true;
        $mail->Username='lockerkandado01@gmail.com';
        $mail->Password='xgzhnjxyapnphnco';
        $mail->SMTPSecure=PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port=465;

        $mail->setFrom('lockerkandado01@gmail.com','Kandado');
        $mail->addAddress($user_email,$user_fullname);

        $mail->isHTML(true);
        $mail->Subject="Your Locker QR Code - Locker #{$locker}";
        $mail->Body= "
<table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #f9f9f9; padding: 30px;'>
    <tr>
        <td align='center'>
        <table width='400' cellpadding='0' cellspacing='0' border='0' style='background-color: #ffffff; padding: 20px; border-radius: 12px; text-align: center;'>
            <tr>
            <td>
                <h2 style='color: #333; font-family: Arial, sans-serif;'>Hello {$user_fullname},</h2>
                <p style='font-size: 16px; color: #555; font-family: Arial, sans-serif;'>You have successfully reserved Locker #{$locker}.</p>
                
                <p style='font-size: 16px; font-weight: 600; color: #374151; font-family: Arial, sans-serif;'>Your QR Code:</p>
                
                <img src='cid:lockerqr' alt='QR Code' width='200' style='display: block; margin: 10px auto;' />
                
                <p style='font-size: 18px; font-weight: bold; color: #2563eb; font-family: Arial, sans-serif; margin: 10px 0 20px 0;'>{$data['code']}</p>
                
                <p style='font-size: 14px; color: #888; font-family: Arial, sans-serif; margin-top: 10px;'>Use this QR code to open your locker.</p>

                <p style='font-size: 14px; color: #555; font-family: Arial, sans-serif; margin-top: 20px;'>
                    Reserved on: <strong>{$reserve_date}</strong><br>
                    Reserved Time: <strong>{$reserve_time}</strong><br>
                    Expires at: <strong>{$expires_at_formatted}</strong>
                </p>
            </td>
            </tr>
        </table>
        </td>
    </tr>
</table>";
        $mail->addEmbeddedImage($qr_filename,'lockerqr');
        $mail->send();
    }catch(Exception $e){
        error_log("Mailer Error: ".$mail->ErrorInfo);
    }

    $data['expires_at']=$expires_at;
    $data['duration_minutes']=$duration_minutes;

    jexit($data);
}

// ------------------- FETCH LOCKERS -------------------
$lockerStatus=[];

if (isset($_GET['esp32']) && ($_GET['secret'] ?? '') === $esp32_secret) {
    // ESP32 view
    $stmt = $conn->prepare("
        SELECT locker_number, code, status, expires_at, duration_minutes, item  /* NEW: item */
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
        ];
    }
} else {
    // User/dashboard view
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("
        SELECT locker_number, code, status, user_id, expires_at, duration_minutes, item  /* NEW: item */
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
        if ($status === "available")   $label = "✅ Available";
        elseif ($status === "occupied") $label = "🔒 In Use";
        elseif ($status === "hold")     $label = "⚠ On Hold (Item Inside)";

        $expires_iso = $row['expires_at'] ? date('c', strtotime($row['expires_at'])) : null;

        $lockerStatus[$locker] = [
            'code'             => $row['code'],
            'status'           => $status,          // raw database value
            'status_label'     => $label,           // for dashboard display
            'user_id'          => $row['user_id'],
            'expires_at'       => $expires_iso,     // <-- 🔑 countdown source
            'duration_minutes' => $row['duration_minutes'] !== null ? (float)$row['duration_minutes'] : null,
            'item'             => (int)$row['item'], /* NEW: include item */
        ];
    }
}


// ------------------- UPDATE ITEM (Slave 2) -------------------
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
