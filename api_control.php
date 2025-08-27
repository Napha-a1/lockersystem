<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนด header ให้เป็น JSON response
header('Content-Type: application/json');

// ฟังก์ชันสำหรับส่ง response กลับไปในรูปแบบ JSON
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_email'])) {
    sendJsonResponse('error', 'Authentication failed: User not logged in.');
}

// รับค่าจาก POST
$userEmail = $_SESSION['user_email'];
$lockerNumber = $_POST['locker_number'] ?? null;
$action = $_POST['action'] ?? null; // 'open' or 'close'

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($lockerNumber) || empty($action)) {
    sendJsonResponse('error', 'Missing required parameters (locker_number or action).');
}

// ตรวจสอบว่า action ที่ส่งมาถูกต้องหรือไม่
if ($action !== 'open' && $action !== 'close') {
    sendJsonResponse('error', 'Invalid action. Action must be "open" or "close".');
}

try {
    // ตรวจสอบสิทธิ์การควบคุมล็อกเกอร์
    // ผู้ใช้ต้องเป็นเจ้าของล็อกเกอร์และล็อกเกอร์ต้องมีสถานะ 'occupied'
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
    $locker_status = $locker['status'];

    // Placeholder: This is where you would send a command to the ESP32
    // For a real-world scenario, you would use cURL or a similar method
    // to send an HTTP request to the ESP32's IP address.
    // Example: $response = file_get_contents("http://{$esp32_ip}/control?action={$action}");

    // For this demonstration, we'll just log the action and return a success message.
    error_log("Locker {$lockerNumber} control request: User {$userEmail} wants to {$action}.");

    // ตรวจสอบผลการส่งคำสั่งไปยัง ESP32 (ในที่นี้คือการจำลอง)
    $controlSuccess = true; // Assume the control command was successful

    if ($controlSuccess) {
        // หากเป็นการปิดล็อกเกอร์ ให้ตั้งเวลาสิ้นสุดการใช้งานเป็นตอนนี้
        if ($action === 'close') {
            $update_stmt = $conn->prepare("UPDATE lockers SET status = 'available', user_email = NULL, end_time = NOW() WHERE id = :id");
            $update_stmt->bindParam(':id', $locker['id']);
            $update_stmt->execute();
        }
        sendJsonResponse('success', 'Locker control command sent successfully.');
    } else {
        sendJsonResponse('error', 'Failed to send command to locker hardware.');
    }

} catch (PDOException $e) {
    // Log the database error and send a generic message to the user
    error_log("Database Error in api_control.php: " . $e->getMessage());
    sendJsonResponse('error', 'An internal server error occurred.');
}
?>
