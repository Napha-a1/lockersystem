<?php
// control_locker.php

// ฟังก์ชันสำหรับส่งคำสั่ง HTTP GET ไปยัง ESP32
function send_command_to_esp32($ip_address, $command) {
    $url = "http://" . $ip_address . "/control?command=" . urlencode($command);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // ตั้งค่า timeout 5 วินาที
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return "ERROR: cURL Error - " . $error;
    }
    if ($http_code != 200) {
        return "ERROR: HTTP Code " . $http_code . " - " . $response;
    }

    return "SUCCESS";
}

// ตรวจสอบว่ามีข้อมูลจาก POST request หรือไม่
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $locker_number = isset($_POST['locker_number']) ? (int)$_POST['locker_number'] : null;
    $action = isset($_POST['action']) ? $_POST['action'] : null;

    if ($locker_number && $action) {
        // กำหนด IP Address ของ ESP32 แต่ละตัว
        // คุณต้องเปลี่ยนค่าเหล่านี้ให้เป็น IP Address ที่ได้จาก ESP32 ของคุณจริงๆ
        $esp32_ips = [
            1 => '192.168.1.101', // IP ของ ESP32 ตัวที่ 1 สำหรับล็อกเกอร์ 1
            2 => '192.168.1.102', // IP ของ ESP32 ตัวที่ 2 สำหรับล็อกเกอร์ 2
        ];

        // ตรวจสอบว่าหมายเลขล็อกเกอร์มี IP ที่กำหนดไว้หรือไม่
        if (isset($esp32_ips[$locker_number])) {
            $esp32_ip = $esp32_ips[$locker_number];
            $result = send_command_to_esp32($esp32_ip, $action);

            // ส่งผลลัพธ์กลับไปในรูปแบบ JSON เพื่อให้ JavaScript จัดการ
            header('Content-Type: application/json');
            echo json_encode(['status' => $result]);
            exit();
        } else {
            // หมายเลขล็อกเกอร์ไม่ถูกต้อง
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ERROR: Invalid locker number.']);
            exit();
        }
    }
}
?>
