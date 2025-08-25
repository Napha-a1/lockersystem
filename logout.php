<?php
session_start();        // เริ่ม session
session_unset();        // ลบค่า session ทั้งหมด
session_destroy();      // ทำลาย session
header("Location: login.php"); // กลับไปหน้า login
exit();
?>
