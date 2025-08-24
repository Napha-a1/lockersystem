<?php
session_start();
include '../connect.php'; // ตรวจสอบว่าพาธนี้ถูกต้อง

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ดึงรหัสผ่านที่เข้ารหัสแล้วจากฐานข้อมูล
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username=?");
    $stmt->bind_param("s", $username); // 's' สำหรับ string
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        // ตรวจสอบรหัสผ่านที่ผู้ใช้ป้อนกับรหัสผ่านที่ถูกเข้ารหัสในฐานข้อมูล
        // ใช้ password_verify() สำหรับรหัสผ่านที่ถูก hashed เท่านั้น
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin'] = $row['username']; 
            header("Location: booking_stats.php");
            exit();
        } else {
            // ถ้า password_verify() ไม่ผ่าน แสดงว่ารหัสผ่านไม่ถูกต้อง
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"; // ข้อความนี้ครอบคลุมทั้ง username/password ไม่ตรง
        }
    } else {
        // ไม่พบ username ในฐานข้อมูล
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"; // ข้อความนี้ครอบคลุมทั้ง username/password ไม่ตรง
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบแอดมิน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #071932, #023c4d);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-box {
            background: #fff;
            padding: 2rem 3rem;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>เข้าสู่ระบบแอดมิน</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <div class="mb-3">
            <label for="username" class="form-label">ชื่อผู้ใช้</label>
            <input type="text" class="form-control" name="username" id="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">รหัสผ่าน</label>
            <input type="password" class="form-control" name="password" id="password" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
        </div>
    </form>
</div>

</body>
</html>
