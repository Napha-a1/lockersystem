<?php 
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนดพาธสำหรับไฟล์ Log (สามารถใช้ log file เดียวกับ auto_return.php ได้ หรือแยกก็ได้)
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
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบก่อน"));
    exit();
}

// รับค่าจากฟอร์ม
$locker_id = $_POST['locker_id'] ?? null;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;
$user_email = $_SESSION['user_email']; // ใช้จาก session เพื่อความปลอดภัย

// ตรวจสอบว่าได้รับข้อมูลครบถ้วนหรือไม่
if (empty($locker_id) || empty($start_time) || empty($end_time)) {
    writeBookingLog("ERROR: Missing booking data (Locker ID: {$locker_id}, Start Time: {$start_time}, End Time: {$end_time})", $logFile);
    header("Location: book_locker.php?error=" . urlencode("ข้อมูลการจองไม่ครบถ้วน!"));
    exit();
}

// ตรวจสอบเวลาเริ่มและเวลาสิ้นสุด
if (strtotime($start_time) >= strtotime($end_time)) {
    writeBookingLog("ERROR: Invalid booking time (Start: {$start_time}, End: {$end_time})", $logFile);
    header("Location: book_locker.php?error=" . urlencode("เวลาจองไม่ถูกต้อง! เวลาสิ้นสุดต้องหลังเวลาเริ่ม"));
    exit();
}

try {
    // ดึงข้อมูลล็อกเกอร์ (รวมถึง locker_number ด้วย)
    writeBookingLog("INFO: Fetching locker data for ID: {$locker_id}", $logFile);
    $stmt = $conn->prepare("SELECT locker_number, status, price_per_hour FROM lockers WHERE id = :locker_id");
    $stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $stmt->execute();
    $locker_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$locker_data) {
        writeBookingLog("ERROR: Locker not found for ID: {$locker_id}", $logFile);
        header("Location: book_locker.php?error=" . urlencode("ไม่พบล็อกเกอร์ที่เลือก!"));
        exit();
    }

    if ($locker_data['status'] !== 'available') {
        writeBookingLog("ERROR: Locker #{$locker_data['locker_number']} is not available (Status: {$locker_data['status']})", $logFile);
        header("Location: book_locker.php?error=" . urlencode("ล็อกเกอร์ #" . htmlspecialchars($locker_data['locker_number']) . " ไม่ว่างสำหรับการจอง! สถานะปัจจุบัน: " . htmlspecialchars($locker_data['status'])));
        exit();
    }

    $price_per_hour = $locker_data['price_per_hour'];

    // คำนวณจำนวนชั่วโมงและราคารวม
    $start_timestamp = strtotime($start_time);
    $end_timestamp = strtotime($end_time);
    $diff_seconds = $end_timestamp - $start_timestamp;
    $hours = $diff_seconds / 3600; // แปลงเป็นชั่วโมง
    $total_price = $price_per_hour * $hours;

    writeBookingLog("INFO: Locker data found. Price/hr: {$price_per_hour}, Hours: {$hours}, Total Price: {$total_price}", $logFile);

    // อัปเดตสถานะล็อกเกอร์ในตาราง lockers
    // กำหนด user_email, start_time, end_time ด้วย
    writeBookingLog("INFO: Attempting to update lockers table for ID: {$locker_id}", $logFile);
    $update_locker_sql = "UPDATE lockers SET status = 'occupied', user_email = :user_email, start_time = :start_time, end_time = :end_time WHERE id = :locker_id";
    $update = $conn->prepare($update_locker_sql);
    $update->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $update->bindParam(':start_time', $start_time, PDO::PARAM_STR);
    $update->bindParam(':end_time', $end_time, PDO::PARAM_STR);
    $update->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);

    if ($update->execute()) {
        writeBookingLog("INFO: Locker ID {$locker_id} updated to 'occupied'.", $logFile);

        // บันทึกการจองลงในตาราง bookings
        writeBookingLog("INFO: Attempting to insert into bookings table.", $logFile);
        $insert_booking_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time, total_price) VALUES (:locker_id, :user_email, :start_time, :end_time, :total_price)";
        $insert_booking = $conn->prepare($insert_booking_sql);
        $insert_booking->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
        $insert_booking->bindParam(':user_email', $user_email, PDO::PARAM_STR);
        $insert_booking->bindParam(':start_time', $start_time, PDO::PARAM_STR);
        $insert_booking->bindParam(':end_time', $end_time, PDO::PARAM_STR);
        $insert_booking->bindParam(':total_price', $total_price, PDO::PARAM_STR); // ใช้ PARAM_STR สำหรับ decimal/numeric
        
        if ($insert_booking->execute()) {
            writeBookingLog("SUCCESS: Booking for locker #{$locker_data['locker_number']} completed successfully.", $logFile);
            // Redirect ไปยังหน้า index.php พร้อมข้อความสำเร็จ
            header("Location: index.php?success=" . urlencode("การจองล็อกเกอร์ #" . htmlspecialchars($locker_data['locker_number']) . " สำเร็จแล้ว! ยอดชำระ: " . number_format($total_price, 2) . " บาท"));
            exit();
        } else {
            $errorInfo = $insert_booking->errorInfo();
            writeBookingLog("ERROR: Failed to insert into bookings table. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}", $logFile);
            // หากบันทึก bookings ไม่สำเร็จ ให้ย้อนกลับสถานะ lockers
            // ควรมีการ rollback transaction หากใช้ transaction (ถ้าใช้)
            writeBookingLog("INFO: Reverting locker status for ID: {$locker_id} due to bookings insert failure.", $logFile);
            $revert_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :locker_id_revert";
            $revert_locker = $conn->prepare($revert_locker_sql);
            $revert_locker->bindParam(':locker_id_revert', $locker_id, PDO::PARAM_INT);
            $revert_locker->execute();
            writeBookingLog("INFO: Locker ID {$locker_id} reverted to 'available'.", $logFile);
            
            header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการบันทึกการจอง!"));
            exit();
        }
    } else {
        $errorInfo = $update->errorInfo();
        writeBookingLog("ERROR: Failed to update lockers table. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}", $logFile);
        header("Location: book_locker.php?error=" . urlencode("ไม่สามารถอัปเดตสถานะล็อกเกอร์ได้!"));
        exit();
    }
} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
    writeBookingLog("FATAL ERROR: PDOException in book_process.php: " . $e->getMessage(), $logFile);
    header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการดำเนินการจอง โปรดลองอีกครั้ง"));
    exit();
}
writeBookingLog("--- สคริปต์ book_process.php ทำงานเสร็จสิ้น ---", $logFile);
?>
