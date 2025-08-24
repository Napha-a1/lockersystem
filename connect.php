<?php
// connect.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "locker_system_web"; // ชื่อฐานข้อมูลของคุณ

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}
?>
