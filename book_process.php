<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนดพาธสำหรับไฟล์ Log
$logFile = __DIR__ . '/booking_process_log.txt';

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeBookingLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

writeBookingLog("--- สคริปต์ book_process.php เริ่มทำงาน ---\n", $logFile);
writeBookingLog("SESSION user_email: " . ($_SESSION['user_email'] ?? 'Not Set') . "\n", $logFile);
writeBookingLog("POST Data: " . print_r($_POST, true) . "\n", $logFile);

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
    // เริ่ม Transaction เพื่อให้การทำงานเป็น Atomicity
    $conn->beginTransaction();
    writeBookingLog("INFO: Transaction started.", $logFile);

    // 1. ดึงข้อมูลราคาต่อชั่วโมงจากตาราง lockers
    $stmt_price = $conn->prepare("SELECT price_per_hour FROM lockers WHERE id = :locker_id FOR UPDATE");
    $stmt_price->bindParam(':locker_id', $locker_id);
    $stmt_price->execute();
    $locker = $stmt_price->fetch(PDO::FETCH_ASSOC);

    if (!$locker) {
        throw new Exception("ไม่พบข้อมูลล็อกเกอร์");
    }

    $price_per_hour = $locker['price_per_hour'];

    // 2. คำนวณราคารวม
    $start_datetime = new DateTime($start_time);
    $end_datetime = new DateTime($end_time);
    
    // คำนวณส่วนต่างของเวลาเป็นชั่วโมง
    $interval = $end_datetime->diff($start_datetime);
    $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
    
    // คำนวณราคารวม
    $total_price = $hours * $price_per_hour;
    
    // 3. ตรวจสอบว่าล็อกเกอร์ยังว่างอยู่
    $stmt_check_status = $conn->prepare("SELECT status FROM lockers WHERE id = :id");
    $stmt_check_status->bindParam(':id', $locker_id);
    $stmt_check_status->execute();
    $current_status = $stmt_check_status->fetchColumn();

    if ($current_status !== 'available') {
        throw new Exception("ล็อกเกอร์ไม่ว่างแล้ว");
    }

    // 4. อัปเดตสถานะล็อกเกอร์ในตาราง lockers
    $update_sql = "UPDATE lockers SET status = 'occupied', user_email = :user_email, start_time = :start_time, end_time = :end_time WHERE id = :id";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bindParam(':user_email', $user_email);
    $update_stmt->bindParam(':start_time', $start_time);
    $update_stmt->bindParam(':end_time', $end_time);
    $update_stmt->bindParam(':id', $locker_id);
    if (!$update_stmt->execute()) {
        $errorInfo = $update_stmt->errorInfo();
        writeBookingLog("ERROR: Failed to update locker. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}", $logFile);
        throw new Exception("เกิดข้อผิดพลาดในการอัปเดตสถานะล็อกเกอร์");
    }
    writeBookingLog("INFO: Locker ID {$locker_id} updated to occupied.", $logFile);

    // 5. บันทึกข้อมูลการจองในตาราง bookings
    $insert_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time, total_price) VALUES (:locker_id, :user_email, :start_time, :end_time, :total_price)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bindParam(':locker_id', $locker_id);
    $insert_stmt->bindParam(':user_email', $user_email);
    $insert_stmt->bindParam(':start_time', $start_time);
    $insert_stmt->bindParam(':end_time', $end_time);
    $insert_stmt->bindParam(':total_price', $total_price); // ใช้ค่าที่คำนวณได้
    
    if (!$insert_stmt->execute()) {
        $errorInfo = $insert_stmt->errorInfo();
        writeBookingLog("ERROR: Failed to record booking. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}", $logFile);
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
    header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการบันทึกข้อมูลการจอง"));
    exit();
}
