<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
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
    $blynk_virtual_pin = trim($_POST['blynk_virtual_pin'] ?? ''); // เพิ่ม Virtual Pin

    if ($locker_number !== '' && ($status === 'available' || $status === 'occupied') && $price_per_hour > 0 && $blynk_virtual_pin !== '') {
        // ตรวจสอบหมายเลขล็อกเกอร์ซ้ำ (ยกเว้นตัวล็อกเกอร์เอง)
        $stmt_check_locker = $conn->prepare("SELECT id FROM lockers WHERE locker_number = ? AND id <> ?");
        $stmt_check_locker->bind_param("si", $locker_number, $id);
        $stmt_check_locker->execute();
        $stmt_check_locker->store_result();

        if ($stmt_check_locker->num_rows > 0) {
            $message = "หมายเลขล็อกเกอร์นี้มีอยู่ในระบบแล้ว!";
        } else {
            $update = $conn->prepare("UPDATE lockers SET locker_number = ?, status = ?, price_per_hour = ?, blynk_virtual_pin = ? WHERE id = ?");
            if ($update === false) {
                $message = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $update->bind_param("ssdsi", $locker_number, $status, $price_per_hour, $blynk_virtual_pin, $id);
                if ($update->execute()) {
                    $message = "บันทึกการแก้ไขล็อกเกอร์เรียบร้อยแล้ว!";
                    // ดึงข้อมูลล่าสุดมาแสดงผลหลังจากอัปเดต
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $locker = $result->fetch_assoc();
                } else {
                    $message = "เกิดข้อผิดพลาดในการอัปเดต: " . $update->error;
                }
                $update->close();
            }
        }
        $stmt_check_locker->close();
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขล็อกเกอร์</title>
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
    <h2 class="mb-4"><i class="fas fa-box-open me-2"></i>แก้ไขล็อกเกอร์: <?= htmlspecialchars($locker['locker_number']) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label for="locker_number" class="form-label">หมายเลขล็อกเกอร์</label>
                <input type="text" class="form-control" id="locker_number" name="locker_number" value="<?= htmlspecialchars($locker['locker_number']) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="available" <?= $locker['status'] === 'available' ? 'selected' : '' ?>>ว่าง</option>
                    <option value="occupied" <?= $locker['status'] === 'occupied' ? 'selected' : '' ?>>ถูกใช้งาน</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง</label>
                <input type="number" step="0.01" class="form-control" id="price_per_hour" name="price_per_hour" value="<?= htmlspecialchars($locker['price_per_hour']) ?>" required min="0.01">
            </div>
            <div class="col-md-6">
                <label for="blynk_virtual_pin" class="form-label">Blynk Virtual Pin</label>
                <input type="text" class="form-control" id="blynk_virtual_pin" name="blynk_virtual_pin" value="<?= htmlspecialchars($locker['blynk_virtual_pin'] ?? '') ?>" placeholder="เช่น V0, V1" required>
            </div>
            <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                <button type="submit" name="update_locker" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>บันทึกการแก้ไข</button>
                <a href="admin_manage_lockers.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>กลับ</a>
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
