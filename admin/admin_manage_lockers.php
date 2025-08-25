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
    $blynk_virtual_pin = isset($_POST['blynk_virtual_pin']) ? trim($_POST['blynk_virtual_pin']) : ''; // GPIO Pin
    $esp32_ip_address = isset($_POST['esp32_ip_address']) ? trim($_POST['esp32_ip_address']) : ''; // ESP32 IP Address

    if ($locker_number !== '' && $price_per_hour > 0 && $blynk_virtual_pin !== '') {
        try {
            // ตรวจสอบหมายเลขล็อกเกอร์ซ้ำ
            $stmt_check_locker = $conn->prepare("SELECT id FROM lockers WHERE locker_number = :locker_number");
            $stmt_check_locker->bindParam(':locker_number', $locker_number);
            $stmt_check_locker->execute();

            if ($stmt_check_locker->fetch(PDO::FETCH_ASSOC)) {
                $message = "error: หมายเลขล็อกเกอร์นี้มีอยู่ในระบบแล้ว!";
            } else {
                $stmt = $conn->prepare("INSERT INTO lockers (locker_number, price_per_hour, blynk_virtual_pin, esp32_ip_address) VALUES (:locker_number, :price_per_hour, :blynk_virtual_pin, :esp32_ip_address)");
                $stmt->bindParam(':locker_number', $locker_number);
                $stmt->bindParam(':price_per_hour', $price_per_hour, PDO::PARAM_STR); // ใช้ PARAM_STR สำหรับ Numeric
                $stmt->bindParam(':blynk_virtual_pin', $blynk_virtual_pin);
                $stmt->bindParam(':esp32_ip_address', $esp32_ip_address); // Bind ESP32 IP Address

                if ($stmt->execute()) {
                    $message = "success: เพิ่มล็อกเกอร์ใหม่เรียบร้อยแล้ว!";
                } else {
                    $message = "error: เกิดข้อผิดพลาดในการเพิ่มล็อกเกอร์: " . $stmt->errorInfo()[2];
                }
            }
        } catch (PDOException $e) {
            error_log("SQL Error adding new locker: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดของฐานข้อมูลในการเพิ่มล็อกเกอร์";
        }
    } else {
        $message = "error: กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง (หมายเลขล็อกเกอร์, ราคา, GPIO Pin)";
    }
}

