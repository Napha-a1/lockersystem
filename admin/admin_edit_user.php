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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "error: รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        try {
            $update_sql = "UPDATE locker_users SET fullname = :fullname, email = :email";
            
            // ถ้ามีการกรอกรหัสผ่านใหม่ ก็จะอัปเดตด้วย
            if (!empty($password)) {
                $update_sql .= ", password = :password"; // บันทึกแบบ Plain Text
            }
            $update_sql .= " WHERE id = :user_id";

            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bindParam(':fullname', $fullname);
            $stmt_update->bindParam(':email', $email);
            if (!empty($password)) {
                $stmt_update->bindParam(':password', $password); // ผูกรหัสผ่านแบบ Plain Text
            }
            $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                $message = "success: อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว!";
                // โหลดข้อมูลผู้ใช้อีกครั้งหลังจากอัปเดต
                $stmt = $conn->prepare("SELECT * FROM locker_users WHERE id = :user_id");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "error: เกิดข้อผิดพลาดในการอัปเดตข้อมูลผู้ใช้";
            }
        } catch (PDOException $e) {
            error_log("SQL Error updating user data: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขผู้ใช้ - แอดมิน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .main-container { padding-top: 2rem; padding-bottom: 2rem; }
        .card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .form-label { font-weight: 500; }
    </style>
</head>
<body>

<div class="container main-container">
    <div class="card p-4">
        <h3 class="card-title mb-4 text-center text-primary"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้: <?= htmlspecialchars($user['fullname']) ?></h3>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'success') !== false ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
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
<footer class="footer text-center mt-5 p-3 bg-light text-muted">
    &copy; <?= date('Y') ?> Locker System Admin. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
