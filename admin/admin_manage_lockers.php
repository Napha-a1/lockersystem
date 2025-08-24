<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

$message = '';

// เพิ่มล็อกเกอร์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_locker'])) {
    $locker_number = isset($_POST['locker_number']) ? trim($_POST['locker_number']) : '';
    $price_per_hour = isset($_POST['price_per_hour']) ? (float)$_POST['price_per_hour'] : 0;
    $blynk_virtual_pin = isset($_POST['blynk_virtual_pin']) ? trim($_POST['blynk_virtual_pin']) : ''; // เพิ่ม Virtual Pin

    if ($locker_number !== '' && $price_per_hour > 0 && $blynk_virtual_pin !== '') {
        // ตรวจสอบหมายเลขล็อกเกอร์ซ้ำ
        $stmt_check_locker = $conn->prepare("SELECT id FROM lockers WHERE locker_number = ?");
        $stmt_check_locker->bind_param("s", $locker_number);
        $stmt_check_locker->execute();
        $stmt_check_locker->store_result();

        if ($stmt_check_locker->num_rows > 0) {
            $message = "หมายเลขล็อกเกอร์นี้มีอยู่ในระบบแล้ว!";
        } else {
            $stmt = $conn->prepare("INSERT INTO lockers (locker_number, status, price_per_hour, blynk_virtual_pin) VALUES (?, 'available', ?, ?)");
            $stmt->bind_param("sds", $locker_number, $price_per_hour, $blynk_virtual_pin);
            if ($stmt->execute()) {
                $message = "เพิ่มล็อกเกอร์เรียบร้อยแล้ว!";
            } else {
                $message = "เกิดข้อผิดพลาดในการเพิ่มล็อกเกอร์: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check_locker->close();
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

// ดึงข้อมูลล็อกเกอร์ทั้งหมด
$lockers = $conn->query("SELECT * FROM lockers ORDER BY locker_number ASC");
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
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .table thead {
            background-color: #007bff;
            color: white;
        }
        .table th, .table td {
            vertical-align: middle;
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
    <h2 class="mb-5 text-center text-primary fw-bold"><i class="fas fa-boxes me-2"></i>จัดการล็อกเกอร์</h2>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Add Locker Form -->
    <div class="card mb-5">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-plus-square me-2"></i>เพิ่มล็อกเกอร์ใหม่</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label for="locker_number" class="form-label">หมายเลขล็อกเกอร์</label>
                    <input type="text" class="form-control" id="locker_number" name="locker_number" required>
                </div>
                <div class="col-md-4">
                    <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง</label>
                    <input type="number" step="0.01" class="form-control" id="price_per_hour" name="price_per_hour" required min="0.01">
                </div>
                <div class="col-md-4">
                    <label for="blynk_virtual_pin" class="form-label">Blynk Virtual Pin</label>
                    <input type="text" class="form-control" id="blynk_virtual_pin" name="blynk_virtual_pin" placeholder="เช่น V0, V1" required>
                </div>
                <div class="col-12 d-grid mt-4">
                    <button type="submit" name="add_locker" class="btn btn-success btn-lg"><i class="fas fa-plus-circle me-2"></i>เพิ่มล็อกเกอร์</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Locker List Table -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>รายการล็อกเกอร์ทั้งหมด</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover mt-3 align-middle">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>หมายเลขล็อกเกอร์</th>
                            <th>สถานะ</th>
                            <th>ราคาต่อชั่วโมง</th>
                            <th>Blynk Virtual Pin</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lockers && $lockers->num_rows > 0): ?>
                            <?php while($locker = $lockers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($locker['id']) ?></td>
                                    <td><?= htmlspecialchars($locker['locker_number']) ?></td>
                                    <td>
                                        <?php
                                            $status_class = ($locker['status'] === 'available') ? 'status-available' : 'status-occupied';
                                            $status_text = ($locker['status'] === 'available') ? 'ว่าง' : 'ใช้งานอยู่';
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td><?= number_format($locker['price_per_hour'], 2) ?></td>
                                    <td><?= htmlspecialchars($locker['blynk_virtual_pin']) ?></td>
                                    <td>
                                        <a href="admin_edit_locker.php?id=<?= $locker['id'] ?>" class="btn btn-warning btn-sm me-2"><i class="fas fa-edit me-1"></i>แก้ไข</a>
                                        <button type="button" class="btn btn-danger btn-sm delete-locker-btn" data-id="<?= $locker['id'] ?>" data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>"><i class="fas fa-trash-alt me-1"></i>ลบ</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">ไม่มีล็อกเกอร์ในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="booking_stats.php" class="btn btn-secondary btn-lg me-3"><i class="fas fa-arrow-left me-2"></i>กลับไปหน้า Dashboard</a>
        <a href="admin_logout.php" class="btn btn-danger btn-lg"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a>
    </div>
</div>

<!-- Confirmation Modal for Delete -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-lg shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="deleteConfirmationModalLabel">ยืนยันการลบล็อกเกอร์</h5>
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
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
        deleteModal.show();
    });
});
</script>
</body>
</html>
