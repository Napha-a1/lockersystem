<?php
// กำหนดค่าตัวแปรสภาพแวดล้อมหรือค่าเริ่มต้น
$host = getenv('DB_HOST') ?: 'dpg-d2lcdpvdiees73bu6hbg-a';
$username = getenv('DB_USERNAME') ?: 'lockersystem';
$password = getenv('DB_PASSWORD') ?: '7WBobcxBBAKdkShjvprNnWQPQIQ4bOMb';
$dbname = getenv('DB_DATABASE') ?: 'lockersystem';
$port = getenv('DB_PORT') ?: 5432; // ดึงค่าพอร์ต หรือใช้ค่าเริ่มต้น 5432 สำหรับ PostgreSQL

// สร้าง DSN (Data Source Name) สำหรับ PostgreSQL
// ระบุ host, port, dbname และ charset (utf8)
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;options='--client_encoding=UTF8'";

$conn = null; // กำหนดค่าเริ่มต้นเป็น null

try {
    // สร้างการเชื่อมต่อ PDO กับ PostgreSQL
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // กำหนดให้ PDO โยน Exception เมื่อเกิดข้อผิดพลาด
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // กำหนดโหมดการดึงข้อมูลเริ่มต้นเป็น Associative array
        PDO::ATTR_EMULATE_PREPARES => false, // แนะนำให้ปิดเพื่อความปลอดภัยและประสิทธิภาพ
    ]);
    // ไม่จำเป็นต้องเรียก set_charset สำหรับ PDO กับ PostgreSQL โดยตรง เพราะระบุใน DSN แล้ว
    // และ PostgreSQL โดยทั่วไปจะใช้ UTF-8 เป็นค่าเริ่มต้น

} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดในการเชื่อมต่อ
    error_log("Connection failed: " . $e->getMessage());
    // แสดงข้อความผิดพลาดและหยุดการทำงาน
    die("Connection failed: " . $e->getMessage());
}

// ตอนนี้ $conn คือออบเจกต์ PDO ที่พร้อมใช้งานแล้ว
// คุณสามารถใช้ $conn เพื่อดำเนินการกับฐานข้อมูล PostgreSQL ได้เลย
?>
