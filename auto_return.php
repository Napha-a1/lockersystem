<?php
// ไฟล์ auto_return.php พร้อมการบันทึก (Logging)
// ใช้สำหรับตรวจสอบและคืนสถานะล็อกเกอร์ที่หมดเวลาจองแล้วโดยอัตโนมัติ
// โค้ดเวอร์ชันนี้จะจัดการเฉพาะฐานข้อมูลเท่านั้น ไม่มีการเชื่อมต่อกับ Blynk หรือ ESP32 โดยตรง

// กำหนดพาธสำหรับไฟล์ Log
$logFile = __DIR__ . '/auto_return_log.txt';

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// เริ่มต้นการบันทึกเมื่อสคริปต์ถูกเรียก
writeLog("--- สคริปต์ auto_return.php เริ่มทำงาน (ไม่มีการควบคุมอุปกรณ์ภายนอก) ---", $logFile);

include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ตรวจสอบการเชื่อมต่อ PDO
if ($conn === null) {
    writeLog("ERROR: เชื่อมต่อฐานข้อมูลล้มเหลว", $logFile);
    exit();
}

writeLog("🔄 เริ่มตรวจสอบล็อกเกอร์หมดเวลา...", $logFile);

try {
    // ดึงข้อมูลล็อกเกอร์ที่มีสถานะ 'occupied'
    // และเวลาสิ้นสุดน้อยกว่าหรือเท่ากับเวลาปัจจุบัน
    // ยังคง SELECT blynk_virtual_pin อยู่ แต่จะไม่ได้นำไปใช้ในการควบคุม
    $sql = "SELECT id, locker_number, user_email, start_time, end_time, price_per_hour, blynk_virtual_pin
            FROM lockers
            WHERE status = 'occupied' AND end_time < NOW()";
    $stmt_select = $conn->prepare($sql);
    $stmt_select->execute();
    $expired_lockers = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expired_lockers)) {
        writeLog("✅ ไม่มีรายการที่หมดเวลาในตอนนี้", $logFile);
        exit();
    }

    writeLog("📦 พบ " . count($expired_lockers) . " รายการที่หมดเวลา", $logFile);

    foreach ($expired_lockers as $row) {
        $locker_id_db = $row['id'];
        $locker_number = $row['locker_number'];
        $email = $row['user_email'];
        $start = $row['start_time'];
        $end = $row['end_time'];
        $price_per_hour = $row['price_per_hour'];
        // $blynkVirtualPin = $row['blynk_virtual_pin']; // ไม่ได้ใช้ในการควบคุมอีกต่อไป

        writeLog("💡 กำลังประมวลผลล็อกเกอร์ #{$locker_number} (ID: {$locker_id_db}) หมดเวลาตั้งแต่ {$end}", $logFile);

        // บันทึกการจองลงในตาราง bookings_history (สำหรับเก็บประวัติ)
        $insert_history_sql = "INSERT INTO bookings_history (locker_id, user_email, start_time, end_time, price_per_hour, total_price, returned_at)
                               VALUES (:locker_id, :user_email, :start_time, :end_time, :price_per_hour, :total_price, NOW())";
        $stmt_insert_history = $conn->prepare($insert_history_sql);

        // คำนวณราคารวม
        $start_timestamp = strtotime($start);
        $end_timestamp = strtotime($end);
        $diff_seconds = $end_timestamp - $start_timestamp;
        $hours = $diff_seconds / 3600;
        $total_price = $price_per_hour * $hours;

        $stmt_insert_history->bindParam(':locker_id', $locker_id_db, PDO::PARAM_INT);
        $stmt_insert_history->bindParam(':user_email', $email);
        $stmt_insert_history->bindParam(':start_time', $start);
        $stmt_insert_history->bindParam(':end_time', $end);
        $stmt_insert_history->bindParam(':price_per_hour', $price_per_hour, PDO::PARAM_STR);
        $stmt_insert_history->bindParam(':total_price', $total_price, PDO::PARAM_STR);

        if ($stmt_insert_history->execute()) {
            writeLog("✅ บันทึกประวัติการจองลง bookings_history สำหรับล็อกเกอร์ #{$locker_number} สำเร็จ", $logFile);

            // อัปเดตสถานะล็อกเกอร์เป็น 'available'
            $update_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :locker_id";
            $stmt_update = $conn->prepare($update_locker_sql);
            $stmt_update->bindParam(':locker_id', $locker_id_db, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                writeLog("✅ อัปเดตสถานะล็อกเกอร์ #{$locker_number} เป็น 'ว่าง' สำเร็จ", $logFile);
                // *** ส่วนของการส่งคำสั่งไปยัง Blynk Server (หรืออุปกรณ์อื่น) ถูกลบออกแล้ว ***
                writeLog("ℹ️ ไม่มีการส่งคำสั่งไปยังอุปกรณ์ภายนอกสำหรับล็อกเกอร์ #{$locker_number} (Blynk/ESP32)", $logFile);
            } else {
                writeLog("❌ ข้อผิดพลาด: ไม่สามารถอัปเดตสถานะล็อกเกอร์ #{$locker_number} ได้: " . $stmt_update->errorInfo()[2], $logFile);
            }
        } else {
            writeLog("❌ ข้อผิดพลาด: ไม่สามารถบันทึกประวัติการจองลง bookings_history ได้สำหรับล็อกเกอร์ #{$locker_number}: " . $stmt_insert_history->errorInfo()[2], $logFile);
        }
    }

} catch (PDOException $e) {
    writeLog("ERROR: Database operation failed in auto_return.php: " . $e->getMessage(), $logFile);
}

writeLog("--- สคริปต์ auto_return.php ทำงานเสร็จสิ้น ---", $logFile);
exit(); // สคริปต์ทำงานเสร็จสิ้น
?>
