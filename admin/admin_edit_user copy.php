<?php
session_start();
if (!isset($_SESSION['admin'])) {
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
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password']; // รหัสผ่านใหม่ (อาจเว้นว่าง)

    // ตรวจสอบซ้ำอีเมล
    $stmt_check = $conn->prepare("SELECT * FROM locker_users WHERE email=? AND id<>?");
    $stmt_check->bind_param("si", $email, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $message = "อีเมลนี้มีผู้ใช้อยู่แล้ว!";
    } else {
        if (!empty($password)) {
            // ถ้ามีการกรอกรหัสผ่านใหม่ ให้เข้ารหัส
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE locker_users SET fullname=?, email=?, password=? WHERE id=?");
            $stmt_update->bind_param("sssi", $fullname, $email, $password_hash, $user_id);
        } else {
            // ถ้าไม่กรอก รหัสผ่านเดิมไม่เปลี่ยน
            $stmt_update = $conn->prepare("UPDATE locker_users SET fullname=?, email=? WHERE id=?");
            $stmt_update->bind_param("ssi", $fullname, $email, $user_id);
        }

        if ($stmt_update->execute()) {
            $message = "อัปเดตผู้ใช้เรียบร้อยแล้ว!";
            $user['fullname'] = $fullname;
            $user['email'] = $email;
        } else {
            $message = "เกิดข้อผิดพลาด!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 2rem; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container">
    <h2 class="mb-4">✏️ แก้ไขผู้ใช้: <?= htmlspecialchars($user['fullname']) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <div class="card p-4">
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">อีเมล</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่านใหม่">
            </div>
            <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                <button type="submit" name="update_user" class="btn btn-success">💾 บันทึกการแก้ไข</button>
                <a href="admin_manage_users.php" class="btn btn-secondary">⬅ กลับ</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
