<?php
// control_locker.php
// ไฟล์นี้ใช้สำหรับรับคำสั่งจากหน้าเว็บ และส่งคำสั่งไปยัง ESP32 โดยตรง

// กำหนด header ให้เป็น JSON response
header('Content-Type: application/json');

// ฟังก์ชันสำหรับส่ง response กลับไปในรูปแบบ JSON
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// รับค่าจาก POST
$lockerId = $_POST['locker_id'] ?? null;
$lockerNumber = $_POST['locker_number'] ?? null;
$command = $_POST['command'] ?? null; // 'on' or 'off'
$esp32_ip = $_POST['esp32_ip'] ?? null; // รับ IP Address มาจากหน้า index.php

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($lockerId) || empty($lockerNumber) || empty($command) || empty($esp32_ip)) {
    sendJsonResponse('ERROR', 'Missing required parameters (locker_id, locker_number, command, or esp32_ip).');
}

// ตรวจสอบว่า command ที่ส่งมาถูกต้องหรือไม่
if ($command !== 'on' && $command !== 'off') {
    sendJsonResponse('ERROR', 'Invalid command. Command must be "on" or "off".');
}

// ตรวจสอบว่าเป็น Locker 1 เท่านั้นที่สามารถควบคุมโดยตรงจากหน้านี้
if ($lockerNumber != 1) {
    sendJsonResponse('ERROR', 'Only Locker #1 can be controlled directly via this page.');
}

try {
    // สร้าง URL สำหรับส่งคำสั่งไปยัง ESP32
    $url = "http://" . $esp32_ip . "/" . $command;

    // ใช้ cURL เพื่อส่งคำสั่ง HTTP GET ไปยัง ESP32
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // ตั้งค่าให้ cURL คืนค่าเป็นสตริง
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // ตั้งค่า Timeout 5 วินาที

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // ตรวจสอบผลลัพธ์
    if ($curl_error) {
        sendJsonResponse('ERROR', 'cURL Error: ' . $curl_error);
    } elseif ($http_code !== 200) {
        sendJsonResponse('ERROR', 'HTTP Error: ' . $http_code . ' Response: ' . $response);
    } elseif (trim($response) !== 'OK') { // ตรวจสอบการตอบกลับจาก ESP32 (สมมติว่า ESP32 ตอบ 'OK')
        sendJsonResponse('ERROR', 'Unexpected ESP32 response: ' . $response);
    } else {
        // อัปเดตสถานะในฐานข้อมูลหลังจากส่งคำสั่งสำเร็จ
        include 'connect.php'; // เชื่อมต่อฐานข้อมูลอีกครั้งเพื่ออัปเดตสถานะ

        // กำหนดสถานะใหม่และข้อมูลผู้ใช้/เวลาให้เป็น NULL เมื่อปิด
        $new_status = ($command === 'on') ? 'occupied' : 'available'; // 'occupied' หรือ 'available' (ตัวพิมพ์เล็ก)
        $user_email_db = ($command === 'on') ? ($_SESSION['user_email'] ?? 'system') : NULL; // ผู้ที่สั่งเปิด, หรือ NULL ถ้าปิด
        $start_time_db = ($command === 'on') ? date('Y-m-d H:i:s') : NULL;
        $end_time_db = ($command === 'on') ? date('Y-m-d H:i:s', strtotime('+2 hours')) : NULL; // ตัวอย่าง: เปิด 2 ชั่วโมง

        $update_sql = "
            UPDATE lockers 
            SET status = :new_status, 
                user_email = :user_email, 
                start_time = :start_time, 
                end_time = :end_time 
            WHERE id = :locker_id
        ";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bindParam(':new_status', $new_status);
        $stmt_update->bindParam(':user_email', $user_email_db);
        $stmt_update->bindParam(':start_time', $start_time_db);
        $stmt_update->bindParam(':end_time', $end_time_db);
        $stmt_update->bindParam(':locker_id', $lockerId, PDO::PARAM_INT);
        $stmt_update->execute();

        sendJsonResponse('SUCCESS', 'Command "' . $command . '" sent to Locker #' . $lockerNumber . ' successfully and status updated in DB.');
    }

} catch (Exception $e) {
    sendJsonResponse('ERROR', 'An unexpected error occurred: ' . $e->getMessage());
}
?>
