<?php
header('Content-Type: text/plain');
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

// ตรวจสอบว่ามีพารามิเตอร์ locker_number และ user_email ส่งมาหรือไม่
if (isset($_GET['locker_number']) && isset($_GET['user_email'])) {
    $lockerNumber = $_GET['locker_number'];
    $userEmail = $_GET['user_email'];

    // ตรวจสอบสิทธิ์จาก Session
    if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] !== $userEmail) {
        echo "ERROR: Permission Denied.";
        exit();
    }

    // ป้องกัน SQL Injection โดยใช้ Prepared Statement
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

        // ตรวจสอบเงื่อนไขในการเปิดล็อกเกอร์
        if ($status == 'occupied' && $bookedBy == $userEmail) { // สถานะที่ถูกต้องควรเป็น 'occupied' ไม่ใช่ 'reserved'
            
            // ส่งคำสั่งไปที่ Blynk
            $blynkAuthToken = 'xeyFkCCbd3qwRgpnRtrWX_z16qx1uxm9'; // <<< ใส่ Auth Token ของคุณที่นี่
            $blynkUrl = "https://blynk.cloud/external/api/update?token={$blynkAuthToken}&v{$blynkVirtualPin}=1";
            
            // ส่งคำสั่งไปยัง Blynk Server
            $blynk_response = @file_get_contents($blynkUrl);

            // ตรวจสอบว่าส่งคำสั่งสำเร็จหรือไม่
            if ($blynk_response !== false) {
                echo "OPEN";
            } else {
                echo "ERROR: Failed to send command to Blynk.";
            }
        } else {
            // หากเงื่อนไขไม่ถูกต้อง ให้ส่งคำสั่ง "ERROR" กลับไป
            echo "ERROR: Locker is not booked by this user or is not occupied.";
        }
    } else {
        echo "ERROR: Locker not found.";
    }
} else {
    echo "ERROR: Invalid request.";
}

$conn->close();
?>