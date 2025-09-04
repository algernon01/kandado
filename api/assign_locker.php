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

// ------------------- AUTH CHECK -------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    jexit(['error'=>'unauthorized'],403);
}

// ------------------- INPUTS -------------------
$user_id       = $_POST['user_id'] ?? null;
$locker_number = $_POST['locker_number'] ?? null;
$durationKey   = $_POST['duration'] ?? '1hour';

if(!$user_id || !$locker_number){
    jexit(['error'=>'missing_fields','message'=>'User, locker, and duration are required.'],400);
}

// ------------------- DURATION MAP -------------------
$durationOptions = [
    '30s'=>0.5,
    '30min'=>30,
    '45min'=>45,
    '1hour'=>60,
    '3hours'=>180,
    '4hours'=>240,
    '5hours'=>300
];

$duration_minutes = $durationOptions[$durationKey] ?? 60;

// ------------------- TIME CALCULATION -------------------
date_default_timezone_set('Asia/Manila');
$expires_at = date('Y-m-d H:i:s', time() + $duration_minutes*60);

$reserve_date = date('F j, Y');           // e.g., August 31, 2025
$reserve_time = date('h:i A');
$expires_at_formatted = date('F j, Y h:i A', strtotime($expires_at));

// ------------------- GENERATE RANDOM 6-DIGIT CODE -------------------
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// ------------------- SAVE QR IMAGE -------------------
$qr_filename = $qr_folder.'qr_'.$code.'.png';
QRcode::png($code, $qr_filename, QR_ECLEVEL_L, 6);
$qr_url = '/kandado/qr_image/qr_'.$code.'.png';

// ------------------- UPDATE LOCKER -------------------
try {
    $stmt = $conn->prepare("UPDATE locker_qr 
        SET user_id=?, code=?, status='occupied', expires_at=?, duration_minutes=? 
        WHERE locker_number=? AND status='available'");
    $stmt->bind_param("issii", $user_id, $code, $expires_at, $duration_minutes, $locker_number);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {

        // Fetch user info for email
        $stmt_user = $conn->prepare("SELECT first_name,last_name,email FROM users WHERE id=? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $u=$stmt_user->get_result()->fetch_assoc();
        $user_fullname=$u['first_name'].' '.$u['last_name'];
        $user_email=$u['email'];

        // ------------------- SEND EMAIL -------------------
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
            $mail->Subject="Locker Assigned - Locker #{$locker_number}";
            $mail->Body= "
<table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #f9f9f9; padding: 30px;'>
    <tr>
        <td align='center'>
        <table width='400' cellpadding='0' cellspacing='0' border='0' style='background-color: #ffffff; padding: 20px; border-radius: 12px; text-align: center;'>
            <tr>
            <td>
                <h2 style='color: #333; font-family: Arial, sans-serif;'>Hello {$user_fullname},</h2>
                <p style='font-size: 16px; color: #555; font-family: Arial, sans-serif;'>An administrator has assigned you Locker #{$locker_number}.</p>
                
                <p style='font-size: 16px; font-weight: 600; color: #374151; font-family: Arial, sans-serif;'>Your QR Code:</p>
                
                <img src='cid:lockerqr' alt='QR Code' width='200' style='display: block; margin: 10px auto;' />
                
                <p style='font-size: 18px; font-weight: bold; color: #2563eb; font-family: Arial, sans-serif; margin: 10px 0 20px 0;'>{$code}</p>
                
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

        jexit([
            'success'=>true,
            'message'=>"Locker #$locker_number assigned successfully and email sent to {$user_email}.",
            'code'=>$code,
            'qr_url'=>$qr_url,
            'expires_at'=>$expires_at,
            'duration_minutes'=>$duration_minutes
        ]);
    } else {
        jexit(['success'=>false,'message'=>"Failed to assign. Locker may already be in use."]);
    }
} catch (mysqli_sql_exception $e) {
    jexit(['error'=>'db_error','message'=>$e->getMessage()],500);
}
?>
