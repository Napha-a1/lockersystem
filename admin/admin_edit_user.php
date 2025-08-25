<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

$message = '';

// ตรวจสอบว่ามีการส่ง `id` มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid User ID");
}

$user_id = (int)$_GET['id'];

// ดึงข้อมูลผู้ใช้ตาม ID
try {
    $stmt = $conn->prepare("SELECT * FROM locker_users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("ไม่พบผู้ใช้");
    }
} catch (PDOException $e) {
    error_log("SQL Error fetching user data: " . $e->getMessage());
    die("เกิดข้อผิดพลาดของฐานข้อมูลในการดึงข้อมูลผู้ใช้");
}


// อัปเดตข้อมูลผู้ใช้
if (isset($_POST['update_user'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // รหัสผ่านใหม่ (อาจเว้นว่าง)

    if (empty($fullname) || empty($email)) {
        $message = "error: กรุณากรอกชื่อ-นามสกุลและอีเมลให้ครบถ้วน";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // ตรวจสอบรูปแบบอีเมล
        $message = "error: รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        try {
            // ตรวจสอบอีเมลซ้ำ ยกเว้นผู้ใช้ปัจจุบัน
            $stmt_check_email = $conn->prepare("SELECT id FROM locker_users WHERE email = :email AND id != :user_id");
            $stmt_check_email->bindParam(':email', $email);
            $stmt_check_email->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check_email->execute();

            if ($stmt_check_email->fetch(PDO::FETCH_ASSOC)) {
                $message = "error: อีเมลนี้ถูกใช้โดยผู้ใช้งานอื่นแล้ว";
            } else {
                $update_sql = "UPDATE locker_users SET fullname = :fullname, email = :email";
                $params = [
                    ':fullname' => $fullname,
                    ':email' => $email,
                    ':user_id' => $user_id
                ];

                if (!empty($password)) {
                    // แฮชรหัสผ่านใหม่หากมีการกรอก
                    if (strlen($password) < 6) {
                        $message = "error: รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $update_sql .= ", password = :password";
                        $params[':password'] = $hashed_password;
                    }
                }
                
                // ถ้าไม่มีข้อความ error จากความยาวรหัสผ่าน
                if (empty($message)) {
                    $update_sql .= " WHERE id = :user_id";
                    $stmt_update = $conn->prepare($update_sql);
                    
                    foreach ($params as $key => $val) {
                        if ($key == ':user_id') {
                            $stmt_update->bindValue($key, $val, PDO::PARAM_INT);
                        } else if ($key == ':password') {
                            $stmt_update->bindValue($key, $val, PDO::PARAM_STR);
                        } else {
                            $stmt_update->bindValue($key, $val);
                        }
                    }

                    if ($stmt_update->execute()) {
                        $message = "success: แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว!";
                        // ดึงข้อมูลผู้ใช้อีกครั้งเพื่อแสดงข้อมูลที่อัปเดต
                        $stmt = $conn->prepare("SELECT * FROM locker_users WHERE id = :user_id");
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $message = "error: เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $stmt_update->errorInfo()[2];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("SQL Error updating user data: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดของฐานข้อมูลในการอัปเดตข้อมูลผู้ใช้";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แก้ไขผู้ใช้งาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      background-color: #f8f9fa; /* Light background */
      font-family: 'Inter', sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .navbar {
      background-color: #007bff !important; /* Primary Blue */
      box-shadow: 0 2px 4px rgba(0,0,0,.1);
    }
    .navbar-brand {
      font-weight: bold;
    }
    .container h4 {
      font-weight: bold;
      color: #007bff;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .form-label {
        font-weight: 600;
        color: #495057;
    }
    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.75rem 1rem;
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
    .btn-secondary {
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
        font-weight: bold;
    }
    .footer {
      background-color: #343a40; /* Dark Grey */
      color: white;
      padding: 1rem 0;
      position: relative;
      bottom: 0;
      width: 100%;
      margin-top: auto; /* Push footer to the bottom */
    }
  </style>
</head>
<body class="bg-light">

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid container">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-box-open"></i> Locker System (Admin)
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <span class="nav-link text-white">ยินดีต้อนรับ: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white" href="admin_logout.php">
              <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

<div class="container py-5 flex-grow-1">
    <div class="card mx-auto shadow" style="max-width: 700px;">
        <div class="card-body p-4">
            <h4 class="card-title text-center mb-4 text-primary"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h4>

            <?php if (!empty($message)): ?>
                <?php $alert_class = (strpos($message, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?>
                <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars(str_replace(['success:', 'error:'], '', $message)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" name="fullname" id="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">อีเมล</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="รหัสผ่านใหม่">
                </div>
                <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                    <button type="submit" name="update_user" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>บันทึกการแก้ไข</button>
                    <a href="admin_manage_users.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>กลับ</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer text-center">
    <div class="container">
      <p class="mb-0">&copy; <?= date('Y') ?> Locker System. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
