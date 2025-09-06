<?php
// /kandado/api/toggle_maintenance.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$locker = isset($_GET['locker']) ? (int)$_GET['locker'] : 0;
$mode   = isset($_GET['mode']) ? $_GET['mode'] : '';

if ($locker <= 0 || !in_array($mode, ['on','off'], true)) {
  echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
  echo json_encode(['success' => false, 'message' => 'DB connection failed']); exit;
}

$maintenance = ($mode === 'on') ? 1 : 0;

$stmt = $conn->prepare("UPDATE locker_qr SET maintenance=? WHERE locker_number=?");
$stmt->bind_param('ii', $maintenance, $locker);
$ok = $stmt->execute();

echo json_encode([
  'success' => (bool)$ok,
  'message' => $ok ? ($maintenance ? 'Locker set to maintenance.' : 'Maintenance ended for locker.') : 'Update failed.'
]);
