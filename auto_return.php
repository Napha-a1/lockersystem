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

include 'connect.php'; // เชื่อมต่อฐานข้อมูล

if ($conn->connect_error) {
    writeLog("ERROR: เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error, $logFile);
    exit();
}

writeLog("🔄 เริ่มตรวจสอบล็อกเกอร์หมดเวลา...", $logFile);

// ดึงข้อมูลล็อกเกอร์ที่มีสถานะ 'occupied'
// และเวลาสิ้นสุดน้อยกว่าหรือเท่ากับเวลาปัจจุบัน
$sql = "SELECT id, locker_number, user_email, start_time, end_time, price_per_hour, blynk_virtual_pin 
        FROM lockers 
        WHERE status = 'occupied' AND end_time < NOW()"; 
$result = $conn->query($sql);

if (!$result) {
    writeLog("ERROR: Query failed: " . $conn->error, $logFile);
    $conn->close();
    exit();
}

if ($result->num_rows == 0) {
    writeLog("✅ ไม่มีรายการที่หมดเวลาในตอนนี้", $logFile);
    $conn->close();
    exit();
}

writeLog("📦 พบ " . $result->num_rows . " รายการที่หมดเวลา", $logFile);

// กำหนด Auth Token ของ Blynk ของคุณ
$blynkAuthToken = 'xeyFkCCbd3qwRgpnRtrWX_z16qx1uxm9'; // <<< ใส่ Auth Token ของคุณที่นี่

while ($row = $result->fetch_assoc()) {
    $locker_id_db = $row['id']; 
    $locker_number = $row['locker_number'];
    $email = $row['user_email'];
    $start = $row['start_time'];
    $end = $row['end_time'];
    $price_per_hour = $row['price_per_hour'];
    $blynkVirtualPin = $row['blynk_virtual_pin'];

    writeLog("--- กำลังประมวลผลล็อกเกอร์ #{$locker_number} (ID: {$locker_id_db}) ---", $logFile);
    writeLog("อีเมล: {$email}, เวลาเริ่ม: {$start}, เวลาสิ้นสุด: {$end}", $logFile);

    // คำนวณเวลาที่ใช้ (เป็นนาที)
    $seconds = strtotime($end) - strtotime($start);
    $minutes = ceil($seconds / 60);

    // คิดเงิน
    $price_per_minute = $price_per_hour / 60; 
    $total_price = round($minutes * $price_per_minute, 2);

    writeLog("เวลาใช้: {$minutes} นาที, รวมราคา: {$total_price} บาท", $logFile);

    // บันทึกลง bookings
    $insert_booking_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time, total_price, status) VALUES (?, ?, ?, ?, ?, 'completed')";
    $stmt_insert = $conn->prepare($insert_booking_sql);
    $stmt_insert->bind_param("isssd", $locker_id_db, $email, $start, $end, $total_price);
    
    if ($stmt_insert->execute()) {
        writeLog("✅ บันทึกการจองลง bookings สำเร็จสำหรับล็อกเกอร์ #{$locker_number}", $logFile);

        // อัปเดตสถานะล็อกเกอร์ในตาราง lockers ให้เป็น 'available'
        $update_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = ?";
        $stmt_update = $conn->prepare($update_locker_sql);
        $stmt_update->bind_param("i", $locker_id_db);
        
        if ($stmt_update->execute()) {
            writeLog("✅ อัปเดตสถานะล็อกเกอร์ #{$locker_number} เป็น 'ว่าง' สำเร็จ", $logFile);

            // ส่งคำสั่งไปที่ Blynk Server เพื่อปิดล็อกเกอร์ (Virtual Pin = 0)
            if (!empty($blynkVirtualPin)) {
                $blynkUrl = "https://blynk.cloud/external/api/update?token={$blynkAuthToken}&v{$blynkVirtualPin}=0";
                writeLog("ส่งคำสั่ง Blynk URL: {$blynkUrl}", $logFile);
                $blynk_response = @file_get_contents($blynkUrl); 

                if ($blynk_response !== false) {
                    writeLog("✅ ส่งคำสั่งปิดล็อกเกอร์ #{$locker_number} ไปยัง Blynk สำเร็จ", $logFile);
                } else {
                    writeLog("❌ ข้อผิดพลาด: ไม่สามารถส่งคำสั่งปิดล็อกเกอร์ #{$locker_number} ไปยัง Blynk ได้ (ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต/Blynk API)", $logFile);
                }
            } else {
                writeLog("❌ ข้อผิดพลาด: ไม่พบ Blynk Virtual Pin สำหรับล็อกเกอร์ #{$locker_number}", $logFile);
            }

        } else {
            writeLog("❌ ข้อผิดพลาด: ไม่สามารถอัปเดตสถานะล็อกเกอร์ #{$locker_number} ได้: " . $stmt_update->error, $logFile);
        }
        $stmt_update->close();
    } else {
        writeLog("❌ ข้อผิดพลาด: ไม่สามารถบันทึกการจองลง bookings ได้สำหรับล็อกเกอร์ #{$locker_number}: " . $stmt_insert->error, $logFile);
    }
    $stmt_insert->close();
}

$conn->close();
writeLog("🏁 ตรวจสอบล็อกเกอร์หมดเวลาเสร็จสิ้น", $logFile);
writeLog("--- สคริปต์ auto_return.php ทำงานเสร็จสิ้น ---", $logFile);
?>
