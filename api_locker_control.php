<?php
session_start();
include 'connect.php'; // ใช้ PDO
header('Content-Type: application/json');

function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_email'])) {
    sendJsonResponse('error', 'Authentication failed: User not logged in.');
}

// ตรวจสอบพารามิเตอร์ที่จำเป็น
$userEmail = $_SESSION['user_email'];
$lockerNumber = $_POST['locker_number'] ?? null;
$action = $_POST['action'] ?? null; // 'open' or 'close'

if (empty($lockerNumber) || empty($action)) {
    sendJsonResponse('error', 'Missing required parameters (locker_number or action).');
}

if ($action !== 'open' && $action !== 'close') {
    sendJsonResponse('error', 'Invalid action. Action must be "open" or "close".');
}

try {
    // ดึงข้อมูลล็อกเกอร์จากฐานข้อมูลและตรวจสอบสิทธิ์
    $stmt = $conn->prepare("SELECT id, esp32_ip_address FROM lockers WHERE locker_number = :locker_number AND user_email = :user_email AND status = 'occupied'");
    $stmt->bindParam(':locker_number', $lockerNumber);
    $stmt->bindParam(':user_email', $userEmail);
    $stmt->execute();
    $locker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$locker) {
        sendJsonResponse('error', 'Permission denied or locker not occupied by you.');
    }

    $esp32_ip = $locker['esp32_ip_address'];
    $esp32_url = "http://{$esp32_ip}/control?command=" . urlencode($action);

    // ส่งคำสั่งไปยัง ESP32
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $esp32_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        // ถ้าเป็นการเปิดล็อกเกอร์ (open) ให้อัปเดตสถานะในฐานข้อมูล
        if ($action === 'open') {
            $update_stmt = $conn->prepare("UPDATE lockers SET status = 'available', user_email = NULL, end_time = NOW() WHERE id = :id");
            $update_stmt->bindParam(':id', $locker['id']);
            $update_stmt->execute();
        }
        sendJsonResponse('success', 'Locker control command sent successfully.', ['action' => $action]);
    } else {
        sendJsonResponse('error', 'Failed to send command to locker hardware. HTTP Code: ' . $http_code);
    }
} catch (PDOException $e) {
    error_log("Database Error in api_locker_control.php: " . $e->getMessage());
    sendJsonResponse('error', 'Database error occurred.');
}
?>
