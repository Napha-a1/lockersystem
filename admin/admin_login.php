<?php
session_start();
include '../connect.php'; // ตรวจสอบว่าพาธนี้ถูกต้อง

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // ตรวจสอบรหัสผ่านแบบ Plain Text (ตามความต้องการของคุณ)
            if ($password === $row['password']) {
                $_SESSION['admin_username'] = $row['username'];
                $_SESSION['role'] = 'admin';
                header("Location: booking_stats.php"); // ไปยังหน้าแดชบอร์ดแอดมิน
                exit();
            } else {
                $error = "รหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error = "ไม่พบผู้ใช้หรือชื่อผู้ใช้ไม่ถูกต้อง";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล";
        error_log("SQL Error in admin_login.php: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบแอดมิน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            animation: fadeIn 0.8s ease-out;
            width: 100%;
            max-width: 400px;
        }
        .login-box h2 {
            color: #343a40;
            margin-bottom: 30px;
            font-weight: bold;
            text-align: center;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 12px;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004d99;
        }
        .alert {
            border-radius: 8px;
            animation: fadeIn 0.5s ease-out;
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
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg">เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-2"></i></button>
        </div>
    </form>
    <div class="text-center mt-3">
        <!-- ลิงก์กลับไปหน้าหลัก (ถ้ามี) หรือข้อมูลเพิ่มเติม -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
