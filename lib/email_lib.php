<?php
// /kandado/lib/email_lib.php
// Centralized email helpers (keeps original designs; adds login link to locker email)

require_once $_SERVER['DOCUMENT_ROOT'] . '/kandado/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Public login URL used in emails (requested) */
const KANDADO_LOGIN_URL = 'http://longhorn-settling-precisely.ngrok-free.app/kandado/public/login.php';

/** Local network login URL (added for QR email fallback) */
const KANDADO_LOGIN_URL_LOCAL = 'http://192.168.100.15/kandado/public/login.php';

/** Brand color */
const KANDADO_BRAND_COLOR = '#3353bb';

function kandado_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet   = 'UTF-8';
    $mail->isSMTP();

    // Prefer env vars if present; fall back to your existing settings
    $mail->Host       = getenv('KANDADO_SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('KANDADO_SMTP_USER') ?: 'lockerkandado01@gmail.com';
    $mail->Password   = getenv('KANDADO_SMTP_PASS') ?: 'pzyuqdxojumykxgs'; // consider env var!
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int) (getenv('KANDADO_SMTP_PORT') ?: 465);

    $mail->setFrom(getenv('KANDADO_FROM_EMAIL') ?: 'lockerkandado01@gmail.com', getenv('KANDADO_FROM_NAME') ?: 'Kandado');
    $mail->isHTML(true);
    return $mail;
}

/**
 * Account verification email
 * $verificationLink is full URL including token (already rawurlencoded upstream)
 */
function email_verify_account(string $to, string $firstName, string $lastName, string $verificationLink): bool {
    if (!$to || !$verificationLink) return false;
    try {
        $mail = kandado_mailer();
        $fullName = trim($firstName.' '.$lastName);
        // Use unescaped name for headers, escaped for HTML
        $displayName = $fullName ?: $to;
        $safeFullName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $brandColor = KANDADO_BRAND_COLOR;
        $year = date('Y');

        $mail->addAddress($to, $displayName);
        $mail->Subject = 'Verify your Kandado account (expires in 1 hour)';

        // Polished, responsive email
        $mail->Body = "
<!doctype html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <title>Kandado Email Verification</title>
  <style>
    @media only screen and (max-width: 600px) {
      .container { width: 100% !important; padding: 16px !important; }
      .btn-table { width: 100% !important; }
      .btn-link  { display: block !important; width: auto !important; text-align: center !important; margin: 0 auto !important; }
    }
    a { text-decoration: none; }
  </style>
</head>
<body style='margin:0; padding:0; background:#f3f4f6;'>
  <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background:#f3f4f6;'>
    <tr>
      <td align='center' style='padding:24px;'>
        <table role='presentation' class='container' cellspacing='0' cellpadding='0' border='0' width='600' style='width:600px; max-width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.08);'>
          <tr>
            <td align='center' style='background:{$brandColor}; padding:20px 24px; text-align:center;'>
              <h1 style='margin:0; font-family:Arial,Helvetica,sans-serif; font-size:20px; color:#ffffff;'>Kandado</h1>
            </td>
          </tr>
          <tr>
            <td style='padding:28px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif; color:#111827;'>
              <h2 style='margin:0 0 8px 0; font-size:22px; line-height:1.3;'>Welcome, {$safeFullName}</h2>
              <p style='margin:0; font-size:16px; line-height:1.6; color:#374151;'>
                Thanks for registering with <strong>Kandado</strong>! Please confirm your email address to activate your account.
              </p>
            </td>
          </tr>
          <tr>
            <td style='padding:16px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:15px; line-height:1.6; color:#6b7280;'>
                <strong>This verification link expires in 1 hour.</strong> For your security, you’ll need to request a new link after that.
              </p>
            </td>
          </tr>
          <tr>
            <td style='padding:20px 24px 28px 24px; font-family:Arial,Helvetica,sans-serif;' align='center'>

              <!--[if mso]>
                <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml'
                  href='{$verificationLink}'
                  style='height:44px;v-text-anchor:middle;width:220px;'
                  arcsize='12%' stroke='f' fillcolor='{$brandColor}'>
                  <w:anchorlock/>
                  <center style='color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;'>
                    Verify Email
                  </center>
                </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-- -->
              <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' class='btn-table' style='margin:0 auto;'>
                <tr>
                  <td style='background:{$brandColor}; border-radius:8px; mso-padding-alt:14px 22px;'>
                    <a href='{$verificationLink}' class='btn-link'
                       style='display:inline-block; padding:14px 22px; font-size:16px; font-weight:bold;
                              font-family:Arial,Helvetica,sans-serif; color:#ffffff; text-decoration:none; border-radius:8px;'
                       target='_blank' rel='noopener'>
                      Verify Email
                    </a>
                  </td>
                </tr>
              </table>
              <!--<![endif]-->

            </td>
          </tr>
          <tr>
            <td style='padding:0 24px 20px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:14px; line-height:1.6; color:#6b7280; word-break:break-word;'>
                If the button doesn’t work, open this link in your browser:
                <br>
                <a href='{$verificationLink}' style='color:{$brandColor}; word-break:break-all;' target='_blank' rel='noopener'>{$verificationLink}</a>
              </p>
            </td>
          </tr>
          <tr>
            <td style='padding:4px 24px 28px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:12px; line-height:1.6; color:#9ca3af;'>
                Didn’t create this account? You can safely ignore this email — the link will become invalid after 1 hour.
              </p>
            </td>
          </tr>
          <tr>
            <td style='background:#f9fafb; padding:16px 24px; text-align:center; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:12px; color:#9ca3af;'>&copy; {$year} Kandado. All rights reserved.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";
        // Plain-text fallback
        $mail->AltBody = "Welcome to Kandado!\n\n"
            ."Verify your email (link expires in 1 hour):\n"
            .$verificationLink."\n\n"
            ."If the button doesn’t work, open this link in your browser (copy & paste if needed).\n\n"
            ."If you didn’t register, you can ignore this email.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error (Account Verification): ".$e->getMessage());
        return false;
    }
}

