<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนดพาธสำหรับไฟล์ Log
$logFile = __DIR__ . '/booking_process_log.txt';

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeBookingLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[$timestamp] $message\n", FILE_APPEND);
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
    header("Location: book_locker.php?error=" . urlencode("ข้อมูลการจองไม่ครบถ้วน"));
    exit();
}

try {
    // เริ่ม Transaction เพื่อให้การทำงานเป็น Atomicity
    $conn->beginTransaction();

    // 1. ดึงข้อมูลราคาจากตาราง lockers ก่อน
    $stmt_price = $conn->prepare("SELECT price_per_hour FROM lockers WHERE id = :locker_id FOR UPDATE");
    $stmt_price->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $stmt_price->execute();
    $locker_data = $stmt_price->fetch(PDO::FETCH_ASSOC);

    if (!$locker_data) {
        throw new Exception("ไม่พบข้อมูลล็อกเกอร์");
    }
    $price_per_hour = $locker_data['price_per_hour'];

    // 2. อัปเดตสถานะล็อกเกอร์ในตาราง 'lockers'
    $update_stmt = $conn->prepare("
        UPDATE lockers 
        SET status = 'occupied', 
            user_email = :user_email, 
            start_time = :start_time, 
            end_time = :end_time 
        WHERE id = :id AND status = 'available'
    ");
    $update_stmt->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $update_stmt->bindParam(':start_time', $start_time, PDO::PARAM_STR);
    $update_stmt->bindParam(':end_time', $end_time, PDO::PARAM_STR);
    $update_stmt->bindParam(':id', $locker_id, PDO::PARAM_INT);
    $update_stmt->execute();

    if ($update_stmt->rowCount() == 0) {
        throw new Exception("ล็อกเกอร์ไม่ว่างแล้วหรือข้อมูลไม่ถูกต้อง");
    }

    // 3. บันทึกข้อมูลการจองลงในตาราง 'bookings'
    $booking_stmt = $conn->prepare("
        INSERT INTO bookings (locker_id, user_email, start_time, end_time, price_per_hour, booking_status)
        VALUES (:locker_id, :user_email, :start_time, :end_time, :price_per_hour, 'pending')
    ");
    $booking_stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $booking_stmt->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $booking_stmt->bindParam(':start_time', $start_time, PDO::PARAM_STR);
    $booking_stmt->bindParam(':end_time', $end_time, PDO::PARAM_STR);
    $booking_stmt->bindParam(':price_per_hour', $price_per_hour, PDO::PARAM_STR); // ใช้ค่าที่ดึงมาจากตาราง lockers
    $booking_stmt->execute();
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
    error_log("FATAL PDO Error in book_process.php: " . $e->getMessage()); // บันทึกข้อผิดพลาดสำหรับ Debug
    header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage()));
    exit();
}
?>
