<?php
// /kandado/api/power_alert_all.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Admin gate
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'forbidden']); exit;
}

require '../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/kandado/lib/email_lib.php';

$host='localhost'; $dbname='kandado'; $user='root'; $pass='';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function jexit($p, $c=200){ http_response_code($c); echo json_encode($p); exit; }

try{
  $conn = new mysqli($host,$user,$pass,$dbname);
  $conn->set_charset('utf8mb4');
  $conn->query("SET time_zone = '+08:00'"); // PH time
}catch(mysqli_sql_exception $e){
  jexit(['error'=>'db','message'=>$e->getMessage()],500);
}

date_default_timezone_set('Asia/Manila');
$deadline_ts = time() + 3600;
$deadline_human = date('F j, Y h:i A', $deadline_ts);

// Get ALL currently occupied lockers with a user/email
$sql = "
  SELECT l.locker_number AS locker, u.first_name, u.last_name, u.email
  FROM locker_qr l
  JOIN users u ON u.id = l.user_id
  WHERE l.status='occupied' AND l.user_id IS NOT NULL AND u.email <> '' 
";
$res = $conn->query($sql);

$sent = 0; $skipped = 0; $recipients = [];
while ($row = $res->fetch_assoc()){
  $locker = (int)$row['locker'];
  $name = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
  $email = $row['email'];

  if (!$email) { $skipped++; continue; }

  $ok = email_power_alert($email, $name, $locker, $deadline_human);
  if ($ok){
    $sent++;
    $recipients[] = ['locker'=>$locker, 'name'=>$name ?: $email, 'email'=>$email];
  } else {
    $skipped++;
  }
}

jexit([
  'success'=>true,
  'sent'=>$sent,
  'skipped'=>$skipped,
  'deadline'=>$deadline_human,
  'recipients'=>$recipients
]);
