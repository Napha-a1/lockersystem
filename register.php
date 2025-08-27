<?php
include 'connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } elseif (strlen($password) < 6) { 
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        try {
            $stmt_check = $conn->prepare("SELECT id FROM locker_users WHERE email = :email");
            $stmt_check->bindParam(':email', $email);
            $stmt_check->execute();

            if ($stmt_check->fetch(PDO::FETCH_ASSOC)) { 
                $error = "อีเมลนี้ถูกใช้แล้ว";
            } else {
                // บันทึกรหัสผ่านแบบ Plain text
                $stmt_insert = $conn->prepare("INSERT INTO locker_users (fullname, email, password, role, created_at) VALUES (:fullname, :email, :password, 'user', NOW())");
                $stmt_insert->bindParam(':fullname', $fullname);
                $stmt_insert->bindParam(':email', $email);
                $stmt_insert->bindParam(':password', $password); // บันทึกรหัสผ่านแบบ Plain text

                if ($stmt_insert->execute()) {
                    header("Location: login.php?success=" . urlencode("สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ"));
                    exit();
                } else {
                    $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง";
                }
            }
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage();
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 450px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2 class="card-title text-center mb-4"><i class="fas fa-user-plus me-2"></i>สร้างบัญชี</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" class="form-control" name="fullname" id="fullname" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">อีเมล</label>
                <input type="email" class="form-control" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-success">สมัครสมาชิก <i class="fas fa-user-check ms-2"></i></button>
            </div>
        </form>
        <div class="text-center mt-3">
            <p>มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
