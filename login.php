<?php
session_start();
// เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL
include 'connect.php';

$error = '';
$success = $_GET['success'] ?? ''; // สำหรับแสดงข้อความจาก register.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'user'; // default user

    $sql = "";
    if ($role === "admin") {
        // ล็อกอินแอดมินด้วย username
        $sql = "SELECT * FROM admins WHERE username = :username";
    } else {
        // ล็อกอินผู้ใช้ด้วย email
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

        if ($row) { // ถ้าพบผู้ใช้
            if ($role === "admin") {
                // ตรวจสอบรหัสผ่านสำหรับแอดมิน (สมมติว่าไม่ได้ hash)
                if ($password === $row['password']) {
                    $_SESSION['admin_username'] = $row['username'];
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $error = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                // ตรวจสอบรหัสผ่านสำหรับผู้ใช้ โดยใช้ password_hash
                // ตรวจสอบว่ามีค่า password_hash หรือไม่
                if (isset($row['password_hash']) && $row['password_hash'] !== null) {
                    if (password_verify($password, $row['password_hash'])) {
                        // ถ้าถูกต้อง
                        $_SESSION['user_email'] = $row['email'];
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "รหัสผ่านไม่ถูกต้อง";
                    }
                } else {
                    // กรณีที่ไม่มี password_hash ให้ตรวจสอบกับคอลัมน์ password เดิม
                    // และทำการอัปเดต password_hash ทันที
                    if ($password === $row['password']) {
                         // แฮชรหัสผ่านใหม่
                        $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // อัปเดตฐานข้อมูลด้วยรหัสผ่านใหม่ที่แฮชแล้ว
                        $update_sql = "UPDATE locker_users SET password_hash = :new_hashed_password WHERE id = :id";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bindParam(':new_hashed_password', $new_hashed_password);
                        $update_stmt->bindParam(':id', $row['id']);
                        $update_stmt->execute();
                        
                        $_SESSION['user_email'] = $row['email'];
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "รหัสผ่านไม่ถูกต้อง";
                    }
                }
            }
        } else {
            // ไม่พบชื่อผู้ใช้หรืออีเมลในระบบ
            $error = "ชื่อผู้ใช้หรืออีเมลไม่ถูกต้อง";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการเข้าสู่ระบบ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #3b82f6;
            border-color: #3b82f6;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">เข้าสู่ระบบ</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
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
