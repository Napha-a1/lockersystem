<?php
session_start();
include('connect.php');

// ฟังก์ชันสำหรับบันทึกข้อความลงในไฟล์ Log (สามารถใช้ได้ทั้งในไฟล์นี้และไฟล์อื่นๆ)
function log_message($message) {
    $log_file = __DIR__ . '/locker_control_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบก่อนทำการควบคุมล็อคเกอร์"));
    exit();
}

// รับค่าจากฟอร์ม
$locker_number = $_POST['locker_number'] ?? null;
$command = $_POST['command'] ?? null;
$user_email = $_SESSION['user_email'];

if (empty($locker_number) || empty($command)) {
    header("Location: index.php?error=" . urlencode("ข้อมูลไม่สมบูรณ์"));
    exit();
}

try {
    // ขั้นตอนที่ 1: ตรวจสอบสถานะและสิทธิ์ของผู้ใช้
    $stmt = $conn->prepare("SELECT esp32_ip_address, status FROM lockers WHERE locker_number = :locker_number AND user_email = :user_email");
    $stmt->bindParam(':locker_number', $locker_number);
    $stmt->bindParam(':user_email', $user_email);
    $stmt->execute();
    $locker_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$locker_data) {
        header("Location: index.php?error=" . urlencode("ไม่พบข้อมูลล็อคเกอร์หรือคุณไม่มีสิทธิ์ควบคุมล็อคเกอร์นี้"));
        exit();
    }

    $esp32_ip = $locker_data['esp32_ip_address'];
    $current_status = $locker_data['status'];
    
    // ตั้งค่า URL สำหรับการเชื่อมต่อกับ ESP32
    $url = "http://" . htmlspecialchars($esp32_ip) . "/control";
    
    // กำหนดพารามิเตอร์สำหรับคำสั่ง
    $params = [
        'command' => $command,
        'locker' => $locker_number
    ];

    // สร้าง URL ด้วย query string
    $full_url = $url . '?' . http_build_query($params);
    
    // ตั้งค่า cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // ตั้งค่า timeout
    
    // บันทึก log ก่อนส่งคำสั่ง
    log_message("INFO: Sending command '{$command}' to locker {$locker_number} at IP {$esp32_ip}...");

    // ส่งคำสั่งไปยัง ESP32
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error_msg = curl_error($ch);
    curl_close($ch);
    
    // ตรวจสอบผลลัพธ์จาก cURL
    if ($response === false || $http_code !== 200) {
        log_message("ERROR: Failed to send command to ESP32. HTTP Code: {$http_code}, cURL Error: {$error_msg}, Response: " . print_r($response, true));
        header("Location: index.php?error=" . urlencode("ไม่สามารถเชื่อมต่อกับอุปกรณ์ได้. กรุณาลองใหม่อีกครั้ง."));
        exit();
    }
    
    // อัปเดตสถานะในฐานข้อมูลหลังจากส่งคำสั่งสำเร็จ
    $update_sql = "";
    $success_message = "";
    
    if ($command === 'open') {
        $update_sql = "UPDATE lockers SET status = 'occupied' WHERE locker_number = :locker_number";
        $success_message = "เปิดล็อคเกอร์หมายเลข " . htmlspecialchars($locker_number) . " สำเร็จ!";
    } elseif ($command === 'close') {
        $update_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE locker_number = :locker_number";
        $success_message = "ปิดล็อคเกอร์หมายเลข " . htmlspecialchars($locker_number) . " และยกเลิกการจองสำเร็จ!";
    } elseif ($command === 'cancel') {
        $update_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE locker_number = :locker_number";
        $success_message = "ยกเลิกการจองล็อคเกอร์หมายเลข " . htmlspecialchars($locker_number) . " สำเร็จ!";
    }

    if (!empty($update_sql)) {
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':locker_number', $locker_number);
        $update_stmt->execute();
    }
    
    log_message("INFO: Command '{$command}' for locker {$locker_number} executed successfully. Database updated.");
    header("Location: index.php?success=" . urlencode($success_message));
    exit();

} catch (PDOException $e) {
    log_message("FATAL ERROR: PDO Exception in control_locker.php: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode("เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage()));
    exit();
}
?>