/**
 * Password reset email
 */
function email_reset_password(string $to, string $firstName, string $resetLink): bool {
    if (!$to || !$resetLink) return false;
    try {
        $mail = kandado_mailer();
        $displayName = $firstName ?: $to;
        $safeFirst = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $brandColor = KANDADO_BRAND_COLOR;

        $mail->addAddress($to, $displayName);
        $mail->Subject = 'Reset your Kandado Password';

        $mail->Body = "
<!doctype html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <title>Password Reset</title>
  <style>
    @media only screen and (max-width: 600px) {
      .container { width: 100% !important; padding: 16px !important; }
      .btn-table { width: 100% !important; }
      .btn-link  { display: block !important; width: auto !important; text-align: center !important; margin: 0 auto !important; }
    }
    a { text-decoration: none; }
  </style>
</head>
<body style='margin:0; padding:0; background:#f3f4f6;'>
  <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background:#f3f4f6;'>
    <tr>
      <td align='center' style='padding:24px;'>
        <table role='presentation' class='container' cellspacing='0' cellpadding='0' border='0' width='600' style='width:600px; max-width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.08);'>
          <tr>
            <td align='center' style='background:{$brandColor}; padding:20px 24px; text-align:center;'>
              <h1 style='margin:0; font-family:Arial,Helvetica,sans-serif; font-size:20px; color:#ffffff;'>Kandado</h1>
            </td>
          </tr>
          <tr>
            <td style='padding:28px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif; color:#111827;'>
              <h2 style='margin:0 0 8px 0; font-size:22px; line-height:1.3;'>Password Reset Request</h2>
              <p style='margin:0; font-size:16px; line-height:1.6; color:#374151;'>Hi {$safeFirst},</p>
            </td>
          </tr>
          <tr>
            <td style='padding:12px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:15px; line-height:1.6; color:#374151;'>
                We received a request to reset your Kandado password. Click the button below to proceed.
                <strong>This link will expire in 1 hour.</strong>
              </p>
            </td>
          </tr>
          <tr>
            <td style='padding:20px 24px 28px 24px; font-family:Arial,Helvetica,sans-serif;' align='center'>
              <!--[if mso]>
                <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' href='{$resetLink}'
                  style='height:44px;v-text-anchor:middle;width:240px;'
                  arcsize='12%' stroke='f' fillcolor='{$brandColor}'>
                  <w:anchorlock/>
                  <center style='color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;'>
                    Reset Password
                  </center>
                </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-- -->
              <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' class='btn-table' style='margin:0 auto;'>
                <tr>
                  <td style='background:{$brandColor}; border-radius:8px; mso-padding-alt:14px 22px;'>
                    <a href='{$resetLink}' class='btn-link'
                       style='display:inline-block; padding:14px 22px; font-size:16px; font-weight:bold;
                              font-family:Arial,Helvetica,sans-serif; color:#ffffff; text-decoration:none; border-radius:8px;'
                       target='_blank' rel='noopener'>
                      Reset Password
                    </a>
                  </td>
                </tr>
              </table>
              <!--<![endif]-->
            </td>
          </tr>
          <tr>
            <td style='padding:0 24px 20px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:14px; line-height:1.6; color:#6b7280; word-break:break-word;'>
                If the button doesn’t work, open this link in your browser:
                <br>
                <a href='{$resetLink}' style='color:{$brandColor}; word-break:break-all;' target='_blank' rel='noopener'>{$resetLink}</a>
              </p>
            </td>
          </tr>
          <tr>
            <td style='background:#f9fafb; padding:16px 24px; text-align:center; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:12px; color:#9ca3af;'>Kandado Support • Do not share this link with anyone.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
";
        $mail->AltBody = "Hi {$displayName},\n\n"
            ."We received a request to reset your Kandado password. This link expires in 1 hour:\n"
            ."{$resetLink}\n\n"
            ."If the button doesn’t work, open this link in your browser (copy & paste if needed).\n\n"
            ."If you didn't request this, you can ignore this email.";

        return $mail->send();
    } catch (Exception $e){
        error_log("Mailer Error (Password Reset): ".$e->getMessage());
        return false;
    }
}

