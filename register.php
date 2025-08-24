<?php
// เชื่อมต่อฐานข้อมูล (ใช้ connect.php เพื่อความสอดคล้อง)
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
    } elseif (strlen($password) < 6) { // เพิ่มการตรวจสอบความยาวรหัสผ่าน
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        // ตรวจสอบอีเมลซ้ำ
        $stmt_check = $conn->prepare("SELECT id FROM locker_users WHERE email = ?");
        if ($stmt_check === false) {
            $error = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (ตรวจสอบอีเมล): " . $conn->error;
        } else {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error = "อีเมลนี้ถูกใช้แล้ว";
            } else {
                // แฮชรหัสผ่าน
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // เพิ่มผู้ใช้ใหม่
                $stmt_insert = $conn->prepare("INSERT INTO locker_users (fullname, email, password) VALUES (?, ?, ?)");
                if ($stmt_insert === false) {
                    $error = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (เพิ่มผู้ใช้): " . $conn->error;
                } else {
                    $stmt_insert->bind_param("sss", $fullname, $email, $hashed_password);
                    if ($stmt_insert->execute()) {
                        // สมัครสมาชิกสำเร็จ กลับไปหน้า Login พร้อมข้อความสำเร็จ
                        header("Location: login.php?success=" . urlencode("สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ"));
                        exit();
                    } else {
                        $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
            }
            $stmt_check->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สมัครสมาชิกผู้ใช้งาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      background: linear-gradient(to right, #4CAF50, #8BC34A); /* Green Gradient */
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      color: #333;
    }
    .register-box {
        background: #fff;
        padding: 2.5rem 3rem;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        width: 100%;
        max-width: 500px;
        animation: fadeIn 0.8s ease-out;
    }
    .register-box h2 {
      text-align: center;
      margin-bottom: 2rem;
      color: #28a745; /* Success green */
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
        border-color: #5cb85c;
        box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
    }
    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-success:hover {
        background-color: #218838;
        border-color: #218838;
        transform: translateY(-2px);
    }
    .alert {
        border-radius: 8px;
        font-size: 0.95rem;
    }
    .text-center a {
        color: #28a745;
        font-weight: 500;
        text-decoration: none;
    }
    .text-center a:hover {
        text-decoration: underline;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<div class="register-box">
    <h2><i class="fas fa-user-plus me-2"></i>สมัครสมาชิกผู้ใช้งาน</h2>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>