<?php
session_start();
$success = $_SESSION['forgot_success'] ?? '';
$error   = $_SESSION['forgot_error']  ?? '';
unset($_SESSION['forgot_success'], $_SESSION['forgot_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Password Reset • Kandado</title>
  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
  <style>
    /* =============== Reset & Tokens =============== */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root{
      --bg: #F4F7FB;
      --bg-accent: radial-gradient(1200px 600px at 20% -10%, #ffffff 0%, #eef3fb 50%, #e8eef8 100%);
      --card: #ffffff;
      --text: #0f172a;
      --muted: #64748b;

      --action: #2563EB;        
      --action-700: #1D4ED8;   
      --success: #16a34a;
      --error: #e11d48;

      --radius-lg: 18px;
      --radius-md: 12px;
      --shadow: 0 14px 40px rgba(15,46,79,.14);
      --border: #e9eef7;
      --focus: 0 0 0 4px rgba(37,99,235,.16);
    }

    html, body { height: 100%; }
    body{
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
      background: var(--bg);
      background-image: var(--bg-accent);
      color: var(--text);
      display: grid;
      place-items: center;
      padding: 24px;
      line-height: 1.45;
    }


    .container{
      width: 100%;
      max-width: 520px;
      padding: 8px;
    }
    .card{
      position: relative;
      z-index: 0;          
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 30px 28px 22px;
      text-align: center;
      overflow: hidden;
      animation: rise .45s ease both;
    }

    .card::after{
      content: "";
      position: absolute; right: -90px; top: -90px;
      width: 220px; height: 220px;
      border-radius: 50%;
      background: conic-gradient(from 200deg at 50% 50%, #eff5ff, #eef3fb, #eff5ff);
      opacity: .7;
      pointer-events: none;
      z-index: -1; 
    }

    @keyframes rise{
      from{ opacity:0; transform: translateY(8px) scale(.995) }
      to{ opacity:1; transform: translateY(0) scale(1) }
    }

    .title{
      font-size: 30px;
      font-weight: 800;
      letter-spacing: .2px;
      margin-bottom: 8px;
    }
    .subtitle{
      color: var(--muted);
      font-size: 15.5px;
      margin-bottom: 18px;
    }


    .msg{
      text-align: left;
      border-radius: var(--radius-md);
      padding: 12px 14px;
      margin: 8px 0 16px;
      font-size: 14px;
      border-left: 4px solid transparent;
    }
    .msg.success{
      background: #ecfdf5;
      border-color: var(--success);
      color: #065f46;
    }
    .msg.error{
      background: #ffe8ee;
      border-color: var(--error);
      color: #8f1a37;
    }

    .btn{
      display: inline-block;
      width: 100%;
      padding: 13px 16px;
      border-radius: 14px;
      font-weight: 800;
      letter-spacing: .2px;
      border: 1px solid transparent;
      text-decoration: none;
      user-select: none;
      text-align: center;
      color: #fff;
      background: var(--action);
      box-shadow: 0 10px 22px rgba(37,99,235,.26);
      transition: transform .06s ease, box-shadow .2s ease, background .2s ease, filter .2s ease;
    }
    .btn:hover{ background: var(--action-700); }
    .btn:active{ transform: translateY(1px); }
    .btn:focus-visible{ outline: none; box-shadow: var(--focus); }

    .hint{
      margin-top: 14px;
      color: var(--muted);
      font-size: 14px;
    }


    @media (max-width: 560px){
      body{ padding: 16px; }
      .card{ padding: 26px 18px 18px; border-radius: 16px; }
      .title{ font-size: 26px; }
      .subtitle{ font-size: 14.5px; }
    }


    @media (prefers-reduced-motion: reduce){
      *{ animation: none !important; transition: none !important; }
    }
  </style>
</head>
<body>
  <main class="container">
    <section class="card" role="status" aria-live="polite">
      <h1 class="title">Password reset</h1>
      <p class="subtitle">If the address you entered matches an account, we’ve sent a reset link.</p>

      <?php if (!empty($success)): ?>
        <div class="msg success"><?= htmlspecialchars($success) ?></div>
      <?php elseif (!empty($error)): ?>
        <div class="msg error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <a href="login.php" class="btn">Back to login</a>
      <p class="hint">Didn’t request this? You can safely ignore this message.</p>
    </section>
  </main>
</body>
</html>
