<?php 
session_start();
include 'connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_email'])) {
    // ใช้ header redirect แทน die() เพื่อให้ flow เป็นไปตามปกติและแสดงข้อความ error
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบก่อน"));
    exit();
}

// รับค่าจากฟอร์ม
$locker_id = $_POST['locker_id'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$user_email = $_SESSION['user_email']; // ใช้จาก session เพื่อความปลอดภัย

// ตรวจสอบเวลาเริ่มและเวลาสิ้นสุด
if (strtotime($start_time) >= strtotime($end_time)) {
    header("Location: book_locker.php?error=" . urlencode("เวลาจองไม่ถูกต้อง! เวลาสิ้นสุดต้องหลังเวลาเริ่ม"));
    exit();
}

// ดึงข้อมูลล็อกเกอร์ (รวมถึง locker_number ด้วย)
$stmt = $conn->prepare("SELECT locker_number, status, price_per_hour FROM lockers WHERE id = ?");
$stmt->bind_param("i", $locker_id);
$stmt->execute();
$result = $stmt->get_result();
$locker_data = $result->fetch_assoc();
$stmt->close();

if (!$locker_data) {
    header("Location: book_locker.php?error=" . urlencode("ไม่พบล็อกเกอร์ที่เลือก!"));
    exit();
}

if ($locker_data['status'] !== 'available') {
    header("Location: book_locker.php?error=" . urlencode("ล็อกเกอร์ #" . htmlspecialchars($locker_data['locker_number']) . " ไม่ว่างสำหรับการจอง!"));
    exit();
}

$price_per_hour = $locker_data['price_per_hour'];

// คำนวณจำนวนชั่วโมงและราคารวม
$start_timestamp = strtotime($start_time);
$end_timestamp = strtotime($end_time);
$diff_seconds = $end_timestamp - $start_timestamp;
$hours = $diff_seconds / 3600; // แปลงเป็นชั่วโมง
$total_price = $price_per_hour * $hours;

// อัปเดตสถานะล็อกเกอร์ในตาราง lockers
// กำหนด user_email, start_time, end_time ด้วย
$update_locker_sql = "UPDATE lockers SET status = 'occupied', user_email = ?, start_time = ?, end_time = ? WHERE id = ?";
$update = $conn->prepare($update_locker_sql);
$update->bind_param("sssi", $user_email, $start_time, $end_time, $locker_id);

if ($update->execute()) {
    // บันทึกการจองลงในตาราง bookings
    $insert_booking_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time, total_price) VALUES (?, ?, ?, ?, ?)";
    $insert_booking = $conn->prepare($insert_booking_sql);
    $insert_booking->bind_param("isssd", $locker_id, $user_email, $start_time, $end_time, $total_price);
    
    if ($insert_booking->execute()) {
        // Redirect ไปยังหน้า index.php พร้อมข้อความสำเร็จ
        header("Location: index.php?success=" . urlencode("การจองล็อกเกอร์ #" . htmlspecialchars($locker_data['locker_number']) . " สำเร็จแล้ว! ยอดชำระ: " . number_format($total_price, 2) . " บาท"));
        exit();
    } else {
        // หากบันทึก bookings ไม่สำเร็จ ให้ย้อนกลับสถานะ lockers
        $revert_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = ?";
        $revert_locker = $conn->prepare($revert_locker_sql);
        $revert_locker->bind_param("i", $locker_id);
        $revert_locker->execute();
        $revert_locker->close();
        
        header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการบันทึกการจอง!"));
        exit();
    }
    $insert_booking->close();
} else {
    header("Location: book_locker.php?error=" . urlencode("ไม่สามารถอัปเดตสถานะล็อกเกอร์ได้: " . $update->error));
    exit();
}
$update->close();
$conn->close();
?>
