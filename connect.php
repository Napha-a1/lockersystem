<?php
$servername = getenv('DB_HOST') ?: 'localhost'; // ดึงค่าจาก ENV หรือใช้ default
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'your_database_name'; // เปลี่ยนเป็นชื่อ DB ของคุณถ้าใช้ default

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่า Character Set เป็น UTF-8
$conn->set_charset("utf8mb4");
?>
