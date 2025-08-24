<?php 
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบก่อน"));
    exit();
}

// รับค่าจากฟอร์ม
$locker_id = $_POST['locker_id'] ?? null;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;
$user_email = $_SESSION['user_email']; // ใช้จาก session เพื่อความปลอดภัย

// ตรวจสอบว่าได้รับข้อมูลครบถ้วนหรือไม่
if (empty($locker_id) || empty($start_time) || empty($end_time)) {
    header("Location: book_locker.php?error=" . urlencode("ข้อมูลการจองไม่ครบถ้วน!"));
    exit();
}

// ตรวจสอบเวลาเริ่มและเวลาสิ้นสุด
if (strtotime($start_time) >= strtotime($end_time)) {
    header("Location: book_locker.php?error=" . urlencode("เวลาจองไม่ถูกต้อง! เวลาสิ้นสุดต้องหลังเวลาเริ่ม"));
    exit();
}

try {
    // ดึงข้อมูลล็อกเกอร์ (รวมถึง locker_number ด้วย)
    $stmt = $conn->prepare("SELECT locker_number, status, price_per_hour FROM lockers WHERE id = :locker_id");
    $stmt->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
    $stmt->execute();
    $locker_data = $stmt->fetch(PDO::FETCH_ASSOC);

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
    $update_locker_sql = "UPDATE lockers SET status = 'occupied', user_email = :user_email, start_time = :start_time, end_time = :end_time WHERE id = :locker_id";
    $update = $conn->prepare($update_locker_sql);
    $update->bindParam(':user_email', $user_email);
    $update->bindParam(':start_time', $start_time);
    $update->bindParam(':end_time', $end_time);
    $update->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);

    if ($update->execute()) {
        // บันทึกการจองลงในตาราง bookings
        $insert_booking_sql = "INSERT INTO bookings (locker_id, user_email, start_time, end_time, total_price) VALUES (:locker_id, :user_email, :start_time, :end_time, :total_price)";
        $insert_booking = $conn->prepare($insert_booking_sql);
        $insert_booking->bindParam(':locker_id', $locker_id, PDO::PARAM_INT);
        $insert_booking->bindParam(':user_email', $user_email);
        $insert_booking->bindParam(':start_time', $start_time);
        $insert_booking->bindParam(':end_time', $end_time);
        $insert_booking->bindParam(':total_price', $total_price, PDO::PARAM_STR); // ใช้ PARAM_STR สำหรับ decimal/numeric
        
        if ($insert_booking->execute()) {
            // Redirect ไปยังหน้า index.php พร้อมข้อความสำเร็จ
            header("Location: index.php?success=" . urlencode("การจองล็อกเกอร์ #" . htmlspecialchars($locker_data['locker_number']) . " สำเร็จแล้ว! ยอดชำระ: " . number_format($total_price, 2) . " บาท"));
            exit();
        } else {
            // หากบันทึก bookings ไม่สำเร็จ ให้ย้อนกลับสถานะ lockers
            // ควรมีการ rollback transaction หากใช้ transaction
            $revert_locker_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :locker_id_revert";
            $revert_locker = $conn->prepare($revert_locker_sql);
            $revert_locker->bindParam(':locker_id_revert', $locker_id, PDO::PARAM_INT);
            $revert_locker->execute();
            
            header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการบันทึกการจอง!"));
            exit();
        }
    } else {
        header("Location: book_locker.php?error=" . urlencode("ไม่สามารถอัปเดตสถานะล็อกเกอร์ได้!"));
        exit();
    }
} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
    error_log("SQL Error in book_process.php: " . $e->getMessage());
    header("Location: book_locker.php?error=" . urlencode("เกิดข้อผิดพลาดในการดำเนินการจอง โปรดลองอีกครั้ง"));
    exit();
}
// ไม่จำเป็นต้องปิดการเชื่อมต่อ PDO ด้วย $conn->close() เพราะ PDO จะจัดการเองเมื่อ script จบการทำงาน
?>
