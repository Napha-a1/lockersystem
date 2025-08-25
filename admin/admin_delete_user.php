<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header("Location: admin_login.php");
    exit();
}

include '../connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID ไม่ถูกต้อง"); // ใช้ die สำหรับข้อผิดพลาดร้ายแรงที่หยุดการทำงานของสคริปต์
}

$id = (int)$_GET['id'];

try {
    // ลบผู้ใช้
    $stmt = $conn->prepare("DELETE FROM locker_users WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // ลบสำเร็จ → redirect กลับหน้าจัดการผู้ใช้ พร้อมข้อความ
        header("Location: admin_manage_users.php?message=" . urlencode("ลบผู้ใช้งานเรียบร้อยแล้ว!"));
        exit();
    } else {
        // ลบไม่สำเร็จ
        // errorInfo()[2] ให้ข้อความ error จาก PDO
        header("Location: admin_manage_users.php?message=" . urlencode("เกิดข้อผิดพลาดในการลบผู้ใช้งาน: " . $stmt->errorInfo()[2]));
        exit();
    }
} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
    error_log("SQL Error in admin_delete_user.php: " . $e->getMessage());
    header("Location: admin_manage_users.php?message=" . urlencode("เกิดข้อผิดพลาดของฐานข้อมูลในการลบผู้ใช้งาน!"));
    exit();
}
?>
