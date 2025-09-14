<?php
$status = $_GET['status'] ?? 'error';
$message = $_GET['msg'] ?? 'Something went wrong.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Email Verification - Kandado</title>
  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
  <style>
    /* === Reset === */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f2f6fc;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 1rem;
    }

    .verify-container {
      width: 100%;
      max-width: 500px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .verify-card {
      background: #fff;
      padding: 2.5rem;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      text-align: center;
      width: 100%;
      transition: transform 0.3s ease;
    }

    .verify-card h1 {
      font-size: 1.8rem;
      margin-bottom: 1rem;
    }

    .verify-card.success h1 {
      color: #28a745;
    }

    .verify-card.error h1 {
      color: #dc3545;
    }

    .verify-card p {
      font-size: 1rem;
      color: #444;
      margin-bottom: 2rem;
      line-height: 1.5;
    }

    .verify-btn {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      background-color: #2e5aac;
      color: #fff;
      border: none;
      border-radius: 8px;
      text-decoration: none;
      font-size: 1rem;
      transition: background-color 0.3s ease;
    }

    .verify-btn:hover {
      background-color: #1c3f84;
    }

    @media (max-width: 600px) {
      .verify-card {
        padding: 2rem 1.5rem;
      }

      .verify-card h1 {
        font-size: 1.5rem;
      }

      .verify-card p {
        font-size: 0.95rem;
      }

      .verify-btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="verify-container">
    <div class="verify-card <?= htmlspecialchars($status) ?>">
      <h1><?= htmlspecialchars($message) ?></h1>
      <p>You may now return to the login page to access your account.</p>
      <a href="../public/login.php" class="verify-btn">Go to Login</a>
    </div>
  </div>
</body>
</html>
