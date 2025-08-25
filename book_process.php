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

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($locker_id) || empty($start_time) || empty($end_time)) {
    writeBookingLog("ERROR: Missing required POST data.", $logFile);
    header("Location: book_locker.php?error=" . urlencode("กรุณากรอกข้อมูลให้ครบถ้วนสำหรับการจอง"));
    exit();
}

$user_email = $_SESSION['user_email'];

try {
    // เริ่มต้น Transaction
    $conn->beginTransaction();
    writeBookingLog("INFO: Transaction started.", $logFile);

    // 1. ตรวจสอบสถานะของล็อกเกอร์อีกครั้งเพื่อป้องกัน Race Condition
    //    และดึงข้อมูล price_per_hour พร้อมกับ LOCK ROW สำหรับการอัปเดต
    //    'FOR UPDATE' จะทำการ Lock แถวนี้ไว้เพื่อป้องกันการจองซ้ำซ้อนในเวลาเดียวกัน
    $check_locker_sql = "SELECT locker_number, status, price_per_hour FROM lockers WHERE id = :locker_id FOR UPDATE";
    $stmt_check = $conn->prepare($check_locker_sql);
    $stmt_check->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $locker_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$locker_data) {
        writeBookingLog("ERROR: Locker ID {$locker_id} not found.", $logFile);
        throw new Exception("ไม่พบล็อกเกอร์ที่คุณเลือก"); // โยน Exception เพื่อให้ Rollback
    }

    // ตรวจสอบสถานะของล็อกเกอร์ว่ายัง 'Available' หรือไม่ (อาจต้องปรับค่า 'Available' ให้ตรงกับ Constraint ด้วย)
    if ($locker_data['status'] !== 'Available' && $locker_data['status'] !== 'available') { // เพิ่มเช็คทั้งสองกรณีเพื่อความยืดหยุ่นก่อนอัปเดต
        writeBookingLog("ERROR: Locker ID {$locker_id} (Number: {$locker_data['locker_number']}) is not available (Status: {$locker_data['status']}).", $logFile);
        throw new Exception("ล็อกเกอร์หมายเลข " . htmlspecialchars($locker_data['locker_number']) . " ไม่ว่างแล้ว"); // โยน Exception เพื่อให้ Rollback
    }

    $price_per_hour = $locker_data['price_per_hour'];

    // 2. คำนวณราคารวม (ทำซ้ำในฝั่ง Server เพื่อความปลอดภัย)
    $start_datetime = new DateTime($start_time);
    $end_datetime = new DateTime($end_time);

    if ($start_datetime >= $end_datetime) {
        writeBookingLog("ERROR: End time is not after start time. Start: {$start_time}, End: {$end_time}", $logFile);
        throw new Exception("เวลาสิ้นสุดต้องหลังเวลาเริ่มต้น"); // โยน Exception เพื่อให้ Rollback
    }

    $interval = $start_datetime->diff($end_datetime);
    // คำนวณชั่วโมงทั้งหมด รวมถึงนาทีและวินาที
    $total_hours = $interval->days * 24 + $interval->h + $interval->i / 60 + $interval->s / 3600;
    $total_price = number_format($price_per_hour * $total_hours, 2, '.', ''); // ให้เป็นทศนิยม 2 ตำแหน่ง

    // 3. อัปเดตสถานะล็อกเกอร์ในตาราง 'lockers'
    //    *** เปลี่ยน 'occupied' เป็น 'Occupied' เพื่อให้ตรงกับ Check Constraint ที่คาดหวัง ***
    //    และใช้ status = 'Available' เพื่อยืนยัน Race Condition ที่ละเอียดขึ้น
    $update_locker_sql = "UPDATE lockers SET status = 'Occupied', user_email = :user_email, start_time = :start_time, end_time = :end_time WHERE id = :locker_id AND status IN ('Available', 'available')";
    $update = $conn->prepare($update_locker_sql);
    $update->bindParam(':user_email', $user_email);
    $update->bindParam(':start_time', $start_time);
    $update->bindParam(':end_time', $end_time);
    $update->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);

    if (!$update->execute() || $update->rowCount() === 0) { // หากไม่มีแถวถูกอัปเดต หมายถึงสถานะถูกเปลี่ยนไปแล้ว
        writeBookingLog("ERROR: Failed to update lockers table or locker status changed unexpectedly (Race condition). Locker ID {$locker_id}", $logFile);
        throw new Exception("ไม่สามารถอัปเดตสถานะล็อกเกอร์ได้ หรือล็อกเกอร์ไม่ว่างแล้ว");
    }
    writeBookingLog("INFO: Locker ID {$locker_id} updated to 'Occupied' by {$user_email}", $logFile);


    // 4. บันทึกข้อมูลการจองลงในตาราง 'bookings'
    $insert_booking_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time, total_price, booking_date) VALUES (:locker_id, :user_email, :start_time, :end_time, :total_price, NOW())";
    $insert_booking = $conn->prepare($insert_booking_sql);
    $insert_booking->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $insert_booking->bindParam(':user_email', $user_email);
    $insert_booking->bindParam(':start_time', $start_time);
    $insert_booking->bindParam(':end_time', $end_time);
    $insert_booking->bindParam(':total_price', $total_price);

    if (!$insert_booking->execute()) {
        $errorInfo = $insert_booking->errorInfo();
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
    header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดของฐานข้อมูลระหว่างการจอง"));
    exit();
}
// PDO connection is automatically closed when the script finishes 
?>
