<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

// ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID ไม่ถูกต้อง");
}

$id = (int)$_GET['id'];

// ลบผู้ใช้
$stmt = $conn->prepare("DELETE FROM locker_users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // ลบสำเร็จ → redirect กลับหน้าจัดการผู้ใช้ พร้อมข้อความ
    header("Location: admin_manage_users.php?message=" . urlencode("ลบผู้ใช้งานเรียบร้อยแล้ว!"));
    exit();
} else {
    // ลบไม่สำเร็จ
    header("Location: admin_manage_users.php?message=" . urlencode("เกิดข้อผิดพลาดในการลบผู้ใช้งาน"));
    exit();
}
?>
