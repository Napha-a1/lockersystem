<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบเพื่อยกเลิกการจอง"));
    exit();
}

// ตรวจสอบว่ามีการส่งค่า locker_id มาหรือไม่
if (!isset($_POST['locker_id'])) {
    header("Location: index.php?error=" . urlencode("ไม่พบข้อมูลล็อกเกอร์ที่ต้องการยกเลิก"));
    exit();
}

$user_email = $_SESSION['user_email'];
$locker_id = $_POST['locker_id'];

try {
    // เริ่ม Transaction เพื่อให้การทำงานเป็น Atomicity
    $conn->beginTransaction();

    // 1. ตรวจสอบสิทธิ์การยกเลิก: ต้องเป็นล็อกเกอร์ที่ผู้ใช้คนนี้จองอยู่และสถานะเป็น 'occupied' เท่านั้น
    // ใช้ SELECT ... FOR UPDATE เพื่อป้องกัน race condition
    $stmt_check = $conn->prepare("SELECT id FROM lockers WHERE id = :id AND user_email = :user_email AND status = 'occupied' FOR UPDATE");
    $stmt_check->bindParam(':id', $locker_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $stmt_check->execute();
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        // ถ้าไม่พบข้อมูล แสดงว่าไม่มีสิทธิ์ในการยกเลิกหรือสถานะไม่ถูกต้อง
        $conn->rollBack(); // Rollback transaction
        header("Location: index.php?error=" . urlencode("คุณไม่สามารถยกเลิกการจองล็อกเกอร์นี้ได้"));
        exit();
    }

    // 2. อัปเดตสถานะล็อกเกอร์ในตาราง 'lockers' ให้กลับเป็น 'available'
    $update_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :id";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bindParam(':id', $locker_id, PDO::PARAM_INT);
    
    if (!$update_stmt->execute()) {
        $conn->rollBack(); // Rollback transaction
        throw new Exception("เกิดข้อผิดพลาดในการอัปเดตสถานะล็อกเกอร์");
    }

    // Commit Transaction เมื่อทุกอย่างสำเร็จ
    $conn->commit();

    // Redirect กลับไปหน้าหลักพร้อมข้อความสำเร็จ
    header("Location: index.php?success=" . urlencode("ยกเลิกการจองล็อกเกอร์สำเร็จแล้ว"));
    exit();

} catch (Exception $e) {
    // Rollback Transaction หากมีข้อผิดพลาดเกิดขึ้น
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Cancellation Error in cancel_booking.php: " . $e->getMessage()); // บันทึกข้อผิดพลาดสำหรับ Debug
    header("Location: index.php?error=" . urlencode("เกิดข้อผิดพลาดในการยกเลิกการจอง: " . $e->getMessage()));
    exit();
} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("FATAL PDO Error in cancel_booking.php: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode("เกิดข้อผิดพลาดในการติดต่อฐานข้อมูล"));
    exit();
}
