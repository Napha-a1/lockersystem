<?php
session_start();
if (!isset($_SESSION['user_email']) && !isset($_SESSION['admin_username'])) {
  header('Location: login.php');
  exit();
}

include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ดึงข้อมูลล็อกเกอร์ทั้งหมด
$lockers = [];
try {
    $stmt = $conn->prepare("SELECT id, locker_number, status, user_email, start_time, end_time, price_per_hour FROM lockers ORDER BY locker_number ASC");
    $stmt->execute();
    $lockers = $stmt->fetchAll(PDO::FETCH_ASSOC); // ดึงข้อมูลทั้งหมดในรูปแบบ associative array
} catch (PDOException $e) {
    error_log("SQL Error fetching locker status: " . $e->getMessage());
    header('Location: error.php?message=' . urlencode('เกิดข้อผิดพลาดในการดึงข้อมูลสถานะล็อกเกอร์'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สถานะล็อกเกอร์</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8f9fa;
    }
    .main-container {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
    .table-container {
        overflow-x: auto;
    }
    .footer {
        padding: 1rem;
        background-color: #e9ecef;
        position: fixed;
        left: 0;
        bottom: 0;
        width: 100%;
        text-align: center;
        color: #6c757d;
    }
    .table thead th {
        background-color: #343a40;
        color: white;
    }
    .table tbody tr:nth-child(odd) {
        background-color: #f2f2f2;
    }
    .status-available { color: #28a745; }
    .status-occupied { color: #dc3545; }
  </style>
</head>
<body>

<div class="container main-container">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white text-center">
            <h3><i class="fas fa-lock me-2"></i>สถานะล็อกเกอร์ทั้งหมด</h3>
        </div>
        <div class="card-body">
            <div class="text-end mb-3">
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a>
            </div>
            <div class="table-container">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">หมายเลขล็อกเกอร์</th>
                            <th scope="col">สถานะ</th>
                            <th scope="col">ผู้ใช้ที่จอง</th>
                            <th scope="col">เวลาเริ่ม</th>
                            <th scope="col">เวลาสิ้นสุด</th>
                            <th scope="col">ราคาต่อชั่วโมง (฿)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lockers)): ?>
                            <?php foreach ($lockers as $index => $row): ?>
                                <tr>
                                    <th scope="row"><?= $index + 1 ?></th>
                                    <td><?= htmlspecialchars($row['locker_number']) ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'available'): ?>
                                            <span class="status-available"><i class="fas fa-check-circle me-1"></i>ว่าง</span>
                                        <?php else: ?>
                                            <span class="status-occupied"><i class="fas fa-times-circle me-1"></i>ไม่ว่าง</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['user_email'])): ?>
                                            <?= htmlspecialchars($row['user_email']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $row['start_time'] ? date('d/m/Y H:i', strtotime($row['start_time'])) : '-' ?></td>
                                    <td><?= $row['end_time'] ? date('d/m/Y H:i', strtotime($row['end_time'])) : '-' ?></td>
                                    <td><?= number_format($row['price_per_hour'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">ไม่มีล็อกเกอร์ในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>กลับหน้าหลัก</a>
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
