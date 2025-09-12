    <?php
    // /kandado/lib/email_lib.php
    // Centralized email helpers (keeps original designs)

    require_once $_SERVER['DOCUMENT_ROOT'] . '/kandado/vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    function kandado_mailer(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lockerkandado01@gmail.com';
        $mail->Password = 'xgzhnjxyapnphnco';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom('lockerkandado01@gmail.com','Kandado');
        $mail->isHTML(true);
        return $mail;
    }

    /** Original QR email (unchanged design) with embedded image */
    function email_qr($to, $name, int $locker, string $qr_path_abs, string $code, string $reserve_date, string $reserve_time, string $expires_at_fmt): bool {
        if (!$to) return false;
        try {
            $mail = kandado_mailer();
            $mail->addAddress($to, $name ?: $to);
            $mail->Subject = "Your Locker QR Code - Locker #{$locker}";
            $cid = 'lockerqr';
            $mail->Body= "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #f9f9f9; padding: 30px;'>
        <tr>
            <td align='center'>
            <table width='400' cellpadding='0' cellspacing='0' border='0' style='background-color: #ffffff; padding: 20px; border-radius: 12px; text-align: center;'>
                <tr>
                <td>
                    <h2 style='color: #333; font-family: Arial, sans-serif;'>Hello {$name},</h2>
                    <p style='font-size: 16px; color: #555; font-family: Arial, sans-serif;'>You have successfully reserved Locker #{$locker}.</p>
                    
                    <p style='font-size: 16px; font-weight: 600; color: #374151; font-family: Arial, sans-serif; margin: 10px 0 20px 0;'>Your QR Code:</p>
                    
                    <img src='cid:{$cid}' alt='QR Code' width='200' style='display: block; margin: 10px auto;' />
                    
                    <p style='font-size: 18px; font-weight: bold; color: #2563eb; font-family: Arial, sans-serif; margin: 10px 0 20px 0;'>{$code}</p>
                    
                    <p style='font-size: 14px; color: #888; font-family: Arial, sans-serif; margin-top: 10px;'>Use this QR code to open your locker.</p>

                    <p style='font-size: 14px; color: #555; font-family: Arial, sans-serif; margin-top: 20px;'>
                        Reserved on: <strong>{$reserve_date}</strong><br>
                        Reserved Time: <strong>{$reserve_time}</strong><br>
                        Expires at: <strong>{$expires_at_fmt}</strong>
                    </p>
                </td>
                </tr>
            </table>
            </td>
        </tr>
    </table>";
            if (is_file($qr_path_abs)) {
                $mail->addEmbeddedImage($qr_path_abs, $cid);
            }
            return $mail->send();
        } catch (Exception $e){
            error_log("Mailer Error (QR): ".$e->getMessage());
            return false;
        }
    }

    /** Original “On Hold” email (unchanged design) */
    function email_on_hold($to, $name, int $locker): bool {
        if (!$to) return false;
        try {
            $mail = kandado_mailer();
            $mail->addAddress($to, $name ?: $to);
            $mail->Subject = "Locker #{$locker} On Hold - Item Inside";
            $year = date('Y');
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
        <p>Hi <strong>{$name}</strong>,</p>
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

    /** NEW: Expired with no item (session released) */
    function email_expired_released($to, $name, int $locker): bool {
        if (!$to) return false;
        try {
            $mail = kandado_mailer();
            $mail->addAddress($to, $name ?: $to);
            $mail->Subject = "Locker #{$locker} Session Ended";
            $mail->Body = "
    <html><body style='font-family:Arial,sans-serif;background:#f6f7fb;padding:24px;'>
    <div style='max-width:560px;margin:auto;background:#ffffff;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.06);padding:24px;'>
        <h2 style='margin:0 0 12px;color:#111827;'>Session expired</h2>
        <p style='margin:0 0 8px;color:#374151;'>Your session for <strong>Locker #{$locker}</strong> has expired. No item was detected inside, so the locker has been <strong>released</strong>.</p>
        <p style='margin-top:16px;color:#6b7280;font-size:12px;'>Thanks for using Kandado.</p>
    </div>
    </body></html>";
            return $mail->send();
        } catch (Exception $e){
            error_log("Mailer Error (Expired Released): ".$e->getMessage());
            return false;
        }
    }

    /** NEW: Generic “time left” reminders (30m / 15m) */
    function email_time_left($to, $name, int $locker, int $minutes_left, string $expires_at_fmt): bool {
        if (!$to) return false;
        try {
            $mail = kandado_mailer();
            $mail->addAddress($to, $name ?: $to);
            $mail->Subject = "Locker #{$locker}: {$minutes_left} minutes left";
            $mail->Body = "
    <html><body style='font-family:Arial,sans-serif;background:#f6f7fb;padding:24px;'>
    <div style='max-width:560px;margin:auto;background:#ffffff;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.06);padding:24px;'>
        <h2 style='margin:0 0 12px;color:#111827;'>Heads up!</h2>
        <p style='margin:0 0 8px;color:#374151;'>Your <strong>Locker #{$locker}</strong> has <strong>{$minutes_left} minutes</strong> remaining.</p>
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
        $mail->addAddress($to, $name ?: $to);

        // Use only ASCII in subject line to prevent encoding issues
        $mail->Subject = "Important: Locker #{$locker} - Please retrieve within 1 hour";

        $safeName = htmlspecialchars($name ?: $to, ENT_QUOTES, 'UTF-8');

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


    



