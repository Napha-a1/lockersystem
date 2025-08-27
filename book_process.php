<?php
session_start();
include 'connect.php';
$logFile = __DIR__ . '/booking_process_log.txt';

function writeBookingLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

if (!isset($_SESSION['user_email'])) {
    writeBookingLog("ERROR: User not logged in, redirecting to login.php", $logFile);
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบก่อนทำการจอง"));
    exit();
}

$locker_id = $_POST['locker_id'] ?? null;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;
$user_email = $_SESSION['user_email'];

if (empty($locker_id) || empty($start_time) || empty($end_time)) {
    writeBookingLog("ERROR: Missing required POST data.", $logFile);
    header("Location: book_locker.php?error=" . urlencode("กรุณากรอกข้อมูลให้ครบถ้วน"));
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ตรวจสอบว่าล็อกเกอร์ว่างหรือไม่
    $stmt_check = $conn->prepare("SELECT status FROM lockers WHERE id = :locker_id AND status = 'available' FOR UPDATE");
    $stmt_check->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $is_available = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$is_available) {
        throw new Exception("ล็อกเกอร์ไม่ว่างหรือไม่สามารถจองได้");
    }

    // 2. บันทึกข้อมูลการจองลงในตาราง bookings
    $stmt_booking = $conn->prepare("INSERT INTO bookings (locker_id, user_email, start_time, end_time) VALUES (:locker_id, :user_email, :start_time, :end_time)");
    $stmt_booking->bindParam(':locker_id', $locker_id);
    $stmt_booking->bindParam(':user_email', $user_email);
    $stmt_booking->bindParam(':start_time', $start_time);
    $stmt_booking->bindParam(':end_time', $end_time);
    if (!$stmt_booking->execute()) {
        throw new Exception("เกิดข้อผิดพลาดในการบันทึกข้อมูลการจอง");
    }

    // 3. อัปเดตสถานะล็อกเกอร์ในตาราง lockers
    $stmt_update = $conn->prepare("UPDATE lockers SET status = 'occupied', user_email = :user_email, start_time = :start_time, end_time = :end_time WHERE id = :locker_id");
    $stmt_update->bindParam(':user_email', $user_email);
    $stmt_update->bindParam(':start_time', $start_time);
    $stmt_update->bindParam(':end_time', $end_time);
    $stmt_update->bindParam(':locker_id', $locker_id);
    if (!$stmt_update->execute()) {
        throw new Exception("เกิดข้อผิดพลาดในการอัปเดตสถานะล็อกเกอร์");
    }

    $conn->commit();
    header("Location: index.php?success=" . urlencode("จองล็อกเกอร์สำเร็จ!"));
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Booking Error: " . $e->getMessage());
    header("Location: book_locker.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
