<?php
// migrate_users.php
// ใช้ไฟล์นี้เพียงครั้งเดียวเพื่อย้ายรหัสผ่านผู้ใช้เก่า
// จากคอลัมน์ 'password' ไปยัง 'password_hash' ที่ถูกต้อง

include 'connect.php';

try {
    // 1. ดึงข้อมูลผู้ใช้ทั้งหมดที่ยังไม่มี password_hash
    $stmt_fetch = $conn->prepare("SELECT id, password FROM locker_users WHERE password_hash IS NULL");
    $stmt_fetch->execute();
    $users_to_migrate = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users_to_migrate)) {
        echo "ไม่มีผู้ใช้ที่ต้อง migrate ข้อมูลแล้ว.";
        exit;
    }

    // 2. เตรียมคำสั่งสำหรับอัปเดตข้อมูล
    $stmt_update = $conn->prepare("UPDATE locker_users SET password_hash = :hashed_password WHERE id = :id");

    foreach ($users_to_migrate as $user) {
        $user_id = $user['id'];
        $plain_password = $user['password'];

        // แฮชรหัสผ่านด้วยฟังก์ชันของ PHP
        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

        // อัปเดตข้อมูล
        $stmt_update->bindParam(':hashed_password', $hashed_password);
        $stmt_update->bindParam(':id', $user_id);
        $stmt_update->execute();

        echo "อัปเดตรหัสผ่านสำหรับ User ID: " . htmlspecialchars($user_id) . " เรียบร้อย<br>";
    }

    echo "การ Migrate ข้อมูลทั้งหมดเสร็จสมบูรณ์แล้ว.";

} catch (PDOException $e) {
    error_log("Migration Error: " . $e->getMessage());
    echo "เกิดข้อผิดพลาดในการ Migrate ข้อมูล กรุณาตรวจสอบ Log: " . htmlspecialchars($e->getMessage());
}
?>
