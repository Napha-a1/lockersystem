    <?php
    // ไฟล์นี้ใช้สำหรับสร้าง Hashed Password สำหรับผู้ดูแลระบบใหม่
    // หลังจากใช้งานเสร็จสิ้นแล้ว ควรลบไฟล์นี้ทิ้งเพื่อความปลอดภัย!

    $new_admin_plain_password = "1234"; // <<< รหัสผ่านที่เราจะใช้ทดสอบ
    $hashed_password = password_hash($new_admin_plain_password, PASSWORD_DEFAULT);

    echo "Plain Password (สำหรับทดสอบ): " . $new_admin_plain_password . "<br>";
    echo "Hashed Password (ใช้สำหรับบันทึกในฐานข้อมูล): " . $hashed_password . "<br>";
    ?>
    