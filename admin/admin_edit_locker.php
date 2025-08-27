<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
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
try {
    $stmt = $conn->prepare("SELECT * FROM lockers WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $locker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$locker) {
        die("ไม่พบล็อกเกอร์นี้");
    }
} catch (PDOException $e) {
    error_log("SQL Error fetching locker data: " . $e->getMessage());
    die("เกิดข้อผิดพลาดของฐานข้อมูลในการดึงข้อมูลล็อกเกอร์");
}


// อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_locker'])) {
    $locker_number = trim($_POST['locker_number'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $price_per_hour = (float)($_POST['price_per_hour'] ?? 0);
    // esp32_ip_address และ blynk_virtual_pin สามารถเว้นว่างได้ (สำหรับล็อกเกอร์ออฟไลน์)
    $blynk_virtual_pin = trim($_POST['blynk_virtual_pin'] ?? NULL); 
    $esp32_ip_address = trim($_POST['esp32_ip_address'] ?? NULL); 
    
    // ตั้งค่าเป็น NULL หากค่าว่างเปล่า
    if (empty($blynk_virtual_pin)) $blynk_virtual_pin = NULL;
    if (empty($esp32_ip_address)) $esp32_ip_address = NULL;

    if (empty($locker_number) || empty($status) || $price_per_hour <= 0) {
        $message = "error: กรุณากรอกข้อมูลหมายเลขล็อกเกอร์, สถานะ และราคาต่อชั่วโมงให้ครบถ้วน";
    } else {
        try {
            $update_sql = "
                UPDATE lockers 
                SET locker_number = :locker_number, 
                    status = :status, 
                    price_per_hour = :price_per_hour,
                    blynk_virtual_pin = :blynk_virtual_pin,
                    esp32_ip_address = :esp32_ip_address
                WHERE id = :id
            ";
            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bindParam(':locker_number', $locker_number);
            $stmt_update->bindParam(':status', $status);
            $stmt_update->bindParam(':price_per_hour', $price_per_hour);
            $stmt_update->bindParam(':blynk_virtual_pin', $blynk_virtual_pin);
            $stmt_update->bindParam(':esp32_ip_address', $esp32_ip_address);
            $stmt_update->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                $message = "success: อัปเดตข้อมูลล็อกเกอร์เรียบร้อยแล้ว!";
                // โหลดข้อมูลล็อกเกอร์อีกครั้งหลังจากอัปเดต
                $stmt = $conn->prepare("SELECT * FROM lockers WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $locker = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "error: เกิดข้อผิดพลาดในการอัปเดตข้อมูลล็อกเกอร์";
            }
        } catch (PDOException $e) {
            error_log("SQL Error updating locker data: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขล็อกเกอร์ - แอดมิน</title>
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
        <h3 class="card-title mb-4 text-center text-primary"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?></h3>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'success') !== false ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label for="locker_number" class="form-label">หมายเลขล็อกเกอร์</label>
                <input type="text" class="form-control" id="locker_number" name="locker_number" value="<?= htmlspecialchars($locker['locker_number'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="available" <?= ($locker['status'] === 'available') ? 'selected' : '' ?>>ว่าง</option>
                    <option value="occupied" <?= ($locker['status'] === 'occupied') ? 'selected' : '' ?>>ไม่ว่าง (ถูกใช้งาน)</option>
                    <option value="maintenance" <?= ($locker['status'] === 'maintenance') ? 'selected' : '' ?>>อยู่ระหว่างซ่อมบำรุง</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง (฿)</label>
                <input type="number" step="0.01" class="form-control" id="price_per_hour" name="price_per_hour" value="<?= htmlspecialchars($locker['price_per_hour'] ?? '0.00') ?>" required>
            </div>
            <div class="col-md-6">
                <label for="blynk_virtual_pin" class="form-label">Virtual Pin (สำหรับอุปกรณ์)</label>
                <input type="text" class="form-control" id="blynk_virtual_pin" name="blynk_virtual_pin" value="<?= htmlspecialchars($locker['blynk_virtual_pin'] ?? '') ?>" placeholder="เช่น V1, V2 หรือ GPIO Pin">
                <small class="form-text text-muted">ใช้สำหรับควบคุมฮาร์ดแวร์ (เว้นว่างถ้าเป็นล็อกเกอร์ออฟไลน์)</small>
            </div>
            <div class="col-md-6">
                <label for="esp32_ip_address" class="form-label">ESP32 IP Address</label>
                <input type="text" class="form-control" id="esp32_ip_address" name="esp32_ip_address" value="<?= htmlspecialchars($locker['esp32_ip_address'] ?? '') ?>" placeholder="เช่น 192.168.1.100">
                <small class="form-text text-muted">IP Address ของ ESP32 ที่ควบคุมล็อกเกอร์นี้ (เว้นว่างถ้าเป็นล็อกเกอร์ออฟไลน์)</small>
            </div>
            <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                <button type="submit" name="update_locker" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>บันทึกการแก้ไข</button>
                <a href="admin_manage_lockers.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>กลับ</a>
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
