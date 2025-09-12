<?php
require_once '../config/db.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila'); // ensure all date()/strtotime() use PH time

session_start();
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST["first_name"]);
    $lastName = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];

    // === Required Fields ===
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $errors[] = "All fields are required.";
    }

    // === Email Validation ===
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if domain has MX records
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            $errors[] = "Email domain does not exist or cannot receive emails.";
        }
    }

    // === Password Validation ===
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        $errors[] = "Password must include at least one special character.";
    }

    // === Password Strength Check ===
    $strengthScore = 0;
    if (preg_match('/[a-z]/', $password)) $strengthScore++;
    if (preg_match('/[A-Z]/', $password)) $strengthScore++;
    if (preg_match('/[0-9]/', $password)) $strengthScore++;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strengthScore++;
    if ($strengthScore < 2) {
        $errors[] = "Password is too weak.";
    }

    // === Check if email already exists ===
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already registered.";
        }
    }

    // === Proceed if no errors ===
    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Expiry in 30 minutes, explicitly using PH time (Asia/Manila)
        $tzManila  = new DateTimeZone('Asia/Manila');
        $expiresAt = (new DateTimeImmutable('now', $tzManila))
                        ->add(new DateInterval('PT30M'))
                        ->format('Y-m-d H:i:s'); // stored as naive local PH time

        // Insert user with token + expiry
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, verification_token, verification_expires_at)
            VALUES (?, ?, ?, ?, 'user', ?, ?)
        ");
        $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $token, $expiresAt]);

        // === Send Verification Email ===
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'lockerkandado01@gmail.com';
            $mail->Password = 'xgzhnjxyapnphnco'; // consider using a Gmail App Password / env var
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('lockerkandado01@gmail.com', 'Kandado'); 
            $mail->addAddress($email, "$firstName $lastName");

            $mail->isHTML(true);
            $mail->Subject = 'Verify your Kandado account (expires in 30 minutes)';

            $verificationLink = "https://192.168.1.104/kandado/auth/verify_process.php?token=" . rawurlencode($token);

            // Polished, responsive email (inline styles for broad client support) — with bulletproof centered button
            $brandColor = '#3353bb';
            $mail->Body = "
  <!doctype html>
  <html lang='en'>
  <head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Kandado Email Verification</title>
    <style>
      /* mobile tweaks for some clients */
      @media only screen and (max-width: 600px) {
        .container { width: 100% !important; padding: 16px !important; }
        /* keep button auto-width; center via table */
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
              <td style='background:{$brandColor}; padding:20px 24px;'>
                <h1 style='margin:0; font-family:Arial,Helvetica,sans-serif; font-size:20px; color:#ffffff;'>Kandado</h1>
              </td>
            </tr>
            <tr>
              <td style='padding:28px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif; color:#111827;'>
                <h2 style='margin:0 0 8px 0; font-size:22px; line-height:1.3;'>Welcome, ".htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES)."</h2>
                <p style='margin:0; font-size:16px; line-height:1.6; color:#374151;'>
                  Thanks for registering with <strong>Kandado</strong>! Please confirm your email address to activate your account.
                </p>
              </td>
            </tr>
            <tr>
              <td style='padding:16px 24px 8px 24px; font-family:Arial,Helvetica,sans-serif;'>
                <p style='margin:0; font-size:15px; line-height:1.6; color:#6b7280;'>
                  <strong>This verification link expires in 30 minutes.</strong> For your security, you’ll need to request a new link after that.
                </p>
              </td>
            </tr>
            <tr>
              <td style='padding:20px 24px 28px 24px; font-family:Arial,Helvetica,sans-serif;' align='center'>

                <!-- Bulletproof centered button -->
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
                                font-family:Arial,Helvetica,sans-serif; color:#ffffff; text-decoration:none; border-radius:8px;'>
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
                <p style='margin:0; font-size:14px; line-height:1.6; color:#6b7280;'>
                  If the button doesn’t work, copy and paste this link into your browser:
                  <br>
                  <a href='{$verificationLink}' style='color:{$brandColor}; word-break:break-all;'>{$verificationLink}</a>
                </p>
              </td>
            </tr>
            <tr>
              <td style='padding:4px 24px 28px 24px; font-family:Arial,Helvetica,sans-serif;'>
                <p style='margin:0; font-size:12px; line-height:1.6; color:#9ca3af;'>
                  Didn’t create this account? You can safely ignore this email — the link will become invalid after 30 minutes.
                </p>
              </td>
            </tr>
            <tr>
              <td style='background:#f9fafb; padding:16px 24px; text-align:center; font-family:Arial,Helvetica,sans-serif;'>
                <p style='margin:0; font-size:12px; color:#9ca3af;'>&copy; ".date('Y')." Kandado. All rights reserved.</p>
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
                ."Verify your email (link expires in 30 minutes):\n"
                .$verificationLink."\n\n"
                ."If you didn’t register, you can ignore this email.";

            $mail->send();
            $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account. The link expires in 30 minutes.";
            header("Location: ../public/verify.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Email could not be sent. Error: " . $mail->ErrorInfo;
        }
    }

    // Store errors in session and redirect back
    $_SESSION['register_errors'] = $errors;
    header("Location: ../public/register.php");
    exit;
}
