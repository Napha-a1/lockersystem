<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนดพาธสำหรับไฟล์ Log (สามารถใช้ไฟล์เดียวกับ book_process.php ได้)
$logFile = __DIR__ . '/auto_return_log.txt'; // เปลี่ยนเป็นชื่อไฟล์ Log ที่เหมาะสม

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeAutoReturnLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

writeAutoReturnLog("--- สคริปต์ auto_return.php เริ่มทำงาน ---", $logFile);

try {
    // ดึง Locker ที่หมดเวลาจองแล้ว
    // ตรวจสอบว่า end_time น้อยกว่าหรือเท่ากับเวลาปัจจุบัน (NOW())
    // และสถานะยังเป็น 'occupied' (ตัวพิมพ์เล็กทั้งหมด)
    $stmt = $conn->prepare("SELECT id, locker_number, user_email FROM lockers WHERE end_time <= NOW() AND status = 'occupied'");
    $stmt->execute();
    $expired_lockers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expired_lockers)) {
        writeAutoReturnLog("INFO: No expired lockers found at this time.", $logFile);
    } else {
        foreach ($expired_lockers as $locker) {
            $locker_id = $locker['id'];
            $locker_number = $locker['locker_number'];
            $user_email = $locker['user_email'];

            writeAutoReturnLog("INFO: Processing expired locker ID: {$locker_id}, Number: {$locker_number}, User: {$user_email}", $logFile);

            // เริ่มต้น Transaction สำหรับแต่ละ Locker เพื่อความปลอดภัย
            $conn->beginTransaction();

            try {
                // อัปเดตสถานะ Locker กลับเป็น 'available'
                // *** ใช้ 'available' (ตัวพิมพ์เล็กทั้งหมด) เพื่อให้ตรงกับ Check Constraint ***
                $update_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :locker_id AND status = 'occupied'";
                $update_stmt = $conn->prepare($update_locker_sql);
                $update_stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);

                if ($update_stmt->execute() && $update_stmt->rowCount() > 0) {
                    writeAutoReturnLog("INFO: Locker ID {$locker_id} (Number: {$locker_number}) status updated to 'available'.", $logFile);

                    // บันทึก Log การคืน Locker ในตาราง bookings (ถ้ามีคอลัมน์สำหรับสถานะการคืน)
                    // ตัวอย่าง: อัปเดตสถานะในตาราง bookings เป็น 'returned' หรือ 'completed'
                    // $update_booking_status_sql = "UPDATE bookings SET status = 'returned' WHERE locker_id = :locker_id AND user_email = :user_email AND end_time <= NOW()";
                    // $update_booking_status_stmt = $conn->prepare($update_booking_status_sql);
                    // $update_booking_status_stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
                    // $update_booking_status_stmt->bindParam(':user_email', $user_email);
                    // $update_booking_status_stmt->execute();

                    $conn->commit();
                    writeAutoReturnLog("INFO: Transaction committed for Locker ID {$locker_id}.", $logFile);
                } else {
                    writeAutoReturnLog("WARNING: Failed to update Locker ID {$locker_id} (Number: {$locker_number}) status. May be already updated or status changed.", $logFile);
                    $conn->rollBack();
                }
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                writeAutoReturnLog("ERROR: Exception during processing Locker ID {$locker_id}: " . $e->getMessage(), $logFile);
                error_log("Auto-return Exception: " . $e->getMessage());
            }
        }
    }

} catch (PDOException $e) {
    writeAutoReturnLog("FATAL ERROR: PDOException in auto_return.php: " . $e->getMessage(), $logFile);
    error_log("FATAL PDO Error in auto_return.php: " . $e->getMessage());
}

writeAutoReturnLog("--- สคริปต์ auto_return.php ทำงานเสร็จสิ้น ---", $logFile);

// คุณอาจไม่ต้องการให้มี Output ใดๆ ถ้าสคริปต์นี้ถูกเรียกโดย Cron Job
// หรือคุณอาจส่งข้อความตอบกลับถ้าเรียกผ่านเว็บ
// echo "Auto-return process completed.";
?>
