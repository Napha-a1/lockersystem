<?php
header('Content-Type: text/plain'); // กำหนด Content-Type เป็น plain text สำหรับการตอบกลับ
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

// กำหนด Auth Token ของ Blynk ของคุณที่นี่
// *** สำคัญ: คุณต้องเปลี่ยน 'YOUR_BLYNK_AUTH_TOKEN' เป็นโทเค็นจริงของคุณ ***
$blynkAuthToken = 'xeyFkCCbd3qwRgpnRtrWX_z16qx1uxm9'; 

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

    // ป้องกัน SQL Injection โดยใช้ Prepared Statement เพื่อดึงข้อมูลล็อกเกอร์
    $sql = "SELECT status, user_email, blynk_virtual_pin FROM lockers WHERE locker_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $lockerNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $status = $row['status'];
        $bookedBy = $row['user_email'];
        $blynkVirtualPin = $row['blynk_virtual_pin']; // ดึงข้อมูล Virtual Pin

        // ตรวจสอบเงื่อนไขในการควบคุมล็อกเกอร์
        // ล็อกเกอร์ต้องถูกจองโดยผู้ใช้คนนี้และอยู่ในสถานะ 'occupied'
        if ($status == 'occupied' && $bookedBy == $userEmail) {
            // กำหนดค่า Virtual Pin ที่จะส่งไปยัง Blynk
            // 1 สำหรับเปิด, 0 สำหรับปิด
            $blynkValue = ($action === 'open') ? 1 : 0;
            $blynkUrl = "https://blynk.cloud/external/api/update?token={$blynkAuthToken}&v{$blynkVirtualPin}={$blynkValue}";

            // ส่งคำสั่งไปยัง Blynk Server
            // @file_get_contents ใช้เพื่อไม่แสดง warning หากเกิดข้อผิดพลาดในการเชื่อมต่อ
            $blynk_response = @file_get_contents($blynkUrl);

            // ตรวจสอบว่าส่งคำสั่งสำเร็จหรือไม่
            if ($blynk_response !== false) {
                if ($action === 'open') {
                    echo "OPEN";
                } else {
                    echo "CLOSED";
                }
            } else {
                echo "ERROR: Failed to send command to Blynk. Check internet connection or Blynk API.";
            }
        } else {
            // หากเงื่อนไขไม่ถูกต้อง (ล็อกเกอร์ไม่ได้ถูกจองโดยผู้ใช้นี้ หรือไม่อยู่ในสถานะ 'occupied')
            echo "ERROR: Locker is not occupied by this user or status is incorrect.";
        }
    } else {
        // ไม่พบล็อกเกอร์ด้วยหมายเลขที่ระบุ
        echo "ERROR: Locker not found.";
    }

    $stmt->close();
} else {
    // พารามิเตอร์ไม่ครบถ้วน
    echo "ERROR: Required parameters (locker_number, user_email, action) are missing.";
}

$conn->close();
?>
