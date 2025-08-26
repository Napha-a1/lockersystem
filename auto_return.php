<?php
// ในกรณีที่ External Cron Service ไม่ได้รัน session
// หรือเพื่อหลีกเลี่ยง Header already sent error เมื่อถูกเรียกโดยตรง
// session_start(); // คุณอาจจะต้องคอมเมนต์หรือลบบรรทัดนี้ออกไป หากไม่ได้ใช้ session ในสคริปต์นี้

include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนดพาธสำหรับไฟล์ Log
// ใช้ชื่อไฟล์แยกกันเพื่อติดตาม Log ของ Auto Return ได้ง่ายขึ้น
$logFile = __DIR__ . '/auto_return_log.txt';

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeAutoReturnLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// ==========================================================
// *** เพิ่มส่วนรักษาความปลอดภัยด้วย API Key (สำคัญมาก!) ***
// ==========================================================
// กำหนด API Key ลับของคุณ
// คุณต้องเปลี่ยน 'YOUR_SUPER_SECRET_KEY_HERE' เป็นรหัสที่ซับซ้อนและคาดเดายาก
// และอย่าเผยแพร่รหัสนี้
define('AUTO_RETURN_API_KEY', 'JWIA2@AF1!kfkova');

// ตรวจสอบ API Key ที่ส่งมาใน URL (GET parameter 'key')
$apiKey = $_GET['key'] ?? null;

if ($apiKey !== AUTO_RETURN_API_KEY) {
    // หาก API Key ไม่ถูกต้อง ให้ปฏิเสธการเข้าถึง
    writeAutoReturnLog("SECURITY ALERT: Unauthorized access attempt to auto_return.php. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . ", Provided Key: " . ($apiKey ?? 'None'), $logFile);
    http_response_code(401); // ส่ง HTTP Status Code 401 Unauthorized
    die("Unauthorized Access"); // หยุดการทำงานของสคริปต์
}
// ==========================================================
// *** จบส่วนรักษาความปลอดภัย ***
// ==========================================================

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
                // ใช้ 'available' (ตัวพิมพ์เล็กทั้งหมด) เพื่อให้ตรงกับ Check Constraint
                $update_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :locker_id AND status = 'occupied'";
                $update_stmt = $conn->prepare($update_locker_sql);
                $update_stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);

                if ($update_stmt->execute() && $update_stmt->rowCount() > 0) {
                    writeAutoReturnLog("INFO: Locker ID {$locker_id} (Number: {$locker_number}) status updated to 'available'.", $logFile);

                    // คุณสามารถเพิ่มโค้ดเพื่ออัปเดตสถานะในตาราง bookings
                    // หรือส่งการแจ้งเตือนอื่นๆ ที่นี่
                    // เช่น $update_booking_status_sql = "UPDATE bookings SET status = 'returned' WHERE ...";

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

// ส่งข้อความสำเร็จกลับไปให้ External Cron Service ทราบ
echo "Auto-return process completed successfully.";
?>
