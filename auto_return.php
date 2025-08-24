<?php
// ไฟล์ auto_return.php พร้อมการบันทึก (Logging)
// ใช้สำหรับตรวจสอบและคืนสถานะล็อกเกอร์ที่หมดเวลาจองแล้วโดยอัตโนมัติ

// กำหนดพาธสำหรับไฟล์ Log
$logFile = __DIR__ . '/auto_return_log.txt';

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log
function writeLog($message, $logPath) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// เริ่มต้นการบันทึกเมื่อสคริปต์ถูกเรียก
writeLog("--- สคริปต์ auto_return.php เริ่มทำงาน ---", $logFile);

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

    // กำหนด Auth Token ของ Blynk ของคุณ
    // *** สำคัญ: ควรเก็บ Auth Token นี้ใน environment variable หรือไฟล์ config ที่ปลอดภัยกว่านี้ ***
    $blynkAuthToken = getenv('BLYNK_AUTH_TOKEN') ?: 'xeyFkCCbd3qwRgpnRtrWX_z16qx1uxm9'; // <<< ใส่ Auth Token ของคุณที่นี่

    foreach ($expired_lockers as $row) {
        $locker_id_db = $row['id'];
        $locker_number = $row['locker_number'];
        $email = $row['user_email'];
        $start = $row['start_time'];
        $end = $row['end_time'];
        $price_per_hour = $row['price_per_hour'];
        $blynkVirtualPin = $row['blynk_virtual_pin'];

        writeLog("💡 กำลังประมวลผลล็อกเกอร์ #{$locker_number} (ID: {$locker_id_db}) หมดเวลาตั้งแต่ {$end}", $logFile);

        // บันทึกการจองลงในตาราง bookings_history (สำหรับเก็บประวัติ)
        $insert_history_sql = "INSERT INTO bookings_history (locker_id, user_email, start_time, end_time, price_per_hour, total_price, returned_at)
                               VALUES (:locker_id, :user_email, :start_time, :end_time, :price_per_hour, :total_price, NOW())";
        $stmt_insert_history = $conn->prepare($insert_history_sql);

        // คำนวณราคารวมอีกครั้ง (อาจไม่จำเป็นถ้าเก็บไว้ใน bookings_history แล้วแต่ควรตรวจสอบ)
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

                // ส่งคำสั่งไปที่ Blynk Server เพื่อปิดล็อกเกอร์ (Virtual Pin = 0)
                if (!empty($blynkVirtualPin)) {
                    $blynkUrl = "https://blynk.cloud/external/api/update?token={$blynkAuthToken}&v{$blynkVirtualPin}=0";
                    writeLog("ส่งคำสั่ง Blynk URL: {$blynkUrl}", $logFile);
                    
                    // ใช้ cURL แทน file_get_contents เพื่อความยืดหยุ่นและการจัดการข้อผิดพลาดที่ดีกว่า
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $blynkUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // ไม่แสดงผลลัพธ์ออกไป
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // กำหนด timeout 5 วินาที
                    $blynk_response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // รับ HTTP response code
                    curl_close($ch);

                    if ($blynk_response !== false && $http_code >= 200 && $http_code < 300) {
                        writeLog("✅ ส่งคำสั่งปิดล็อกเกอร์ #{$locker_number} ไปยัง Blynk สำเร็จ (HTTP Code: {$http_code})", $logFile);
                    } else {
                        writeLog("❌ ข้อผิดพลาด: ไม่สามารถส่งคำสั่งปิดล็อกเกอร์ #{$locker_number} ไปยัง Blynk ได้ (HTTP Code: {$http_code}, Response: {$blynk_response})", $logFile);
                    }
                } else {
                    writeLog("❌ ข้อผิดพลาด: ไม่พบ Blynk Virtual Pin สำหรับล็อกเกอร์ #{$locker_number}", $logFile);
                }

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
