<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Enable errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check admin session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check locker number
if (!isset($_GET['locker'])) {
    echo json_encode(['success' => false, 'message' => 'Locker number missing']);
    exit();
}

$locker = (int)$_GET['locker'];

// DB connection
$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit();
}

try {
    $conn->begin_transaction();

    // 1️⃣ Fetch current locker info including expires_at and duration_minutes
    $stmt = $conn->prepare("SELECT code, user_id, expires_at, duration_minutes FROM locker_qr WHERE locker_number=?");
    $stmt->bind_param("i", $locker);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Locker not found']);
        exit();
    }

    $old_code = $row['code'];                     
    $user_id = $row['user_id'];                   
    $expires_at = $row['expires_at'];             
    $duration_minutes = $row['duration_minutes']; 

    $user_fullname = null;
    $user_email = null;

    // 2️⃣ Fetch user info if exists
    if ($user_id) {
        $stmt_user = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id=?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        if ($u = $stmt_user->get_result()->fetch_assoc()) {
            $user_fullname = $u['first_name'] . ' ' . $u['last_name'];
            $user_email = $u['email'];
        }
    }

    // 3️⃣ Log history if locker had a QR code
    if ($old_code) {
        // Convert empty datetime to NULL so MySQL stores it correctly
        $expires_at_param = ($expires_at && $expires_at != '0000-00-00 00:00:00') ? $expires_at : null;
        $duration_minutes_param = ($duration_minutes !== null) ? $duration_minutes : null;

        // Prepare SQL with placeholders for NULL values
        $stmt_hist = $conn->prepare("
            INSERT INTO locker_history 
                (locker_number, code, user_fullname, user_email, expires_at, duration_minutes, used_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        // Use mysqli_stmt::bind_param dynamically for NULLs
        $stmt_hist->bind_param(
            "issssi", 
            $locker,
            $old_code,
            $user_fullname,
            $user_email,
            $expires_at_param,
            $duration_minutes_param
        );
        $stmt_hist->execute();
    }

    // 4️⃣ Reset locker completely
    $stmt_reset = $conn->prepare("
        UPDATE locker_qr
        SET status='available',
            code=NULL,
            user_id=NULL,
            expires_at=NULL,
            duration_minutes=NULL
        WHERE locker_number=?
    ");
    $stmt_reset->bind_param("i", $locker);
    $stmt_reset->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => "Locker #$locker reset successfully"]);

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to reset locker: ' . $e->getMessage()]);
}
?>
