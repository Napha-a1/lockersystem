<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

$message = '';

// เพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password']; // รหัสผ่าน

    // ตรวจสอบอีเมลซ้ำ
    $check = $conn->prepare("SELECT * FROM locker_users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $result_check = $check->get_result();

    if ($result_check->num_rows > 0) {
        $message = "อีเมลนี้มีอยู่แล้ว!";
    } else {
        // เข้ารหัสรหัสผ่าน
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // เพิ่มผู้ใช้
        $stmt = $conn->prepare("INSERT INTO locker_users (fullname, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $fullname, $email, $password_hash);
        if ($stmt->execute()) {
            $message = "เพิ่มผู้ใช้เรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาด!";
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$users = $conn->query("SELECT * FROM locker_users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            padding: 2rem;
            font-family: 'Segoe UI', sans-serif;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h2 {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>👥 จัดการผู้ใช้</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <!-- ฟอร์มเพิ่มผู้ใช้ -->
    <div class="card p-3 mb-4">
        <h5>➕ เพิ่มผู้ใช้ใหม่</h5>
        <form method="post" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="fullname" placeholder="ชื่อ-นามสกุล" required>
            </div>
            <div class="col-md-3">
                <input type="email" class="form-control" name="email" placeholder="อีเมล" required>
            </div>
            <div class="col-md-4">
                <input type="password" class="form-control" name="password" placeholder="รหัสผ่าน" required>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" name="add_user" class="btn btn-success">เพิ่ม</button>
            </div>
        </form>
    </div>

    <!-- ตารางแสดงผู้ใช้ -->
    <div class="card p-3">
        <h5>📋 รายชื่อผู้ใช้ทั้งหมด</h5>
        <table class="table table-striped table-hover mt-3">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>อีเมล</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= $user['fullname'] ?></td>
                        <td><?= $user['email'] ?></td>
                        <td>
                            <a href="admin_edit_user.php?id=<?= $user['id'] ?>" class="btn btn-warning btn-sm">แก้ไข</a>
                            <a href="admin_delete_user.php?id=<?= $user['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('คุณแน่ใจว่าต้องการลบผู้ใช้นี้?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($users->num_rows == 0): ?>
                    <tr>
                        <td colspan="4" class="text-center">ไม่มีผู้ใช้ในระบบ</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <a href="booking_stats.php" class="btn btn-secondary">⬅ กลับไปหน้า Dashboard</a>
        <a href="admin_logout.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>

</body>
</html>
