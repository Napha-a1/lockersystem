<?php
// ไฟล์นี้ใช้สำหรับล้างและสร้างข้อมูลแอดมินใหม่ในตาราง 'admins'
include 'connect.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ตั้งค่าชื่อผู้ใช้และรหัสผ่านแอดมินใหม่ที่คุณต้องการ
// *** สำคัญ: เปลี่ยนค่าในตัวแปรด้านล่างนี้ตามที่คุณต้องการ! ***
$new_admin_username = 'admin'; 
$new_admin_password = '123'; 

// ตั้งค่า header เพื่อให้แสดงผลเป็นข้อความธรรมดา
header('Content-Type: text/plain; charset=utf-8');

try {
    // เริ่มต้น Transaction เพื่อให้แน่ใจว่าการลบและเพิ่มข้อมูลสำเร็จพร้อมกัน
    $conn->beginTransaction();
    echo "--- เริ่มต้นการรีเซ็ตข้อมูลแอดมิน ---\n";

    // 1. ลบข้อมูลแอดมินทั้งหมดในตาราง 'admins'
    $delete_sql = "DELETE FROM admins";
    $stmt_delete = $conn->prepare($delete_sql);
    $stmt_delete->execute();
    echo "✅ ข้อมูลแอดมินเดิมถูกลบทั้งหมดแล้ว\n";

    // 2. เพิ่มข้อมูลแอดมินใหม่
    // ใช้คำสั่ง SQL INSERT เพื่อเพิ่ม username และ password ใหม่
    $insert_sql = "INSERT INTO admins (username, password) VALUES (:username, :password)";
    $stmt_insert = $conn->prepare($insert_sql);
    $stmt_insert->bindParam(':username', $new_admin_username);
    $stmt_insert->bindParam(':password', $new_admin_password); // รหัสผ่านยังไม่ได้ถูกแฮชตามโค้ด login.php เดิม
    $stmt_insert->execute();
    echo "✅ เพิ่มบัญชีแอดมินใหม่เรียบร้อยแล้ว\n";
    echo "   - ชื่อผู้ใช้: " . htmlspecialchars($new_admin_username) . "\n";
    echo "   - รหัสผ่าน: " . htmlspecialchars($new_admin_password) . "\n";

    // 3. Commit Transaction
    $conn->commit();
    echo "--- กระบวนการรีเซ็ตเสร็จสมบูรณ์ ---\n";
    echo "ตอนนี้คุณสามารถเข้าสู่ระบบในฐานะแอดมินด้วยข้อมูลใหม่นี้ได้แล้ว\n";

} catch (PDOException $e) {
    // หากเกิดข้อผิดพลาด ให้ Rollback Transaction เพื่อป้องกันข้อมูลเสียหาย
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error during admin reset: " . $e->getMessage());
    echo "❌ เกิดข้อผิดพลาดในระหว่างดำเนินการ: " . $e->getMessage() . "\n";
    echo "กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูลและสิทธิ์ในการแก้ไขข้อมูล\n";
}
?>
