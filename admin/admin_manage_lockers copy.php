<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

$message = '';

// เพิ่มล็อกเกอร์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_locker'])) {
    $locker_number = isset($_POST['locker_number']) ? trim($_POST['locker_number']) : '';
    $price_per_hour = isset($_POST['price_per_hour']) ? (float)$_POST['price_per_hour'] : 0;

    if ($locker_number !== '' && $price_per_hour > 0) {
        $stmt = $conn->prepare("INSERT INTO lockers (locker_number, status, price_per_hour) VALUES (?, 'available', ?)");
        $stmt->bind_param("sd", $locker_number, $price_per_hour);
        if ($stmt->execute()) {
            $message = "เพิ่มล็อกเกอร์เรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาด!";
        }
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

// ดึงข้อมูลล็อกเกอร์ทั้งหมด
$lockers = $conn->query("SELECT * FROM lockers ORDER BY id DESC");
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการล็อกเกอร์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 2rem; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container">
    <h2>📦 จัดการล็อกเกอร์</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- ฟอร์มเพิ่มล็อกเกอร์ -->
    <div class="card p-3 mb-4">
        <h5>➕ เพิ่มล็อกเกอร์ใหม่</h5>
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control" name="locker_number" placeholder="หมายเลขล็อกเกอร์" required>
            </div>
            <div class="col-md-4">
                <input type="number" step="0.01" class="form-control" name="price_per_hour" placeholder="ราคาต่อชั่วโมง" required>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" name="add_locker" class="btn btn-success">เพิ่มล็อกเกอร์</button>
            </div>
        </form>
    </div>

    <!-- ตารางแสดงล็อกเกอร์ -->
    <div class="card p-3">
        <h5>📋 รายการล็อกเกอร์ทั้งหมด</h5>
        <table class="table table-striped table-hover mt-3">
            <thead>
                <tr>
                    <th>#</th>
                    <th>หมายเลขล็อกเกอร์</th>
                    <th>สถานะ</th>
                    <th>ราคาต่อชั่วโมง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($locker = $lockers->fetch_assoc()): ?>
                    <tr>
                        <td><?= $locker['id'] ?></td>
                        <td><?= htmlspecialchars($locker['locker_number']) ?></td>
                        <td><?= htmlspecialchars($locker['status']) ?></td>
                        <td><?= number_format($locker['price_per_hour'], 2) ?></td>
                        <td>
                            <a href="admin_edit_locker.php?id=<?= $locker['id'] ?>" class="btn btn-warning btn-sm">แก้ไข</a>
                            <a href="admin_delete_locker.php?id=<?= $locker['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('คุณแน่ใจว่าต้องการลบล็อกเกอร์นี้?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($lockers->num_rows == 0): ?>
                    <tr>
                        <td colspan="5" class="text-center">ไม่มีล็อกเกอร์ในระบบ</td>
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
