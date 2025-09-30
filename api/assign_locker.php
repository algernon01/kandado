<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require '../vendor/autoload.php';

// --- CONFIG ---
$host = 'localhost';
$dbname = 'kandado';
$user  = 'root';
$pass  = '';

// Use centralized email helper (for SMTP config, from-address, etc.)
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

// ------------------- DURATION MAP (match the main API) -------------------
$durationOptions = [
    '5min'    => 5,
    '20min'   => 20,
    '30min'   => 30,
    '1hour'   => 60,
    '2hours'  => 120,
    '4hours'  => 240,
    '8hours'  => 480,
    '12hours' => 720,
    '24hours' => 1440,
    '2days'   => 2880,
    '7days'   => 10080,
];
$duration_minutes = isset($durationOptions[$durationKey]) ? (int)$durationOptions[$durationKey] : 60;

// ------------------- TIME CALCULATION -------------------
date_default_timezone_set('Asia/Manila');
$expires_at = date('Y-m-d H:i:s', time() + $duration_minutes*60);

$reserve_date         = date('F j, Y');           // e.g., September 29, 2025
$reserve_time         = date('h:i A');
$expires_at_formatted = date('F j, Y h:i A', strtotime($expires_at));

// ------------------- GENERATE RANDOM 6-DIGIT CODE -------------------
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// ------------------- SAVE QR IMAGE -------------------
$qr_filename = $qr_folder.'qr_'.$code.'.png';
QRcode::png($code, $qr_filename, QR_ECLEVEL_L, 6);
$qr_url = '/kandado/qr_image/qr_'.$code.'.png';

