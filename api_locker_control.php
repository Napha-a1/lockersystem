<?php
header('Content-Type: text/plain'); // กำหนด Content-Type เป็น plain text สำหรับการตอบกลับ
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล PDO สำหรับ PostgreSQL

// กำหนด Auth Token ของ Blynk ของคุณที่นี่
// *** สำคัญ: ควรเก็บ Auth Token นี้ใน environment variable หรือไฟล์ config ที่ปลอดภัยกว่านี้ ***
$blynkAuthToken = getenv('BLYNK_AUTH_TOKEN') ?: 'xeyFkCCbd3qwRgpnRtrWX_z16qx1uxm9'; 

// ตรวจสอบว่ามีพารามิเตอร์ที่จำเป็นส่งมาหรือไม่
if (isset($_GET['locker_number']) && isset($_GET['user_email']) && isset($_GET['action'])) {
    $lockerNumber = $_GET['locker_number'];
    $userEmail = $_GET['user_email'];
    $action = $_GET['action']; // 'open' หรือ 'close'

    // ตรวจสอบสิทธิ์จาก Session
    // ผู้ใช้ที่ล็อกอินอยู่ต้องเป็นคนเดียวกับผู้ใช้ที่พยายามควบคุมล็อกเกอร์
    if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] !== $userEmail) {
        echo "ERROR: Permission Denied. User session mismatch.";
        exit();
    }

    try {
        // ป้องกัน SQL Injection โดยใช้ Prepared Statement เพื่อดึงข้อมูลล็อกเกอร์
        $sql = "SELECT status, user_email, blynk_virtual_pin FROM lockers WHERE locker_number = :locker_number";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':locker_number', $lockerNumber);
        $stmt->execute();
        $locker_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($locker_data) {
            $status = $locker_data['status'];
            $bookedBy = $locker_data['user_email'];
            $blynkVirtualPin = $locker_data['blynk_virtual_pin'];

            // ตรวจสอบเงื่อนไข: ล็อกเกอร์ต้องถูกจองโดยผู้ใช้คนนี้และอยู่ในสถานะ 'occupied'
            if ($status == 'occupied' && $bookedBy == $userEmail) {
                // กำหนดค่า Virtual Pin ที่จะส่งไปยัง Blynk
                // 1 สำหรับเปิด, 0 สำหรับปิด
                $blynkValue = ($action === 'open') ? 1 : 0;
                $blynkUrl = "https://blynk.cloud/external/api/update?token={$blynkAuthToken}&v{$blynkVirtualPin}={$blynkValue}";

                // ส่งคำสั่งไปยัง Blynk Server โดยใช้ cURL เพื่อความยืดหยุ่นและการจัดการข้อผิดพลาดที่ดีกว่า
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $blynkUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // ไม่แสดงผลลัพธ์ออกไป
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); // กำหนด timeout 5 วินาที
                $blynk_response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // รับ HTTP response code
                curl_close($ch);

                // ตรวจสอบว่าส่งคำสั่งสำเร็จหรือไม่
                if ($blynk_response !== false && $http_code >= 200 && $http_code < 300) {
                    if ($action === 'open') {
                        echo "OPEN";
                    } else {
                        echo "CLOSED";
                    }
                } else {
                    echo "ERROR: Failed to send command to Blynk. Check internet connection or Blynk API. (HTTP Code: {$http_code}, Response: {$blynk_response})";
                }
            } else {
                // หากเงื่อนไขไม่ถูกต้อง (ล็อกเกอร์ไม่ได้ถูกจองโดยผู้ใช้นี้ หรือไม่อยู่ในสถานะ 'occupied')
                echo "ERROR: Locker is not occupied by this user or status is incorrect.";
            }
        } else {
            // ไม่พบล็อกเกอร์ด้วยหมายเลขที่ระบุ
            echo "ERROR: Locker not found.";
        }

    } catch (PDOException $e) {
        // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
        error_log("SQL Error in api_locker_control.php: " . $e->getMessage());
        echo "ERROR: Database error occurred while controlling locker.";
    }
} else {
    echo "ERROR: Missing parameters. Please provide locker_number, user_email, and action.";
}
// ไม่จำเป็นต้องปิดการเชื่อมต่อ PDO ด้วย $conn->close() เพราะ PDO จะจัดการเองเมื่อ script จบการทำงาน
?>
