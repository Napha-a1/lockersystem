<?php
// Get environment variables or set default values
$servername = getenv('DB_HOST') ?: 'dpg-d2lcdpvdiees73bu6hbg-a';
$username = getenv('DB_USERNAME') ?: 'lockersystem';
$password = getenv('DB_PASSWORD') ?: '7WBobcxBBAKdkShjvprNnWQPQIQ4bOMb';
$dbname = getenv('DB_DATABASE') ?: 'lockersystem'; 
$port = getenv('DB_PORT') ?: 5432; // เพิ่มบรรทัดนี้เพื่อดึงค่าพอร์ต

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port); // แก้ไขบรรทัดนี้

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $conn->error);
}
?>
