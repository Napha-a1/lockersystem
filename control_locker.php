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
$lockerNumber = $_POST['locker_number'] ?? null;
$command = $_POST['command'] ?? null; // 'on' or 'off'
$esp32_ip = $_POST['esp32_ip'] ?? null; // รับ IP Address มาจากหน้า index.php

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($lockerNumber) || empty($command) || empty($esp32_ip)) {
    sendJsonResponse('ERROR', 'Missing required parameters (locker_number, command, or esp32_ip).');
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
    // ตัวอย่าง: http://10.242.194.185/on หรือ http://10.242.194.185/off
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
    } elseif (trim($response) !== 'OK') { // ตรวจสอบการตอบกลับจาก ESP32
        sendJsonResponse('ERROR', 'Unexpected ESP32 response: ' . $response);
    } else {
        sendJsonResponse('SUCCESS', 'Command "' . $command . '" sent to Locker #1 successfully.');
    }

} catch (Exception $e) {
    sendJsonResponse('ERROR', 'An unexpected error occurred: ' . $e->getMessage());
}
?>
