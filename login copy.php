<?php
session_start();
include 'connect.php';

$error = '';
$success = $_GET['success'] ?? ''; // สำหรับแสดงข้อความจาก register.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'user'; // default user

    if ($role === "admin") {
        // ล็อกอินแอดมินด้วย username
        $sql = "SELECT * FROM admins WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
    } else {
        // ล็อกอินผู้ใช้ด้วย email
        $sql = "SELECT * FROM locker_users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();

            if ($role === "admin") {
                if ($password === $row['password']) {
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = 'admin';
                    header("Location: admin/booking_stats.php");
                    exit();
                } else {
                    $error = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_email'] = $row['email']; 
                    $_SESSION['role'] = 'user';
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "รหัสผ่านไม่ถูกต้อง";
                }
            }
        } else {
            $error = "ไม่พบผู้ใช้งาน";
        }

        $stmt->close();
    } else {
        $error = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ</title>
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
    <h2>เข้าสู่ระบบ</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">ประเภทผู้ใช้:</label>
            <select name="role" class="form-select">
                <option value="user">ผู้ใช้</option>
                <option value="admin">แอดมิน</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">ชื่อผู้ใช้ (แอดมิน) / อีเมล (ผู้ใช้):</label>
            <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">รหัสผ่าน:</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
        </div>
    </form>

    <div class="mt-3 text-center">
        <span>ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></span>
    </div>
</div>
</body>
</html>
