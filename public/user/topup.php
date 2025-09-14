<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="color-scheme" content="light" />
  <meta name="theme-color" content="#ffffff" />
  <title>Top Up Wallet • Kandado</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
  <!-- Global styles -->
  <link rel="stylesheet" href="/kandado/assets/css/users_dashboard.css">
  <!-- Page-specific styles (as requested path) -->
  <link rel="stylesheet" href="../../assets/css/topup.css">
</head>
<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/kandado/includes/user_header.php'; ?>

  <main class="container" role="main">
    <div class="topup-container">
      <header class="topup-header">
        <h2 class="topup-title">Top Up Wallet</h2>
        <a class="btn" href="/kandado/public/user/dashboard.php">← Back to Dashboard</a>
      </header>

      <section class="topup-card" aria-label="Wallet balance">
        <div class="balance-row">
          <div class="label">Current Balance</div>
          <div id="walletBalanceTopup" class="value">₱0.00</div>
        </div>
        <div class="note">Top up your wallet using GCash or Maya. Funds will be available immediately.</div>
      </section>

      <section class="topup-card" aria-label="Add funds">
        <div class="amount-grid">
          <div>
            <div class="pm-title">Amount</div>
            <div class="input-row">
              <label for="amount" class="sr-only">Amount</label>
              <input id="amount" type="number" min="1" step="1" placeholder="Enter amount (₱)" />
            </div>
            <div class="preset-grid" style="margin-top:8px">
              <button class="preset-btn" data-val="50">₱50</button>
              <button class="preset-btn" data-val="100">₱100</button>
              <button class="preset-btn" data-val="200">₱200</button>
              <button class="preset-btn" data-val="500">₱500</button>
            </div>
          </div>

          <div>
            <div class="pm-title">Choose Payment</div>
            <div class="pay-methods" role="group" aria-label="Select a payment method">
              <button class="method active" data-method="GCash" id="pm-gcash" type="button" aria-pressed="true">
                <img src="/kandado/assets/icon/gcash.png" alt="GCash Logo" loading="lazy" decoding="async" />
                <span>GCash</span>
              </button>
              <button class="method" data-method="Maya" id="pm-maya" type="button" aria-pressed="false">
                <img src="/kandado/assets/icon/maya.png" alt="Maya Logo" loading="lazy" decoding="async" />
                <span>Maya</span>
              </button>
            </div>

            <div class="actions">
              <button class="btn cancel" id="cancelBtn" type="button">Cancel</button>
              <button class="btn pay" id="topupBtn" type="button">Top Up Now</button>
            </div>
          </div>
        </div>
      </section>

      <section class="topup-card" aria-label="How it works">
        <div class="pm-title">How it works</div>
        <ol style="margin:0; padding-left:18px; line-height:1.7;">
          <li>Enter the amount you want to add.</li>
          <li>Select GCash or Maya, then click <b>Top Up Now</b>.</li>
          <li>We’ll securely process the payment for a moment.</li>
          <li>On success, your wallet balance updates instantly.</li>
          <li>Use your wallet on the dashboard to reserve or extend your locker.</li>
        </ol>
      </section>
    </div>
  </main>

  <!-- libs first -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- page script (as requested path) -->
  <script src="../../assets/js/topup.js" defer></script>
</body>
</html>
