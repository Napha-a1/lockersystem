<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    header("Location: admin_login.php");
    exit();
}

include '../connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

$message = '';

// เพิ่มล็อกเกอร์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_locker'])) {
    $locker_number = isset($_POST['locker_number']) ? trim($_POST['locker_number']) : '';
    $price_per_hour = isset($_POST['price_per_hour']) ? (float)$_POST['price_per_hour'] : 0;
    // ไม่มีการรับค่า blynk_virtual_pin และ esp32_ip_address สำหรับล็อกเกอร์ที่ 'ออฟไลน์'
    // เราจะตั้งค่าเป็น NULL หรือค่าว่างในฐานข้อมูลโดยตรง

    if ($locker_number !== '' && $price_per_hour > 0) {
        try {
            // ตรวจสอบหมายเลขล็อกเกอร์ซ้ำ
            $stmt_check_locker = $conn->prepare("SELECT id FROM lockers WHERE locker_number = :locker_number");
            $stmt_check_locker->bindParam(':locker_number', $locker_number);
            $stmt_check_locker->execute();

            if ($stmt_check_locker->fetch(PDO::FETCH_ASSOC)) {
                $message = "error: หมายเลขล็อกเกอร์นี้มีอยู่ในระบบแล้ว!";
            } else {
                // เพิ่มล็อกเกอร์ใหม่โดยตั้งค่า esp32_ip_address และ blynk_virtual_pin เป็น NULL
                $stmt_insert = $conn->prepare("INSERT INTO lockers (locker_number, status, price_per_hour, esp32_ip_address, blynk_virtual_pin) VALUES (:locker_number, 'available', :price_per_hour, NULL, NULL)");
                $stmt_insert->bindParam(':locker_number', $locker_number);
                $stmt_insert->bindParam(':price_per_hour', $price_per_hour);

                if ($stmt_insert->execute()) {
                    $message = "success: เพิ่มล็อกเกอร์ #{$locker_number} เรียบร้อยแล้ว (สถานะออฟไลน์)";
                } else {
                    $message = "error: เกิดข้อผิดพลาดในการเพิ่มล็อกเกอร์";
                }
            }
        } catch (PDOException $e) {
            error_log("SQL Error adding locker: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage();
        }
    } else {
        $message = "error: กรุณากรอกข้อมูลหมายเลขล็อกเกอร์และราคาต่อชั่วโมงให้ถูกต้อง";
    }
}