/**
 * Locker QR email (sent when user avails / reserves a locker)
 * Adds a prominent login link + note about using data/another network.
 */
function email_qr($to, $name, int $locker, string $qr_path_abs, string $code, string $reserve_date, string $reserve_time, string $expires_at_fmt): bool {
    if (!$to) return false;
    try {
        $mail = kandado_mailer();
        $displayName = $name ?: $to; // for headers
        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); // for HTML
        $brandColor = KANDADO_BRAND_COLOR;
        $loginUrl   = KANDADO_LOGIN_URL;
        $loginUrlLocal = KANDADO_LOGIN_URL_LOCAL;

        $mail->addAddress($to, $displayName);
        $mail->Subject = "Your Locker QR Code - Locker #{$locker}";
        $cid = 'lockerqr';

        $mail->Body= "
<!doctype html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <title>Locker QR</title>
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
                You have successfully reserved <strong>Locker #{$locker}</strong>.
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
                Reserved on: <strong>{$reserve_date}</strong><br>
                Reserved Time: <strong>{$reserve_time}</strong><br>
                Expires at: <strong>{$expires_at_fmt}</strong>
              </p>
            </td>
          </tr>

          <!-- CTA: Open Kandado -->
          <tr>
            <td style='padding:12px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif;' align='center'>
              <!--[if mso]>
                <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml'
                  href='{$loginUrl}'
                  style='height:44px;v-text-anchor:middle;width:240px;'
                  arcsize='12%' stroke='f' fillcolor='{$brandColor}'>
                  <w:anchorlock/>
                  <center style='color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;'>
                    View Reservation
                  </center>
                </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-- -->
              <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' class='btn-table' style='margin:0 auto;'>
                <tr>
                  <td style='background:{$brandColor}; border-radius:8px; mso-padding-alt:14px 22px;'>
                    <a href='{$loginUrl}' class='btn-link'
                       style='display:inline-block; padding:14px 22px; font-size:16px; font-weight:bold;
                              font-family:Arial,Helvetica,sans-serif; color:#ffffff; text-decoration:none; border-radius:8px;'
                       target='_blank' rel='noopener'>
                      View Reservation
                    </a>
                  </td>
                </tr>
              </table>
              <!--<![endif]-->
              <p class='btn-note' style='margin:10px 0 0 0; font-size:12px; line-height:1.6; color:#6b7280; font-family:Arial,Helvetica,sans-serif;'>
                This button opens a <strong>view-only</strong> page with your reservation details. From there, you can choose <strong>Extend</strong> to add more time.
              </p>
            </td>
          </tr>

          <!-- Fallback link + network note -->
          <tr>
            <td style='padding:8px 24px 20px 24px; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:14px; line-height:1.6; color:#6b7280;'>
                If the button doesn’t work, open this link in your browser:
                <br>
                <a href='{$loginUrl}' style='color:{$brandColor}; word-break:break-all;' target='_blank' rel='noopener'>{$loginUrl}</a>
              </p>
              <p style='margin:8px 0 0 0; font-size:13px; line-height:1.6; color:#6b7280;'>
                if you're still connected to the KandadoWifi (Wi-Fi), you can also use this link:
                <br>
                <a href='{$loginUrlLocal}' style='color:{$brandColor}; word-break:break-all;' target='_blank' rel='noopener'>{$loginUrlLocal}</a>
              </p>
              <p style='margin:8px 0 0 0; font-size:13px; line-height:1.6; color:#6b7280;'>
                Both links open a <strong>view-only</strong> page. You can extend your reservation after it opens.
              </p>
              <p style='margin:8px 0 0 0; font-size:13px; line-height:1.6; color:#6b7280;'>
                You can access this site using mobile data or another network — just click a link above.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style='background:#f9fafb; padding:16px 24px; text-align:center; font-family:Arial,Helvetica,sans-serif;'>
              <p style='margin:0; font-size:12px; color:#9ca3af;'>Kandado • Please keep your QR code confidential.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
";

        if (is_file($qr_path_abs)) {
            $mail->addEmbeddedImage($qr_path_abs, $cid);
        }

        // Plain-text fallback (with login link + note)
        $mail->AltBody =
            "Hello {$displayName},\n\n"
            ."You have successfully reserved Locker #{$locker}.\n\n"
            ."QR Code: {$code}\n"
            ."Reserved on: {$reserve_date}\n"
            ."Reserved Time: {$reserve_time}\n"
            ."Expires at: {$expires_at_fmt}\n\n"
            ."Open via public link (view-only): ".KANDADO_LOGIN_URL."\n"
            ."Open via local network (view-only): ".KANDADO_LOGIN_URL_LOCAL."\n\n"
            ."If the button doesn’t work, open one of the links above in your browser (copy & paste if needed).\n"
            ."You can access this site using mobile data or another network.\n";

        return $mail->send();
    } catch (Exception $e){
        error_log("Mailer Error (QR): ".$e->getMessage());
        return false;
    }
}

