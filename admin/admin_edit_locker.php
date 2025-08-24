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
    $blynk_virtual_pin = trim($_POST['blynk_virtual_pin'] ?? ''); // GPIO Pin
    $esp32_ip_address = trim($_POST['esp32_ip_address'] ?? ''); // ESP32 IP Address

    if ($locker_number !== '' && ($status === 'available' || $status === 'reserved' || $status === 'occupied') && $price_per_hour > 0) {
        try {
            // Check for duplicate locker_number, excluding the current locker being edited
            $stmt_check_duplicate = $conn->prepare("SELECT id FROM lockers WHERE locker_number = :locker_number AND id != :id");
            $stmt_check_duplicate->bindParam(':locker_number', $locker_number);
            $stmt_check_duplicate->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check_duplicate->execute();

            if ($stmt_check_duplicate->fetch(PDO::FETCH_ASSOC)) {
                $message = "error: หมายเลขล็อกเกอร์นี้มีอยู่แล้ว!";
            } else {
                $update_sql = "UPDATE lockers SET locker_number = :locker_number, status = :status, price_per_hour = :price_per_hour, blynk_virtual_pin = :blynk_virtual_pin, esp32_ip_address = :esp32_ip_address WHERE id = :id";
                $stmt_update = $conn->prepare($update_sql);
                $stmt_update->bindParam(':locker_number', $locker_number);
                $stmt_update->bindParam(':status', $status);
                $stmt_update->bindParam(':price_per_hour', $price_per_hour, PDO::PARAM_STR);
                $stmt_update->bindParam(':blynk_virtual_pin', $blynk_virtual_pin);
                $stmt_update->bindParam(':esp32_ip_address', $esp32_ip_address); // Bind new parameter
                $stmt_update->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    $message = "success: แก้ไขข้อมูลล็อกเกอร์เรียบร้อยแล้ว!";
                    // ดึงข้อมูลล็อกเกอร์อีกครั้งเพื่อแสดงข้อมูลที่อัปเดต
                    $stmt = $conn->prepare("SELECT * FROM lockers WHERE id = :id");
                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $locker = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = "error: เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $stmt_update->errorInfo()[2];
                }
            }
        } catch (PDOException $e) {
            error_log("SQL Error updating locker data: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดของฐานข้อมูลในการอัปเดตข้อมูลล็อกเกอร์";
        }
    } else {
        $message = "error: กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง";
    }
}
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
      background-color: #007bff !important; /* Primary Blue */
      box-shadow: 0 2px 4px rgba(0,0,0,.1);
    }
    .navbar-brand {
      font-weight: bold;
    }
    .container h4 {
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
    .btn-secondary {
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
        font-weight: bold;
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
<body class="bg-light">

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid container">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-box-open"></i> Locker System (Admin)
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
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
    <div class="card mx-auto shadow" style="max-width: 700px;">
        <div class="card-body p-4">
            <h4 class="card-title text-center mb-4 text-primary"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?></h4>

            <?php if (!empty($message)): ?>
                <?php $alert_class = (strpos($message, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?>
                <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars(str_replace(['success:', 'error:'], '', $message)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="locker_number" class="form-label">หมายเลขล็อกเกอร์</label>
                    <input type="text" class="form-control" id="locker_number" name="locker_number" value="<?= htmlspecialchars($locker['locker_number']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">สถานะ</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="available" <?= ($locker['status'] == 'available') ? 'selected' : '' ?>>ว่าง</option>
                        <option value="occupied" <?= ($locker['status'] == 'occupied') ? 'selected' : '' ?>>ใช้งาน</option>
                        <!-- 'reserved' status is optional, depending on your system's flow -->
                        <option value="reserved" <?= ($locker['status'] == 'reserved') ? 'selected' : '' ?>>จองแล้ว (รอใช้งาน)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง</label>
                    <input type="number" step="0.01" class="form-control" id="price_per_hour" name="price_per_hour" value="<?= htmlspecialchars($locker['price_per_hour']) ?>" required min="0.01">
                </div>
                <div class="col-md-6">
                    <label for="blynk_virtual_pin" class="form-label">GPIO Pin (บน ESP32)</label>
                    <input type="text" class="form-control" id="blynk_virtual_pin" name="blynk_virtual_pin" value="<?= htmlspecialchars($locker['blynk_virtual_pin'] ?? '') ?>" placeholder="เช่น 2, 4, 16" required>
                    <small class="form-text text-muted">ใช้เป็นหมายเลข GPIO Pin บน ESP32 ที่เชื่อมต่อกับรีเลย์</small>
                </div>
                <div class="col-md-6">
                    <label for="esp32_ip_address" class="form-label">ESP32 IP Address</label>
                    <input type="text" class="form-control" id="esp32_ip_address" name="esp32_ip_address" value="<?= htmlspecialchars($locker['esp32_ip_address'] ?? '') ?>" placeholder="เช่น 192.168.1.100">
                    <small class="form-text text-muted">IP Address ของ ESP32 ที่ควบคุมล็อกเกอร์นี้ (เช่น 192.168.1.100)</small>
                </div>
                <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                    <button type="submit" name="update_locker" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>บันทึกการแก้ไข</button>
                    <a href="admin_manage_lockers.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>กลับ</a>
                </div>
            </form>
        </div>
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
