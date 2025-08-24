<?php
session_start();
include '../connect.php'; // ตรวจสอบว่าพาธนี้ถูกต้อง

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // ดึงรหัสผ่านที่เข้ารหัสแล้วจากฐานข้อมูล
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :username");
        $stmt->bindParam(':username', $username); // 's' สำหรับ string ไม่จำเป็นสำหรับ PDO ที่ใช้ named parameter
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) { // ถ้าพบผู้ใช้
            // ตรวจสอบรหัสผ่านที่ผู้ใช้ป้อนกับรหัสผ่านที่ถูกเข้ารหัสในฐานข้อมูล
            // *** แนะนำให้ใช้ password_hash และ password_verify สำหรับรหัสผ่านแอดมินด้วย ***
            // ปัจจุบันโค้ดของคุณไม่ได้ใช้ password_hash สำหรับแอดมิน, จึงเปรียบเทียบตรงๆ
            if ($password === $row['password']) { // ตรงนี้คือจุดที่ควรใช้ password_verify หากรหัสผ่านถูก hash ไว้
                $_SESSION['admin_username'] = $row['username']; // เปลี่ยนเป็น admin_username เพื่อความชัดเจน
                $_SESSION['role'] = 'admin';
                header("Location: booking_stats.php");
                exit();
            } else {
                // ถ้า password_verify() ไม่ผ่าน แสดงว่ารหัสผ่านไม่ถูกต้อง
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"; // ข้อความผิดพลาด
            }
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"; // ไม่พบชื่อผู้ใช้
        }
    } catch (PDOException $e) {
        // บันทึกข้อผิดพลาดในการประมวลผลคำสั่ง SQL
        error_log("SQL Error in admin_login.php: " . $e->getMessage());
        $error = "เกิดข้อผิดพลาดในการเข้าสู่ระบบ โปรดลองอีกครั้ง";
    }
}
// ไม่จำเป็นต้องปิดการเชื่อมต่อ PDO ด้วย $conn->close() เพราะ PDO จะจัดการเองเมื่อ script จบการทำงาน
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบผู้ดูแลระบบ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(to right, #007bff, #00c6ff); /* Blue Gradient */
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: #333;
        }
        .login-box {
            background: #fff;
            padding: 2.5rem 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.8s ease-out;
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #007bff; /* Primary blue */
            font-weight: bold;
        }
        .form-label {
            font-weight: 600;
            color: #555;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1.1rem;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 8px;
            font-size: 0.95rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2><i class="fas fa-users-cog me-2"></i>เข้าสู่ระบบแอดมิน</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <div class="mb-3">
            <label for="username" class="form-label">ชื่อผู้ใช้</label>
            <input type="text" class="form-control" name="username" id="username" required>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">รหัสผ่าน</label>
            <input type="password" class="form-control" name="password" id="password" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-2"></i></button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