// ดึงข้อมูลล็อกเกอร์ทั้งหมด
$lockers = [];
try {
    $stmt = $conn->query("SELECT id, locker_number, status, user_email, start_time, end_time, price_per_hour, blynk_virtual_pin, esp32_ip_address FROM lockers ORDER BY locker_number ASC");
    $lockers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching lockers for admin: " . $e->getMessage());
    $message = "error: เกิดข้อผิดพลาดในการดึงข้อมูลล็อกเกอร์";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการล็อกเกอร์ - แอดมิน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .main-container { padding-top: 2rem; padding-bottom: 2rem; }
        .card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .table thead th { background-color: #343a40; color: white; }
        .btn-square-sm { width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
        .status-offline { color: #6c757d; font-style: italic; } /* สำหรับสถานะออฟไลน์ */
    </style>
</head>
<body>

<div class="container-fluid main-container">
    <div class="row mb-4">
        <div class="col text-center">
            <h2 class="text-primary"><i class="fas fa-cogs me-2"></i>จัดการล็อกเกอร์</h2>
            <nav class="nav justify-content-center">
                <a class="nav-link btn btn-outline-primary mx-1" href="booking_stats.php"><i class="fas fa-chart-line me-2"></i>สรุปภาพรวม</a>
                <a class="nav-link btn btn-primary mx-1" href="admin_manage_lockers.php"><i class="fas fa-box me-2"></i>จัดการล็อกเกอร์</a>
                <a class="nav-link btn btn-outline-primary mx-1" href="admin_manage_users.php"><i class="fas fa-users me-2"></i>จัดการผู้ใช้</a>
                <a class="nav-link btn btn-outline-danger mx-1" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a>
            </nav>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <h4 class="card-title mb-3">เพิ่มล็อกเกอร์ใหม่</h4>
        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'success') !== false ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label for="locker_number" class="form-label">หมายเลขล็อกเกอร์</label>
                <input type="text" class="form-control" id="locker_number" name="locker_number" required placeholder="เช่น L1, L2">
            </div>
            <div class="col-md-4">
                <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง (฿)</label>
                <input type="number" step="0.01" class="form-control" id="price_per_hour" name="price_per_hour" required value="30.00">
            </div>
            <div class="col-12">
                <button type="submit" name="add_locker" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>เพิ่มล็อกเกอร์</button>
            </div>
        </form>
    </div>

    <div class="card p-4">
        <h4 class="card-title mb-3">รายการล็อกเกอร์ทั้งหมด</h4>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>หมายเลขล็อกเกอร์</th>
                        <th>สถานะ</th>
                        <th>ผู้ใช้ที่จอง</th>
                        <th>เวลาเริ่ม</th>
                        <th>เวลาสิ้นสุด</th>
                        <th>ราคา/ชม. (฿)</th>
                        <th>สถานะเชื่อมต่อ</th> <!-- คอลัมน์ใหม่ -->
                        <th>ESP32 IP</th>
                        <th>Blynk Pin</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lockers)): ?>
                        <?php foreach ($lockers as $locker): ?>
                            <tr>
                                <td><?= htmlspecialchars($locker['id']) ?></td>
                                <td><?= htmlspecialchars($locker['locker_number']) ?></td>
                                <td>
                                    <?php if ($locker['status'] === 'available'): ?>
                                        <span class="badge bg-success">ว่าง</span>
                                    <?php elseif ($locker['status'] === 'occupied'): ?>
                                        <span class="badge bg-warning text-dark">ไม่ว่าง</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($locker['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($locker['user_email'] ?? '-') ?></td>
                                <td><?= $locker['start_time'] ? date('d/m/Y H:i', strtotime($locker['start_time'])) : '-' ?></td>
                                <td><?= $locker['end_time'] ? date('d/m/Y H:i', strtotime($locker['end_time'])) : '-' ?></td>
                                <td><?= number_format($locker['price_per_hour'], 2) ?></td>
                                <td>
                                    <?php if (!empty($locker['esp32_ip_address'])): ?>
                                        <span class="badge bg-primary"><i class="fas fa-globe me-1"></i>ออนไลน์</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-unlink me-1"></i>ออฟไลน์</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($locker['esp32_ip_address'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($locker['blynk_virtual_pin'] ?? '-') ?></td>
                                <td>
                                    <a href="admin_edit_locker.php?id=<?= htmlspecialchars($locker['id']) ?>" class="btn btn-info btn-square-sm me-1" title="แก้ไข"><i class="fas fa-edit"></i></a>
                                    <button type="button" class="btn btn-danger btn-square-sm delete-locker-btn" data-id="<?= htmlspecialchars($locker['id']) ?>" data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">ไม่พบล็อกเกอร์ในระบบ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLockerModal" tabindex="-1" aria-labelledby="deleteLockerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-light text-dark">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteLockerModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="lead mb-0">คุณแน่ใจหรือไม่ว่าต้องการลบล็อกเกอร์หมายเลข <strong id="deleteLockerNumber"></strong>?</p>
                <small class="text-muted">การดำเนินการนี้จะลบข้อมูลที่เกี่ยวข้องกับการจองทั้งหมดของล็อกเกอร์นี้</small>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" class="btn btn-danger rounded-pill px-4" id="confirmDeleteButton">ลบ</a>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="text-center mt-5 p-3 bg-light text-muted">
    &copy; <?= date('Y') ?> Locker System Admin. All rights reserved.
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Show delete confirmation modal
    $('.delete-locker-btn').on('click', function() {
        const lockerId = $(this).data('id');
        const lockerNumber = $(this).data('locker-number');
        $('#deleteLockerNumber').text(lockerNumber);
        $('#confirmDeleteButton').attr('href', 'admin_delete_locker.php?id=' + lockerId);
        var deleteLockerModal = new bootstrap.Modal(document.getElementById('deleteLockerModal'));
        deleteLockerModal.show();
    });
});
</script>
</body>
</html>
