<?php
// ไฟล์นี้ใช้ตรวจสอบสิทธิ์การเปิดล็อกเกอร์
session_start();
include('connect.php'); // ใช้ไฟล์เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

header('Content-Type: text/plain');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ โดยใช้ $_SESSION['user_email']
if (!isset($_SESSION['user_email'])) {
    echo "ERROR: User not logged in.";
    exit();
}

if (!isset($_GET['locker_number'])) {
    echo "ERROR: Locker number not specified.";
    exit();
}

// ใช้ $_SESSION['user_email'] ให้สอดคล้องกับส่วนอื่นๆ ของระบบ
$userEmail = $_SESSION['user_email'];
$lockerNumber = $_GET['locker_number'];

try {
    // ตรวจสอบว่าผู้ใช้คนนี้ได้จองล็อกเกอร์นี้ไว้หรือไม่ และสถานะเป็น 'occupied'
    // ค้นหาในตาราง `lockers` โดยตรงเพื่อตรวจสอบสถานะการจองปัจจุบัน
    $check_booking_sql = "SELECT id FROM lockers WHERE user_email = :user_email AND locker_number = :locker_number AND status = 'occupied'";
    $stmt = $conn->prepare($check_booking_sql);
    if ($stmt === false) {
        error_log("SQL prepare failed: " . $conn->errorInfo()[2]); // บันทึก error ของ PDO
        echo "ERROR: SQL prepare failed.";
        exit();
    }
    // ใช้ bindParam สำหรับ PDO
    $stmt->bindParam(':user_email', $userEmail);
    $stmt->bindParam(':locker_number', $lockerNumber);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC); // ดึงข้อมูลหนึ่งแถว

    if ($result) { // ถ้าพบข้อมูล แสดงว่ามีสิทธิ์
        echo "PERMITTED"; // มีสิทธิ์
    } else {
        echo "ERROR: You do not have permission to control this locker or it's not currently occupied by you."; // ไม่มีสิทธิ์
    }

} catch (PDOException $e) {
    error_log("SQL Error in check_locker_permission.php: " . $e->getMessage());
    echo "ERROR: Database error occurred while checking permission.";
    exit();
}
// ไม่จำเป็นต้องปิดการเชื่อมต่อ PDO ด้วย $conn->close() เพราะ PDO จะจัดการเองเมื่อ script จบการทำงาน
?>
