<?php
session_start();
include '../connect.php'; // ตรวจสอบว่าพาธนี้ถูกต้อง (อยู่ในโฟลเดอร์ admin)

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
            // ใช้การตรวจสอบรหัสผ่านแบบ Plain Text โดยตรง
            if ($password === $row['password']) {
                $_SESSION['admin_username'] = $row['username'];
                $_SESSION['role'] = 'admin';
                header("Location: booking_stats.php"); // Redirect ไปหน้าแดชบอร์ดแอดมิน
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .login-box {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.8s ease-out;
        }
        .login-box h2 {
            margin-bottom: 30px;
            font-weight: 700;
            color: #4a5568;
        }
        .form-control {
            border-radius: 8px;
            height: 45px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #5a67d8;
            border-color: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        .alert {
            border-radius: 8px;
            text-align: left;
            margin-bottom: 20px;
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
        <a href="../login.php" class="text-secondary text-decoration-none hover:text-primary"><i class="fas fa-arrow-left me-2"></i>กลับหน้าเข้าสู่ระบบผู้ใช้</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
