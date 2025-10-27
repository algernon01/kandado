 <?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];


$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';


$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("
    SELECT locker_number, code, expires_at
    FROM locker_qr
    WHERE user_id = ? AND status = 'occupied'
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$lockerData = $result->fetch_assoc() ?: null;


date_default_timezone_set('Asia/Manila');
$expires_timestamp = null;
if ($lockerData && !empty($lockerData['expires_at'])) {
    try {

        $dt = new DateTimeImmutable($lockerData['expires_at'], new DateTimeZone('Asia/Manila'));
        $expires_timestamp = $dt->getTimestamp();
    } catch (Exception $e) {
        $expires_timestamp = null;
    }
}
$conn->close();


$hasLocker        = (bool)$lockerData;
$lockerNumberSafe = $hasLocker ? (int)$lockerData['locker_number'] : null;
$lockerCodeSafe   = $hasLocker ? htmlspecialchars($lockerData['code'], ENT_QUOTES, 'UTF-8') : null;

$DEFAULT_DURATION_PHP = '30s';


$SERVER_NOW_MS = (int) round(microtime(true) * 1000);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta http-equiv="x-ua-compatible" content="ie=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Locker · Kandado</title>
  

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="icon" href="../../assets/icon/icon_tab.png" sizes="any">

<link rel="stylesheet" href="/kandado/assets/css/mylocker.css?v=9">

<style>
  .locker__code--below{display:inline-flex;align-items:center;gap:8px;margin-top:10px}
  .locker__qr{justify-items:center}
  @media (min-width:800px){.locker__qr{justify-items:start}}
  .is-busy{opacity:.6;pointer-events:none}
  .swal-confirm-btn{font-weight:600}
</style>
</head>
<body class="<?= $hasLocker ? 'has-locker' : 'no-locker' ?>">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/kandado/includes/user_header.php'; ?>

<main class="page">
  <section class="container">
    <header class="page-head">
      <h1>My Locker QR</h1>
      <div class="badges">
        <?php if ($hasLocker): ?>
          <span class="status-badge" id="statusPill" aria-live="polite">Active</span>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($hasLocker): ?>
      <article class="card locker" id="lockerCard" aria-live="polite">
        <header class="locker__head">
          <div class="locker__title">
            <span class="locker__eyebrow">Locker</span>
            <span class="locker__number">#<?= $lockerNumberSafe ?></span>
          </div>
        </header>

        <div class="locker__grid">

          <div class="locker__qr">
            <button class="qr__button" id="qrZoomBtn" aria-label="View QR full screen">
              <img id="qrImage" src="/kandado/qr_image/qr_<?= $lockerCodeSafe ?>.png" alt="QR code" />
            </button>

            <div class="locker__code locker__code--below" aria-live="polite">
              <span class="code-label">Code</span>
              <span class="code-value" id="qrCodeText"><?= $lockerCodeSafe ?></span>
              <button class="btn btn-ghost btn-xs" id="copyCodeBtn" aria-label="Copy code">Copy</button>
            </div>
          </div>

  
          <div class="locker__time">
            <div class="time__row">
              <span class="time__label">Time Remaining</span>
              <span class="time__value" id="remainingTime">--:--:--</span>
            </div>


            <div class="progress" id="timeTrack" aria-hidden="true">
              <div class="progress__bar" id="timeBar" style="width:0%"></div>
            </div>

            <div class="locker__extend">
              <div class="extend__row">

                <button id="extendBtn" class="btn btn-primary">⏱ Extend</button>
              </div>
            </div>

        <div class="locker__actions">
      <button id="saveBtn" class="btn btn-gray">💾 Save QR</button>
          <button id="terminateBtn" class="btn btn-danger">🔚 Terminate My Locker</button>
        </div>
          </div>
        </div>
      </article>
    <?php else: ?>
<article class="card empty">
  <div class="empty__illu" aria-hidden="true">
    <svg width="120" height="120" viewBox="0 0 120 120" fill="none">

      <rect x="14" y="16" width="92" height="88" rx="12" fill="#E9EFFC"/>

      <rect x="26" y="28" width="68" height="64" rx="8" fill="#FFFFFF"/>


      <rect x="30" y="32" width="18" height="18" rx="2" fill="#2563EB"/>
      <rect x="33.5" y="35.5" width="11" height="11" rx="1.5" fill="#D7E4FD"/>

      <rect x="72" y="32" width="18" height="18" rx="2" fill="#2563EB"/>
      <rect x="75.5" y="35.5" width="11" height="11" rx="1.5" fill="#D7E4FD"/>

      <rect x="30" y="66" width="18" height="18" rx="2" fill="#2563EB"/>
      <rect x="33.5" y="69.5" width="11" height="11" rx="1.5" fill="#D7E4FD"/>


      <rect x="52" y="38" width="6" height="6" rx="1" fill="#2563EB"/>
      <rect x="60" y="38" width="6" height="6" rx="1" fill="#D7E4FD"/>
      <rect x="68" y="38" width="6" height="6" rx="1" fill="#2563EB"/>

      <rect x="52" y="46" width="6" height="6" rx="1" fill="#D7E4FD"/>
      <rect x="60" y="46" width="6" height="6" rx="1" fill="#2563EB"/>
      <rect x="68" y="46" width="6" height="6" rx="1" fill="#D7E4FD"/>

      <rect x="52" y="54" width="6" height="6" rx="1" fill="#2563EB"/>
      <rect x="60" y="54" width="6" height="6" rx="1" fill="#D7E4FD"/>
      <rect x="68" y="54" width="6" height="6" rx="1" fill="#2563EB"/>

      <rect x="52" y="70" width="6" height="6" rx="1" fill="#D7E4FD"/>
      <rect x="60" y="70" width="6" height="6" rx="1" fill="#2563EB"/>
      <rect x="68" y="70" width="6" height="6" rx="1" fill="#D7E4FD"/>

      <rect x="60" y="78" width="6" height="6" rx="1" fill="#2563EB"/>
      <rect x="68" y="62" width="6" height="6" rx="1" fill="#2563EB"/>
    </svg>
  </div>
  <h2>No reserved locker</h2>
  <p>Reserve a locker from your dashboard to generate a QR code and start the timer.</p>
  <a class="btn btn-primary" href="/kandado/public/user/dashboard.php">Open Dashboard</a>
</article>

    <?php endif; ?>
  </section>
</main>

<?php if ($hasLocker): ?>

  <script>
    window.MYLOCKER = {
      serverNowMs: <?= (int) round(microtime(true) * 1000) ?>,
      defaultDuration: <?= json_encode($DEFAULT_DURATION_PHP) ?>,
      locker: {
        code: <?= json_encode($lockerData['code'] ?? null) ?>,
        number: <?= json_encode($lockerNumberSafe) ?>,
        expiresAtMs: <?= json_encode(($expires_timestamp ?? (time() - 1)) * 1000) ?>
      } 
    };
  </script>


  <script src="/kandado/assets/js/mylocker.js?v=1"></script>
<?php endif; ?>


</body>
</html>
