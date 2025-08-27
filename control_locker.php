<?php
// control_locker.php

// ไฟล์นี้ถูกปรับปรุงให้รับค่าจาก HTTP POST Request
// โค้ดนี้ไม่ใช้ session เพราะ ESP32 ไม่มีการจัดการ session เหมือน web browser

include 'connect.php'; // ตรวจสอบว่ามีไฟล์ connect.php ที่เชื่อมต่อฐานข้อมูลอยู่หรือไม่

header('Content-Type: application/json');

// Function to send a JSON response
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

$lockerNumber = $_POST['locker_number'] ?? null;
$action = $_POST['action'] ?? null;
$userEmail = $_POST['user_email'] ?? null; // ต้องส่ง email มาด้วยเพื่อใช้ตรวจสอบสิทธิ์

// Check for required parameters
if (empty($lockerNumber) || empty($action) || empty($userEmail)) {
    sendJsonResponse('error', 'Missing required parameters (locker_number, action, or user_email).');
}

// Check if the action is valid
if ($action !== 'open' && $action !== 'close') {
    sendJsonResponse('error', 'Invalid action. Action must be "open" or "close".');
}

try {
    // Retrieve locker information from the database to check permissions
    // Check that the locker is booked by this user and the status is 'occupied'
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
        sendJsonResponse('error', 'Permission denied. You do not own this locker or it is not occupied.', ['locker' => $lockerNumber]);
    }

    $esp32_ip = $locker['esp32_ip_address'];
    $esp32_url = "http://{$esp32_ip}/control?action={$action}";
    
    // Use cURL to send an HTTP GET request to the ESP32
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $esp32_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5-second timeout

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
