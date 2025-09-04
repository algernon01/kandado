<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Only allow admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

// Get locker number
$locker_number = intval($_GET['locker'] ?? 0);
if(!$locker_number){
    echo json_encode(['success'=>false,'message'=>'Invalid locker number']);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');
if($conn->connect_error){
    echo json_encode(['success'=>false,'message'=>'Database connection failed']);
    exit();
}

// Update locker from hold → available
$stmt = $conn->prepare("
    UPDATE locker_qr 
    SET status='available', code=NULL, user_id=NULL, expires_at=NULL, duration_minutes=NULL 
    WHERE locker_number=? AND status='hold'
");
$stmt->bind_param('i', $locker_number);

if($stmt->execute()){
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['success'=>false,'message'=>'Failed to release locker']);
}