// ------------------- UPDATE LOCKER -------------------
try {
    $stmt = $conn->prepare("
        UPDATE locker_qr
           SET user_id=?, code=?, status='occupied', expires_at=?, duration_minutes=?
         WHERE locker_number=? AND status='available'
    ");
    $stmt->bind_param("issii", $user_id, $code, $expires_at, $duration_minutes, $locker_number);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Fetch user info for email
        $stmt_user = $conn->prepare("SELECT first_name,last_name,email FROM users WHERE id=? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $u = $stmt_user->get_result()->fetch_assoc();

        $user_fullname = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? ''));
        $user_email    = $u['email'] ?? '';

        // ------------------- SEND CUSTOM ADMIN-ASSIGN EMAIL -------------------
        $sent = false;
        if ($user_email) {
            // fallbacks if your helper constants aren’t defined
            $brandColor      = defined('KANDADO_BRAND_COLOR')    ? KANDADO_BRAND_COLOR    : '#2563eb';
            $loginUrl        = defined('KANDADO_LOGIN_URL')      ? KANDADO_LOGIN_URL      : '#';
            $loginUrlLocal   = defined('KANDADO_LOGIN_URL_LOCAL')? KANDADO_LOGIN_URL_LOCAL: '#';

            $displayName = $user_fullname ?: $user_email;
            $safeName    = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
            $cid         = 'lockerqr';

            // Use your centralized mailer (handles SMTP, from, etc.)
            $mail = kandado_mailer();
            $mail->addAddress($user_email, $displayName);
            $mail->Subject = "Locker Assigned by Admin - Locker #{$locker_number}";
            $mail->addEmbeddedImage($qr_filename, $cid);

            // Message: explicitly say an admin assigned the locker
            $mail->Body = "
<!doctype html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <title>Locker Assigned</title>
  <style>
    @media only screen and (max-width: 600px) {
      .container { width: 100% !important; padding: 16px !important; }
      .btn-table { width: 100% !important; }
      .btn-link  { display: block !important; width: auto !important; text-align: center !important; margin: 0 auto !important; }
      .btn-note  { text-align: center !important; }
    }
    a { text-decoration: none; }
  </style>
</head>
<body style='margin:0; padding:0; background:#f3f4f6;'>
  <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background:#f3f4f6;'>
    <tr>
      <td align='center' style='padding:24px;'>
        <table role='presentation' class='container' cellspacing='0' cellpadding='0' border='0' width='600' style='width:600px; max-width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.08);'>

          <!-- Brand header -->
          <tr>
            <td align='center' style='background:{$brandColor}; padding:20px 24px; text-align:center;'>
              <h1 style='margin:0; font-family:Arial,Helvetica,sans-serif; font-size:20px; color:#ffffff;'>Kandado</h1>
            </td>
          </tr>

          <!-- Greeting + copy -->
          <tr>
            <td style='padding:28px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif; color:#111827;'>
              <h2 style='margin:0 0 8px 0; font-size:22px; line-height:1.3;'>Hello {$safeName},</h2>
              <p style='margin:0; font-size:16px; line-height:1.6; color:#374151;'>
                An administrator has <strong>assigned</strong> a locker to you.
                You can now use <strong>Locker #{$locker_number}</strong>.
              </p>
            </td>
          </tr>

          <!-- QR + code -->
          <tr>
            <td style='padding:12px 24px 0 24px; font-family:Arial,Helvetica,sans-serif; text-align:center;'>
              <p style='margin:0 0 10px; font-size:15px; font-weight:600; color:#374151;'>Your QR Code:</p>
              <img src='cid:{$cid}' alt='QR Code' width='200' style='display:block; margin:10px auto;' />
              <p style='font-size:18px; font-weight:bold; color:#2563eb; margin:10px 0 0 0;'>{$code}</p>
            </td>
          </tr>

          <!-- Reservation details -->
          <tr>
            <td style='padding:16px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:14px; color:#555; line-height:1.6;'>
                Assigned on: <strong>{$reserve_date}</strong><br>
                Time Assigned: <strong>{$reserve_time}</strong><br>
                Expires at: <strong>{$expires_at_formatted}</strong>
              </p>
            </td>
          </tr>

          <!-- CTA (optional) -->
          <tr>
            <td style='padding:12px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif;' align='center'>
              <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' class='btn-table' style='margin:0 auto;'>
                <tr>
                  <td style='background:{$brandColor}; border-radius:8px; mso-padding-alt:14px 22px;'>
                    <a href='{$loginUrl}' class='btn-link'
                       style='display:inline-block; padding:14px 22px; font-size:16px; font-weight:bold;
                              font-family:Arial,Helvetica,sans-serif; color:#ffffff; text-decoration:none; border-radius:8px;'
                       target='_blank' rel='noopener'>
                      View Locker Details
                    </a>
                  </td>
                </tr>
              </table>
              <p class='btn-note' style='margin:10px 0 0 0; font-size:12px; line-height:1.6; color:#6b7280; font-family:Arial,Helvetica,sans-serif;'>
                If you're on the Kandado Wi-Fi, you can also use the local link below.
              </p>
            </td>
          </tr>

          <!-- Fallback links -->
          <tr>
            <td style='padding:8px 24px 20px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:14px; line-height:1.6; color:#6b7280;'>
                Cloud link:
                <br>
                <a href='{$loginUrl}' style='color:{$brandColor}; word-break:break-all;' target='_blank' rel='noopener'>{$loginUrl}</a>
              </p>
              <p style='margin:8px 0 0 0; font-size:13px; line-height:1.6; color:#6b7280;'>
                Local network link:
                <br>
                <a href='{$loginUrlLocal}' style='color:{$brandColor}; word-break:break-all;' target='_blank' rel='noopener'>{$loginUrlLocal}</a>
              </p>
              <p style='margin:8px 0 0 0; font-size:13px; line-height:1.6; color:#6b7280;'>
                Keep your QR code confidential.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style='background:#f9fafb; padding:16px 24px; text-align:center; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:12px; color:#9ca3af;'>Kandado • This is an automated message.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
";
            try {
                $sent = $mail->send();
            } catch (\Throwable $e) {
                error_log("Admin-assign mail error: " . $e->getMessage());
            }
        }

        jexit([
            'success'          => true,
            'message'          => "Locker #$locker_number assigned".($user_email ? " and email sent to {$user_email}." : "."),
            'code'             => $code,
            'qr_url'           => $qr_url,
            'expires_at'       => $expires_at,
            'duration_minutes' => $duration_minutes
        ]);
    } else {
        jexit(['success'=>false,'message'=>"Failed to assign. Locker may already be in use."]);
    }
} catch (mysqli_sql_exception $e) {
    jexit(['error'=>'db_error','message'=>$e->getMessage()],500);
}
?>
