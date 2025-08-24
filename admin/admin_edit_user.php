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
$stmt = $conn->prepare("SELECT * FROM locker_users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("ไม่พบผู้ใช้");
}

$user = $result->fetch_assoc();

// อัปเดตข้อมูลผู้ใช้
if (isset($_POST['update_user'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // รหัสผ่านใหม่ (อาจเว้นว่าง)

    if (empty($fullname) || empty($email)) {
        $message = "กรุณากรอกชื่อ-นามสกุลและอีเมลให้ครบถ้วน";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // ตรวจสอบรูปแบบอีเมล
        $message = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // ตรวจสอบซ้ำอีเมล (ยกเว้นตัวผู้ใช้คนนี้)
        $stmt_check = $conn->prepare("SELECT * FROM locker_users WHERE email=? AND id<>?");
        $stmt_check->bind_param("si", $email, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $message = "อีเมลนี้มีอยู่แล้วในระบบ";
        } else {
            $update_sql = "UPDATE locker_users SET fullname = ?, email = ? ";
            $params = [$fullname, $email];
            $types = "ss";

            if (!empty($password)) {
                if (strlen($password) < 6) { // ตรวจสอบความยาวรหัสผ่านใหม่
                    $message = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql .= ", password = ? ";
                    $params[] = $hashed_password;
                    $types .= "s";
                }
            }

            if (empty($message)) { // ถ้าไม่มีข้อผิดพลาดเกี่ยวกับรหัสผ่าน
                $update_sql .= "WHERE id = ?";
                $params[] = $user_id;
                $types .= "i";

                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update === false) {
                    $message = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
                } else {
                    $stmt_update->bind_param($types, ...$params);
                    if ($stmt_update->execute()) {
                        $message = "อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว!";
                        // ดึงข้อมูลผู้ใช้ล่าสุดมาแสดงผล
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                    } else {
                        $message = "เกิดข้อผิดพลาดในการอัปเดต: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
            }
        }
        $stmt_check->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขผู้ใช้</title>
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
            background-color: #2c3e50 !important; /* Darker blue for admin nav */
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand {
            font-weight: bold;
            color: white;
        }
        .container h2 {
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
        .form-control {
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
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid container">
      <a class="navbar-brand" href="booking_stats.php">
        <i class="fas fa-cogs"></i> Admin Dashboard
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin" aria-controls="navbarNavAdmin" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNavAdmin">
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
    <h2 class="mb-4"><i class="fas fa-user-edit me-2"></i>แก้ไขผู้ใช้: <?= htmlspecialchars($user['fullname']) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4">
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

<!-- Footer -->
<footer class="footer text-center">
    <div class="container">
      <p class="mb-0">&copy; <?= date('Y') ?> Locker System Admin. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
