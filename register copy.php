<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "locker_system_web"; // ให้ตรงกับฐานข้อมูลจริง

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        // ตรวจสอบอีเมลซ้ำ
        $stmt = $conn->prepare("SELECT id FROM locker_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "อีเมลนี้ถูกใช้แล้ว";
        } else {
            // แฮชรหัสผ่าน
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // เพิ่มผู้ใช้ใหม่
            $stmt = $conn->prepare("INSERT INTO locker_users (fullname, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $fullname, $email, $hashed_password);

            if ($stmt->execute()) {
                // สมัครสำเร็จ → redirect ไป login.php พร้อมข้อความ
                header("Location: login.php?success=" . urlencode("สมัครสมาชิกสำเร็จแล้ว! กรุณาเข้าสู่ระบบ"));
                exit();
            } else {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>สมัครสมาชิก</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .card-title { font-weight: bold; }
  </style>
</head>
<body>
<div class="container py-5">
  <h2 class="mb-4 text-center">สมัครสมาชิกผู้ใช้งาน</h2>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card p-4">
        <h5 class="card-title">กรอกข้อมูลเพื่อสมัครสมาชิก</h5>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
          <div class="mb-3">
            <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
            <input type="text" class="form-control" name="fullname" required>
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">อีเมล</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">รหัสผ่าน</label>
            <input type="password" class="form-control" name="password" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
            <input type="password" class="form-control" name="confirm_password" required>
          </div>
          <button type="submit" class="btn btn-success w-100">สมัครสมาชิก</button>
        </form>

        <div class="mt-3 text-center">
          <span>มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></span>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
