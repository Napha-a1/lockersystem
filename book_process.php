<?php
// book_process.php
// ไฟล์สำหรับประมวลผลการจองล็อกเกอร์
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนดพาธสำหรับไฟล์ Log
$logFile = __DIR__ . '/booking_process_log.txt';

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeBookingLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

writeBookingLog("--- สคริปต์ book_process.php เริ่มทำงาน ---", $logFile);
writeBookingLog("SESSION user_email: " . ($_SESSION['user_email'] ?? 'Not Set'), $logFile);
writeBookingLog("POST Data: " . print_r($_POST, true), $logFile);

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_email'])) {
    writeBookingLog("ERROR: User not logged in, redirecting to login.php", $logFile);
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบก่อนทำการจอง"));
    exit();
}

// รับค่าจากฟอร์ม
$locker_id = $_POST['locker_id'] ?? null;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;
$user_email = $_SESSION['user_email'];

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($locker_id) || empty($start_time) || empty($end_time)) {
    writeBookingLog("ERROR: Missing required POST data.", $logFile);
    header("Location: book_locker.php?error=" . urlencode("กรุณากรอกข้อมูลให้ครบถ้วน"));
    exit();
}

try {
    // ใช้ Transaction เพื่อให้การทำงานเป็นแบบ Atomic
    $conn->beginTransaction();
    writeBookingLog("INFO: Transaction started.", $logFile);

    // ขั้นตอนที่ 1: ตรวจสอบสถานะล็อกเกอร์อีกครั้งเพื่อป้องกัน Race Condition
    // การทำซ้ำนี้สำคัญมากหากมีผู้ใช้หลายคนพยายามจองในเวลาเดียวกัน
    $check_status_sql = "SELECT status FROM lockers WHERE id = :id FOR UPDATE"; // ใช้ FOR UPDATE เพื่อล็อคแถว
    $stmt_check_status = $conn->prepare($check_status_sql);
    $stmt_check_status->bindParam(':id', $locker_id, PDO::PARAM_INT);
    $stmt_check_status->execute();
    $locker = $stmt_check_status->fetch(PDO::FETCH_ASSOC);

    if ($locker['status'] !== 'available') {
        throw new Exception("ล็อกเกอร์นี้ไม่ว่างแล้ว กรุณาเลือกใหม่");
    }

    // ขั้นตอนที่ 2: อัปเดตสถานะล็อกเกอร์เป็น 'occupied' และบันทึกข้อมูลผู้ใช้
    $update_sql = "UPDATE lockers SET status = 'occupied', user_email = :user_email, start_time = :start_time, end_time = :end_time WHERE id = :id";
    $update_stmt = $conn->prepare($update_sql);

    $update_stmt->bindParam(':user_email', $user_email);
    $update_stmt->bindParam(':start_time', $start_time);
    $update_stmt->bindParam(':end_time', $end_time);
    $update_stmt->bindParam(':id', $locker_id, PDO::PARAM_INT);

    if (!$update_stmt->execute()) {
        $errorInfo = $update_stmt->errorInfo();
        writeBookingLog("ERROR: Failed to update locker status. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}", $logFile);
        throw new Exception("เกิดข้อผิดพลาดในการอัปเดตสถานะล็อกเกอร์");
    }
    writeBookingLog("INFO: Locker ID {$locker_id} status updated to 'occupied' by user {$user_email}.", $logFile);


    // ขั้นตอนที่ 3: บันทึกข้อมูลการจองลงในตาราง bookings
    // เพื่อเก็บประวัติการจองและเป็นหลักฐาน
    $insert_booking_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time) VALUES (:locker_id, :user_email, :start_time, :end_time)";
    $insert_stmt = $conn->prepare($insert_booking_sql);
    
    $insert_stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(':user_email', $user_email);
    $insert_stmt->bindParam(':start_time', $start_time);
    $insert_stmt->bindParam(':end_time', $end_time);
    
    if (!$insert_stmt->execute()) {
        $errorInfo = $insert_stmt->errorInfo();
        writeBookingLog("ERROR: Failed to insert into bookings table. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}", $logFile);
        throw new Exception("เกิดข้อผิดพลาดในการบันทึกข้อมูลการจอง");
    }
    writeBookingLog("INFO: Booking for Locker ID {$locker_id} by {$user_email} recorded successfully.", $logFile);

    // Commit Transaction เมื่อทุกอย่างสำเร็จ
    $conn->commit();
    writeBookingLog("INFO: Transaction committed successfully.", $logFile);

    header("Location: index.php?success=" . urlencode("จองล็อกเกอร์สำเร็จ!"));
    exit();

} catch (Exception $e) {
    // Rollback Transaction หากมีข้อผิดพลาดเกิดขึ้น
    if ($conn->inTransaction()) {
        $conn->rollBack();
        writeBookingLog("ERROR: Transaction rolled back due to error: " . $e->getMessage(), $logFile);
    }
    error_log("Booking Error in book_process.php: " . $e->getMessage()); // บันทึกข้อผิดพลาดสำหรับ Debug
    header("Location: book_locker.php?error=" . urlencode($e->getMessage()));
    exit();
} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
    if ($conn->inTransaction()) {
        $conn->rollBack();
        writeBookingLog("FATAL ERROR: PDOException during transaction. Rolled back. Message: " . $e->getMessage(), $logFile);
    }
    error_log("FATAL PDO Error in book_process.php: " . $e->getMessage());
    header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่ภายหลัง"));
    exit();
}
?>
