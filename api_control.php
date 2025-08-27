<?php
// api_control.php
// ไฟล์นี้ใช้สำหรับตรวจสอบสถานะออนไลน์ของ ESP32 และอาจจะใช้สำหรับ Admin API ในอนาคต
// ไม่ได้ใช้สำหรับควบคุม Locker โดยตรงจากหน้า index.php แล้ว

session_start();
include 'connect.php'; 

header('Content-Type: application/json');

function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

$action = $_POST['action'] ?? null; // หรือ $_GET['action'] ขึ้นอยู่กับว่าเรียกจากไหน

if ($action === 'check_online_status') {
    $ip_address = $_POST['ip_address'] ?? null; // หรือ $_GET['ip_address']
    
    if (empty($ip_address)) {
        sendJsonResponse('error', 'Missing IP address for status check.');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$ip_address}/status"); // สมมติว่า ESP32 มี endpoint /status
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        sendJsonResponse('success', 'ESP32 is online.', ['online' => true]);
    } else {
        sendJsonResponse('success', 'ESP32 is offline.', ['online' => false, 'http_code' => $http_code]);
    }
} else {
    // หากมีการเรียกใช้ API ด้วย action อื่นๆ ที่ยังไม่ได้กำหนด
    sendJsonResponse('error', 'Invalid or unsupported action.');
}

// ** หมายเหตุ: หากคุณมีฟังก์ชัน Admin API อื่นๆ เช่น เพิ่ม/ลบ Locker
// ** คุณสามารถเพิ่ม case เข้าไปใน switch statement หรือสร้างไฟล์ API แยกต่างหากได้
// ** ตามความเหมาะสม
?>
