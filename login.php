<?php
// login.php
// ไฟล์สำหรับหน้าล็อกอิน
session_start();
// เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL
include 'connect.php';

$error = '';
$success = $_GET['success'] ?? ''; // สำหรับแสดงข้อความจาก register.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user'; // default user

    $sql = "";
    if ($role === "admin") {
        // ล็อกอินแอดมินด้วย username
        // ตรวจสอบกับรหัสผ่านที่ถูก hash แล้ว
        $sql = "SELECT * FROM admins WHERE username = :username";
    } else {
        // ล็อกอินผู้ใช้ด้วย email
        // ตรวจสอบกับรหัสผ่านที่ถูก hash แล้ว
        $sql = "SELECT * FROM locker_users WHERE email = :username";
    }

    try {
        // เตรียมคำสั่ง SQL
        $stmt = $conn->prepare($sql);

        // ผูกค่าพารามิเตอร์
        $stmt->bindParam(':username', $username);

        // ประมวลผลคำสั่ง
        $stmt->execute();

        // ดึงข้อมูลผู้ใช้
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) { // ถ้าพบผู้ใช้/แอดมิน
            // ใช้ password_verify() เพื่อตรวจสอบรหัสผ่านที่ถูก hash
            // นี่คือส่วนที่ปรับปรุงเพื่อความปลอดภัย
            if (password_verify($password, $row['password'])) {
                if ($role === "admin") {
                    $_SESSION['admin_username'] = $row['username'];
                    header("Location: admin_dashboard.php");
                } else {
                    $_SESSION['user_email'] = $row['email'];
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }

    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage()); // บันทึกข้อผิดพลาดใน log
        $error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่ภายหลัง";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - Locker System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #e9ecef;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .logo-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-section img {
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <i class="fas fa-lock text-primary fa-4x mb-3"></i>
            <h2 class="text-center fw-bold">เข้าสู่ระบบ</h2>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="role" class="form-label">ประเภทผู้ใช้:</label>
                <select name="role" id="role" class="form-select">
                    <option value="user">ผู้ใช้</option>
                    <option value="admin">แอดมิน</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">ชื่อผู้ใช้ (แอดมิน) / อีเมล (ผู้ใช้):</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">รหัสผ่าน:</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary">เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-2"></i></button>
            </div>
        </form>
        <div class="text-center mt-3">
            <p>ยังไม่มีบัญชีใช่ไหม? <a href="register.php">สมัครสมาชิก</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

