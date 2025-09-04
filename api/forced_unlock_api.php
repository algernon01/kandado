<?php
// ----------------- forced_unlock_api.php -----------------
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE); // suppress notices/warnings
session_start();

// ----------------- Admin check -----------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// ----------------- ESP32 settings -----------------
$esp32_host = "http://locker-esp32.local"; // ESP32 hostname or IP

// ----------------- Helper to call ESP32 -----------------
function unlockLockerOnESP($locker) {
    global $esp32_host;
    $url = $esp32_host . "/unlock?locker=" . ($locker - 1) . "&admin=1"; // 0-indexed ESP32 lockers
    $response = @file_get_contents($url);
    if ($response === FALSE) return ['success' => false, 'message' => 'Failed to contact ESP32'];
    $data = json_decode($response, true);
    if (!$data) $data = ['success' => true]; // assume success if ESP32 doesn't return JSON
    return $data;
}

// ----------------- Force Unlock Single Locker -----------------
if (isset($_GET['locker'])) {
    $locker = intval($_GET['locker']);
    if ($locker < 1 || $locker > 4) {
        echo json_encode(['success' => false, 'message' => 'Invalid locker number']);
        exit();
    }

    // Call ESP32 WITHOUT resetting locker
    $url = $esp32_host . "/unlock?locker=" . ($locker - 1) . "&admin=1&reset=0";
    $response = @file_get_contents($url);
    $data = $response ? json_decode($response, true) : ['success'=>true];
    echo json_encode($data);
    exit();
}

// ----------------- Force Unlock All Lockers -----------------
if (isset($_GET['all']) && $_GET['all'] == '1') {
    $results = [];
    for ($i = 1; $i <= 4; $i++) {
        $url = $esp32_host . "/unlock?locker=" . ($i-1) . "&admin=1&reset=0";
        $response = @file_get_contents($url);
        $results[$i] = $response ? json_decode($response, true) : ['success'=>true];
    }
    echo json_encode(['success'=>true, 'results'=>$results]);
    exit();
}


// ----------------- No action specified -----------------
echo json_encode(['success' => false, 'message' => 'No action specified']);
exit();
