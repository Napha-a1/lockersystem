<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

$message = '';

// ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID ไม่ถูกต้อง");
}

$id = (int)$_GET['id'];

// ดึงข้อมูลล็อกเกอร์
$stmt = $conn->prepare("SELECT * FROM lockers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$locker = $result->fetch_assoc();

if (!$locker) {
    die("ไม่พบล็อกเกอร์นี้");
}

// อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_locker'])) {
    $locker_number = trim($_POST['locker_number'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $price_per_hour = (float)($_POST['price_per_hour'] ?? 0);

    if ($locker_number !== '' && ($status === 'available' || $status === 'occupied') && $price_per_hour > 0) {
        $update = $conn->prepare("UPDATE lockers SET locker_number = ?, status = ?, price_per_hour = ? WHERE id = ?");
        $update->bind_param("ssdi", $locker_number, $status, $price_per_hour, $id);
        if ($update->execute()) {
            $message = "แก้ไขล็อกเกอร์เรียบร้อยแล้ว!";
            // ดึงข้อมูลล่าสุด
            $locker['locker_number'] = $locker_number;
            $locker['status'] = $status;
            $locker['price_per_hour'] = $price_per_hour;
        } else {
            $message = "เกิดข้อผิดพลาดในการแก้ไข!";
        }
    } else {
        $message = "กรุณากรอกข้อมูลให้ถูกต้องครบถ้วน";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขล็อกเกอร์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 2rem; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<div class="container">
    <h2>✏️ แก้ไขล็อกเกอร์</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card p-3 mb-4">
        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">หมายเลขล็อกเกอร์</label>
                <input type="text" class="form-control" name="locker_number" value="<?= htmlspecialchars($locker['locker_number']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">สถานะ</label>
                <select class="form-select" name="status" required>
                    <option value="available" <?= $locker['status'] === 'available' ? 'selected' : '' ?>>ว่าง</option>
                    <option value="occupied" <?= $locker['status'] === 'occupied' ? 'selected' : '' ?>>ถูกใช้งาน</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">ราคาต่อชั่วโมง</label>
                <input type="number" step="0.01" class="form-control" name="price_per_hour" value="<?= htmlspecialchars($locker['price_per_hour']) ?>" required>
            </div>
            <div class="col-12 d-grid mt-3">
                <button type="submit" name="update_locker" class="btn btn-success">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>

    <div class="mt-3">
        <a href="admin_manage_lockers.php" class="btn btn-secondary">⬅ กลับไปหน้าล็อกเกอร์</a>
        <a href="admin_logout.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>
</body>
</html>