/** On Hold email (unchanged design) */
function email_on_hold($to, $name, int $locker): bool {
    if (!$to) return false;
    try {
        $mail = kandado_mailer();
        $displayName = $name ?: $to;
        $mail->addAddress($to, $displayName);
        $mail->Subject = "Locker #{$locker} On Hold - Item Inside";
        $year = date('Y');
        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $mail->Body = "
<html>
<head>
<style>
    body { font-family: 'Segoe UI','Roboto',sans-serif; background-color:#f4f6f8; color:#1f2937; margin:0; padding:0; }
    .email-container { max-width:600px; margin:40px auto; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .header { background-color:#0f172a; color:#ffffff; padding:20px; text-align:center; font-size:24px; font-weight:700; }
    .content { padding:30px 20px; line-height:1.6; font-size:16px; }
    .content p { margin:15px 0; }
    .highlight { color:#2563eb; font-weight:600; }
    .footer { background-color:#f1f5f9; color:#64748b; font-size:12px; text-align:center; padding:15px; }
</style>
</head>
<body>
<div class='email-container'>
    <div class='header'>Locker Hold Notification</div>
    <div class='content'>
    <p>Hi <strong>{$safeName}</strong>,</p>
    <p>Your locker <span class='highlight'>#{$locker}</span> is currently <strong>on hold</strong> because your QR code has expired, but there is still an item inside.</p>
    <p>Please visit the admin to retrieve your belongings as soon as possible.</p>
    <p>Thank you for trusting <strong>Kandado</strong> for your locker needs.</p>
    </div>
    <div class='footer'>
    &copy; {$year} Kandado. All rights reserved.
    </div>
</div>
</body>
</html>";
        return $mail->send();
    } catch (Exception $e){
        error_log("Mailer Error (Hold Notification): ".$e->getMessage());
        return false;
    }
}

/** Expired with no item (session released) */
function email_expired_released($to, $name, int $locker): bool {
    if (!$to) return false;
    try {
        $mail = kandado_mailer();
        $displayName = $name ?: $to;
        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $mail->addAddress($to, $displayName);
        $mail->Subject = "Locker #{$locker} Session Ended";
        $mail->Body = "
<html><body style='font-family:Arial,sans-serif;background:#f6f7fb;padding:24px;'>
<div style='max-width:560px;margin:auto;background:#ffffff;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.06);padding:24px;'>
    <h2 style='margin:0 0 12px;color:#111827;'>Session expired</h2>
    <p style='margin:0 0 8px;color:#374151;'>Hi <strong>{$safeName}</strong>, your session for <strong>Locker #{$locker}</strong> has expired. No item was detected inside, so the locker has been <strong>released</strong>.</p>
    <p style='margin-top:16px;color:#6b7280;font-size:12px;'>Thanks for using Kandado.</p>
</div>
</body></html>";
        return $mail->send();
    } catch (Exception $e){
        error_log("Mailer Error (Expired Released): ".$e->getMessage());
        return false;
    }
}

/** Generic “time left” reminders (30m / 15m) */
function email_time_left($to, $name, int $locker, int $minutes_left, string $expires_at_fmt): bool {
    if (!$to) return false;
    try {
        $mail = kandado_mailer();
        $displayName = $name ?: $to;
        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $mail->addAddress($to, $displayName);
        $mail->Subject = "Locker #{$locker}: {$minutes_left} minutes left";
        $mail->Body = "
<html><body style='font-family:Arial,sans-serif;background:#f6f7fb;padding:24px;'>
<div style='max-width:560px;margin:auto;background:#ffffff;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.06);padding:24px;'>
    <h2 style='margin:0 0 12px;color:#111827;'>Heads up!</h2>
    <p style='margin:0 0 8px;color:#374151;'>Hi <strong>{$safeName}</strong>, your <strong>Locker #{$locker}</strong> has <strong>{$minutes_left} minutes</strong> remaining.</p>
    <p style='margin:0 0 8px;color:#374151;'>Expires at: <strong>{$expires_at_fmt}</strong></p>
    <p style='margin-top:16px;color:#6b7280;font-size:12px;'>You can extend your time anytime.</p>
</div>
</body></html>";
        return $mail->send();
    } catch (Exception $e){
        error_log("Mailer Error (Time Left {$minutes_left}): ".$e->getMessage());
        return false;
    }
}

/** Power outage / backup power warning (admin-triggered) */
function email_power_alert($to, $name, int $locker, string $deadline_human): bool {
    if (!$to) return false;
    try {
        $mail = kandado_mailer();
        $displayName = $name ?: $to;
        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $brandColor = KANDADO_BRAND_COLOR;

        // ASCII-only subject line to avoid encoding issues in some clients
        $mail->Subject = "Important: Locker #{$locker} - Please retrieve within 1 hour";
        $mail->addAddress($to, $displayName);

        $mail->Body = "
<html><body style='font-family:Arial,Helvetica,sans-serif;background:#f6f7fb;padding:24px;'>
  <div style='max-width:600px;margin:auto;background:#ffffff;border-radius:12px;
              box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px;'>
    <h2 style='margin:0 0 8px;color:#111827;'>Heads up about your locker</h2>
    <p style='margin:8px 0;color:#374151;'>Hi <strong>{$safeName}</strong>,</p>
    <p style='margin:8px 0;color:#374151;'>
      We’re currently running on backup power. To make sure everyone can retrieve their items safely,
      please collect your belongings from <strong>Locker #{$locker}</strong> within the next
      <strong>1 hour</strong>.
    </p>
    <p style='margin:8px 0;color:#374151;'>
      After <strong>{$deadline_human}</strong>, backup power may shut down and the locker electronics
      could be unavailable until mains power is restored.
    </p>
    <p style='margin:16px 0;color:#374151;'>
      If you’ve already picked up your item, you can ignore this message.
      If you need help, reply to this email or contact the admin desk.
    </p>
    <p style='margin-top:16px;color:#6b7280;font-size:12px;'>
      Thanks for your understanding — Kandado Team
    </p>
  </div>
</body></html>";
        $mail->isHTML(true);
        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error (Power Alert): ".$e->getMessage());
        return false;
    }
}
