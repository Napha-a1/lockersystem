<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

$message = '';

// เพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // รหัสผ่าน

    if (empty($fullname) || empty($email) || empty($password)) {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน!";
    } else {
        // ตรวจสอบอีเมลซ้ำ
        $check = $conn->prepare("SELECT * FROM locker_users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $result_check = $check->get_result();

        if ($result_check->num_rows > 0) {
            $message = "อีเมลนี้มีอยู่แล้ว!";
        } else {
            // เข้ารหัสรหัสผ่าน
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // เพิ่มผู้ใช้
            $stmt = $conn->prepare("INSERT INTO locker_users (fullname, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $fullname, $email, $password_hash);
            if ($stmt->execute()) {
                $message = "เพิ่มผู้ใช้เรียบร้อยแล้ว!";
            } else {
                $message = "เกิดข้อผิดพลาดในการเพิ่มผู้ใช้: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$users = $conn->query("SELECT * FROM locker_users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้</title>
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
    <h2 class="mb-5 text-center text-primary fw-bold"><i class="fas fa-users-cog me-2"></i>จัดการผู้ใช้งาน</h2>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Add User Form -->
    <div class="card mb-5">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" required>
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">อีเมล</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label">รหัสผ่าน</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="col-12 d-grid mt-4">
                    <button type="submit" name="add_user" class="btn btn-success btn-lg"><i class="fas fa-plus-circle me-2"></i>เพิ่มผู้ใช้</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User List Table -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>รายการผู้ใช้งานทั้งหมด</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover mt-3 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>อีเมล</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td><?= htmlspecialchars($user['fullname']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <a href="admin_edit_user.php?id=<?= $user['id'] ?>" class="btn btn-warning btn-sm me-2"><i class="fas fa-edit me-1"></i>แก้ไข</a>
                                        <button type="button" class="btn btn-danger btn-sm delete-user-btn" data-id="<?= $user['id'] ?>" data-fullname="<?= htmlspecialchars($user['fullname']) ?>"><i class="fas fa-trash-alt me-1"></i>ลบ</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">ไม่มีผู้ใช้ในระบบ</td>
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
                <h5 class="modal-title" id="deleteConfirmationModalLabel">ยืนยันการลบผู้ใช้</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="lead mb-0">คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ชื่อ <strong id="deleteUserName"></strong>?</p>
                <small class="text-muted">การดำเนินการนี้ไม่สามารถย้อนกลับได้</small>
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
    $('.delete-user-btn').on('click', function() {
        const userId = $(this).data('id');
        const userName = $(this).data('fullname');
        $('#deleteUserName').text(userName);
        $('#confirmDeleteButton').attr('href', 'admin_delete_user.php?id=' + userId);
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
        deleteModal.show();
    });
});
</script>
</body>
</html>
