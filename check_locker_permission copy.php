<?php
// ไฟล์นี้ใช้ตรวจสอบสิทธิ์การเปิดล็อกเกอร์
session_start();
include('connect.php'); // ใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิม

header('Content-Type: text/plain');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ โดยใช้ $_SESSION['user_email']
if (!isset($_SESSION['user_email'])) { // แก้ไขจาก $_SESSION['email'] เพื่อความสอดคล้อง
    echo "ERROR: User not logged in.";
    exit();
}

if (!isset($_GET['locker_number'])) {
    echo "ERROR: Locker number not specified.";
    exit();
}

// ใช้ $_SESSION['user_email'] ให้สอดคล้องกับส่วนอื่นๆ ของระบบ
$userEmail = $_SESSION['user_email']; // แก้ไขจาก $_SESSION['email']
$lockerNumber = $_GET['locker_number'];

// ตรวจสอบว่าผู้ใช้คนนี้ได้จองล็อกเกอร์นี้ไว้หรือไม่ และสถานะเป็น 'occupied'
// ค้นหาในตาราง `lockers` โดยตรงเพื่อตรวจสอบสถานะการจองปัจจุบัน
$check_booking_sql = "SELECT * FROM lockers WHERE user_email = ? AND locker_number = ? AND status = 'occupied'";
$stmt = $conn->prepare($check_booking_sql);
// ใช้ "ss" สำหรับ bind_param เพราะทั้ง userEmail และ lockerNumber เป็น string
$stmt->bind_param("ss", $userEmail, $lockerNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ถ้าพบการจอง แสดงว่าผู้ใช้มีสิทธิ์
    echo "OK";
} else {
    // ถ้าไม่พบการจอง แสดงว่าไม่มีสิทธิ์
    echo "ERROR: No valid booking found for this user or locker is not occupied.";
}

$stmt->close();
$conn->close();
?>
