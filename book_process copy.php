<?php 
session_start();
include 'connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_email'])) {
    die("
    <div class='d-flex justify-content-center align-items-center vh-100'>
        <div class='card text-center border-danger shadow p-4'>
            <h3 class='text-danger mb-3'>❌ กรุณาเข้าสู่ระบบก่อน</h3>
            <a href='login.php' class='btn btn-danger mt-3'>กลับไปล็อกอิน</a>
        </div>
    </div>");
}

// รับค่าจากฟอร์ม
$locker_id = $_POST['locker_id'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];

// ตรวจสอบเวลาเริ่มและเวลาสิ้นสุด
if (strtotime($start_time) >= strtotime($end_time)) {
    die("
    <div class='d-flex justify-content-center align-items-center vh-100'>
        <div class='card text-center border-warning shadow p-4'>
            <h3 class='text-warning mb-3'>⚠️ เวลาจองไม่ถูกต้อง</h3>
            <a href='book_locker.php' class='btn btn-warning mt-3'>กลับไปแก้ไข</a>
        </div>
    </div>");
}

// ดึงข้อมูลล็อกเกอร์
$stmt = $conn->prepare("SELECT status, price_per_hour FROM lockers WHERE id = ?");
$stmt->bind_param("i", $locker_id);
$stmt->execute();
$stmt->bind_result($status, $price_per_hour);
$stmt->fetch();
$stmt->close();

if ($status !== 'available') {
    die("
    <div class='d-flex justify-content-center align-items-center vh-100'>
        <div class='card text-center border-danger shadow p-4'>
            <h3 class='text-danger mb-3'>❌ ล็อกเกอร์นี้ถูกจองแล้ว</h3>
            <a href='book_locker.php' class='btn btn-danger mt-3'>เลือกล็อกเกอร์ใหม่</a>
        </div>
    </div>");
}

// คำนวณจำนวนชั่วโมง
$start = new DateTime($start_time);
$end = new DateTime($end_time);
$diff = $start->diff($end);
$hours = ceil($diff->h + ($diff->days * 24));
$total_price = $hours * $price_per_hour;

// ดึงอีเมลผู้ใช้จาก session
$user_email = $_SESSION['user_email'];

// อัปเดตข้อมูลการจอง
$update = $conn->prepare("UPDATE lockers SET status = 'reserved', user_email = ?, start_time = ?, end_time = ? WHERE id = ?");
$update->bind_param("sssi", $user_email, $start_time, $end_time, $locker_id);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ผลการจองล็อกเกอร์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .card {
            max-width: 500px;
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .btn-home {
            width: 100%;
        }
    </style>
</head>
<body>
<div class="d-flex justify-content-center align-items-center w-100">
<?php
if ($update->execute()) {
    echo "
    <div class='card shadow border-success p-4 text-center'>
        <h3 class='text-success mb-4'> จองสำเร็จ</h3>
        <ul class='list-group list-group-flush text-start mb-3'>
            <li class='list-group-item'><strong>อีเมลผู้จอง:</strong> {$user_email}</li>
            <li class='list-group-item'><strong>หมายเลขล็อกเกอร์:</strong> {$locker_id}</li>
            <li class='list-group-item'><strong>เวลาเริ่ม:</strong> {$start_time}</li>
            <li class='list-group-item'><strong>เวลาสิ้นสุด:</strong> {$end_time}</li>
            <li class='list-group-item'><strong>จำนวนชั่วโมง:</strong> {$hours} ชั่วโมง</li>
            <li class='list-group-item fs-5'><strong>ยอดชำระ:</strong> " . number_format($total_price, 2) . " บาท</li>
        </ul>
        <a href='index.php' class='btn btn-success btn-lg mt-3 w-100'>กลับหน้าหลัก</a>
    </div>
    ";
} else {
    echo "
    <div class='card shadow border-danger p-4 text-center'>
        <h3 class='text-danger mb-3'>❌ เกิดข้อผิดพลาด</h3>
        <p>" . $conn->error . "</p>
        <a href='book_locker.php' class='btn btn-danger btn-lg mt-3 w-100'>กลับไปจองใหม่</a>
    </div>
    ";
}
$update->close();
$conn->close();
?>
</div>
</body>
</html>
