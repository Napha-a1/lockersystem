<?php
// control_locker.php

include 'connect.php'; // ตรวจสอบว่ามีไฟล์ connect.php ที่เชื่อมต่อฐานข้อมูลอยู่หรือไม่

header('Content-Type: application/json');

// ฟังก์ชันสำหรับส่ง JSON response
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// โค้ดสำหรับ ESP32 ไม่จำเป็นต้องมีการตรวจสอบ Session
// หากคุณต้องการเพิ่มความปลอดภัย สามารถใช้ Secret Key ในการตรวจสอบแทนได้

$lockerNumber = $_POST['locker_number'] ?? null;
$action = $_POST['action'] ?? null;

// ตรวจสอบพารามิเตอร์ที่จำเป็น
if (empty($lockerNumber) || empty($action)) {
    sendJsonResponse('error', 'Missing required parameters (locker_number or action).');
}

// ตรวจสอบว่า action ถูกต้อง
if ($action !== 'open' && $action !== 'close') {
    sendJsonResponse('error', 'Invalid action. Action must be "open" or "close".');
}

// ในการใช้งานจริงกับ ESP32 คุณจะต้องส่ง user_email มาด้วย
// เพื่อให้สามารถอัปเดตสถานะของล็อกเกอร์ได้ถูกต้อง
$userEmail = $_POST['user_email'] ?? null;

// ตรวจสอบพารามิเตอร์ user_email เพิ่มเติม
if (empty($userEmail)) {
    sendJsonResponse('error', 'Missing required parameter (user_email).');
}

try {
    // ดึงข้อมูลล็อกเกอร์จากฐานข้อมูลเพื่อตรวจสอบสิทธิ์
    $stmt = $conn->prepare("
        SELECT id, esp32_ip_address, status
        FROM lockers
        WHERE locker_number = :locker_number
          AND user_email = :user_email
          AND status = 'occupied'
    ");
    $stmt->bindParam(':locker_number', $lockerNumber);
    $stmt->bindParam(':user_email', $userEmail);
    $stmt->execute();
    $locker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$locker) {
        sendJsonResponse('error', 'Permission denied. You do not own this locker or it is not occupied.');
    }

    $esp32_ip = $locker['esp32_ip_address'];
    $esp32_url = "http://{$esp32_ip}/control?action={$action}";
    
    // ใช้ cURL เพื่อส่งคำขอ HTTP GET ไปยัง ESP32
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $esp32_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // ตั้งค่า timeout 5 วินาที

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $controlSuccess = false;
    if ($http_code === 200) {
        $controlSuccess = true;
        error_log("Locker {$lockerNumber} control command sent successfully to {$esp32_ip}.");
    } else {
        error_log("Failed to send command to ESP32 at {$esp32_ip}. HTTP Code: {$http_code}");
    }
    
    if ($controlSuccess) {
        if ($action === 'close') {
            $update_stmt = $conn->prepare("
                UPDATE lockers 
                SET status = 'available', 
                    user_email = NULL, 
                    end_time = NOW() 
                WHERE id = :id
            ");
            $update_stmt->bindParam(':id', $locker['id']);
            $update_stmt->execute();
        }

        sendJsonResponse('success', 'Locker control command sent successfully.');
    } else {
        sendJsonResponse('error', 'Failed to send command to locker hardware.');
    }

} catch (PDOException $e) {
    error_log("Database Error in control_locker.php: " . $e->getMessage());
    sendJsonResponse('error', 'An internal server error occurred.');
}
?>