// ดึงข้อมูลล็อกเกอร์ทั้งหมด
$lockers = [];
try {
    // เพิ่ม esp32_ip_address ใน SELECT query
    $stmt_all_lockers = $conn->prepare("SELECT id, locker_number, status, user_email, start_time, end_time, price_per_hour, blynk_virtual_pin, esp32_ip_address
                                        FROM lockers ORDER BY locker_number ASC");
    $stmt_all_lockers->execute();
    $lockers = $stmt_all_lockers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching all lockers: " . $e->getMessage());
    // อาจจะแสดงข้อความ error บนหน้าเว็บ หรือ redirect ไปหน้า error
    // header('Location: error.php?message=' . urlencode('เกิดข้อผิดพลาดในการดึงข้อมูลล็อกเกอร์'));
    // exit(); // ถ้าไม่ exit สคริปต์จะทำงานต่อแต่ $lockers จะว่างเปล่า
    $message = "error: เกิดข้อผิดพลาดของฐานข้อมูลในการดึงข้อมูลล็อกเกอร์";
}

// ไม่จำเป็นต้องปิดการเชื่อมต่อ PDO ด้วย $conn->close() เพราะ PDO จะจัดการเองเมื่อ script จบการทำงาน
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการล็อกเกอร์</title>
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
    .btn-primary, .btn-success, .btn-danger, .btn-info {
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-weight: bold;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-primary:hover, .btn-success:hover, .btn-danger:hover, .btn-info:hover {
      transform: translateY(-2px);
    }
    .status-badge {
        padding: 0.4em 0.8em;
        border-radius: 0.8rem;
        font-weight: bold;
        color: white;
        display: inline-block;
    }
    .status-available { background-color: #28a745; } /* Green */
    .status-occupied { background-color: #dc3545; } /* Red */
    .status-reserved { background-color: #ffc107; color: #333;} /* Yellow */
    .footer {
      background-color: #343a40; /* Dark Grey */
      color: white;
      padding: 1rem 0;
      position: relative;
      bottom: 0;
      width: 100%;
      margin-top: auto;
    }
    /* Modal styles */
    .modal-content {
        border-radius: 1rem;
        border: none;
    }
    .modal-header.bg-danger {
        background-color: #dc3545 !important;
        color: white;
        border-top-left-radius: 1rem;
        border-top-right-radius: 1rem;
    }
    .btn-close-white {
        filter: invert(1);
    }
    .modal-footer {
        border-top: none;
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
    <h4 class="mb-4 text-center"><i class="fas fa-boxes me-2"></i>จัดการล็อกเกอร์</h4>

    <?php if (!empty($message)): ?>
        <?php $alert_class = (strpos($message, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?>
        <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars(str_replace(['success:', 'error:'], '', $message)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Add New Locker Form -->
    <div class="card shadow mb-5">
        <div class="card-body p-4">
            <h5 class="card-title mb-4 text-primary"><i class="fas fa-plus-circle me-2"></i>เพิ่มล็อกเกอร์ใหม่</h5>
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label for="locker_number" class="form-label">หมายเลขล็อกเกอร์</label>
                    <input type="text" class="form-control" name="locker_number" id="locker_number" required>
                </div>
                <div class="col-md-4">
                    <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง</label>
                    <input type="number" step="0.01" class="form-control" name="price_per_hour" id="price_per_hour" required min="0.01" value="30.00">
                </div>
                <div class="col-md-4">
                    <label for="blynk_virtual_pin" class="form-label">GPIO Pin (บน ESP32)</label>
                    <input type="text" class="form-control" name="blynk_virtual_pin" id="blynk_virtual_pin" placeholder="เช่น 2, 4, 16" required>
                    <small class="form-text text-muted">ใช้เป็นหมายเลข GPIO Pin บน ESP32 ที่เชื่อมต่อกับรีเลย์</small>
                </div>
                <div class="col-md-4">
                    <label for="esp32_ip_address" class="form-label">ESP32 IP Address</label>
                    <input type="text" class="form-control" name="esp32_ip_address" id="esp32_ip_address" placeholder="เช่น 192.168.1.100">
                    <small class="form-text text-muted">IP Address ของ ESP32 ที่ควบคุมล็อกเกอร์นี้ (สามารถเว้นว่างได้ถ้ามี ESP32 ตัวเดียว)</small>
                </div>
                <div class="col-12 mt-4 d-flex justify-content-end">
                    <button type="submit" name="add_locker" class="btn btn-primary"><i class="fas fa-plus me-2"></i>เพิ่มล็อกเกอร์</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Locker List Table -->
    <div class="card shadow">
        <div class="card-body p-4">
            <h5 class="card-title mb-4 text-primary"><i class="fas fa-list me-2"></i>รายการล็อกเกอร์ทั้งหมด</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>หมายเลขล็อกเกอร์</th>
                            <th>สถานะ</th>
                            <th>อีเมลผู้จอง</th>
                            <th>เวลาเริ่ม</th>
                            <th>เวลาสิ้นสุด</th>
                            <th>ราคา/ชั่วโมง</th>
                            <th>GPIO Pin</th>
                            <th>ESP32 IP</th>
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
                                        <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($locker['status']) {
                                                case 'available':
                                                    $status_class = 'status-available';
                                                    $status_text = 'ว่าง';
                                                    break;
                                                case 'occupied':
                                                    $status_class = 'status-occupied';
                                                    $status_text = 'ใช้งานอยู่';
                                                    break;
                                                case 'reserved':
                                                    $status_class = 'status-reserved';
                                                    $status_text = 'จองแล้ว';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                                    $status_text = 'ไม่ทราบ';
                                            }
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($locker['user_email'] ?? '-') ?></td>
                                    <td><?= $locker['start_time'] ? date('d/m/Y H:i', strtotime($locker['start_time'])) : '-' ?></td>
                                    <td><?= $locker['end_time'] ? date('d/m/Y H:i', strtotime($locker['end_time'])) : '-' ?></td>
                                    <td><?= number_format($locker['price_per_hour'], 2) ?></td>
                                    <td><?= htmlspecialchars($locker['blynk_virtual_pin'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($locker['esp32_ip_address'] ?? '-') ?></td>
                                    <td>
                                        <a href="admin_edit_locker.php?id=<?= htmlspecialchars($locker['id']) ?>" class="btn btn-info btn-sm me-1" title="แก้ไข"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm delete-locker-btn" data-id="<?= htmlspecialchars($locker['id']) ?>" data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">ไม่มีล็อกเกอร์ในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLockerModal" tabindex="-1" aria-labelledby="deleteLockerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-lg shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.delete-locker-btn').on('click', function() {
        const lockerId = $(this).data('id');
        const lockerNumber = $(this).data('locker-number');
        $('#deleteLockerNumber').text(lockerNumber);
        $('#confirmDeleteButton').attr('href', 'admin_delete_locker.php?id=' + lockerId);
        let deleteModal = new bootstrap.Modal(document.getElementById('deleteLockerModal'));
        deleteModal.show();
    });
});
</script>
</body>
</html>
