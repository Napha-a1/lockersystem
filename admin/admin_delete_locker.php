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

// ลบล็อกเกอร์
$stmt = $conn->prepare("DELETE FROM lockers WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // ลบสำเร็จ
    header("Location: admin_manage_lockers.php?message=" . urlencode("ลบล็อกเกอร์เรียบร้อยแล้ว!"));
    exit();
} else {
    // ลบไม่สำเร็จ
    header("Location: admin_manage_lockers.php?message=" . urlencode("เกิดข้อผิดพลาดในการลบล็อกเกอร์"));
    exit();
}
?>
