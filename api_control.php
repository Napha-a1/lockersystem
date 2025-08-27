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
    // ดึงข้อมูลล็อกเกอร์จากฐานข้อมูล พร้อมตรวจสอบสิทธิ์
    $stmt = $conn->prepare("
        SELECT id, esp32_ip_address, status, start_time, price_per_hour
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
    $start_time = $locker['start_time'];
    $price_per_hour = $locker['price_per_hour'];

    // Placeholder: This is where you would send a command to the ESP32
    error_log("Locker {$lockerNumber} control request: User {$userEmail} wants to {$action}.");
    
    // Assume the control command was successful for demonstration purposes
    $controlSuccess = true;

    if ($controlSuccess) {
        // Start a database transaction
        $conn->beginTransaction();

        if ($action === 'close') {
            $end_time = date('Y-m-d H:i:s');
            
            // Calculate total price
            $diff_seconds = strtotime($end_time) - strtotime($start_time);
            $diff_hours = $diff_seconds / 3600;
            $total_price = $price_per_hour * $diff_hours;

            // Update the locker status in the 'lockers' table
            $update_stmt = $conn->prepare("
                UPDATE lockers 
                SET status = 'available', 
                    user_email = NULL, 
                    start_time = NULL, 
                    end_time = NULL 
                WHERE id = :id
            ");
            $update_stmt->bindParam(':id', $locker['id']);
            $update_stmt->execute();
            
            // Insert the completed booking record into the 'bookings' table
            $insert_booking_stmt = $conn->prepare("
                INSERT INTO bookings (locker_id, user_email, start_time, end_time, price_per_hour, total_price, status)
                VALUES (:locker_id, :user_email, :start_time, :end_time, :price_per_hour, :total_price, 'completed')
            ");
            $insert_booking_stmt->bindParam(':locker_id', $locker['id']);
            $insert_booking_stmt->bindParam(':user_email', $userEmail);
            $insert_booking_stmt->bindParam(':start_time', $start_time);
            $insert_booking_stmt->bindParam(':end_time', $end_time);
            $insert_booking_stmt->bindParam(':price_per_hour', $price_per_hour);
            $insert_booking_stmt->bindParam(':total_price', $total_price);
            $insert_booking_stmt->execute();
        }

        // Commit the transaction
        $conn->commit();
        sendJsonResponse('success', 'Locker control command sent successfully.');
    } else {
        // Rollback the transaction on failure
        $conn->rollBack();
        sendJsonResponse('error', 'Failed to send command to locker hardware.');
    }

} catch (PDOException $e) {
    // Rollback the transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Log the database error for debugging and send a generic message to the user
    error_log("Database Error in api_control.php: " . $e->getMessage());
    sendJsonResponse('error', 'An internal server error occurred.');
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Application Error in api_control.php: " . $e->getMessage());
    sendJsonResponse('error', 'An application error occurred: ' . $e->getMessage());
}
?>
