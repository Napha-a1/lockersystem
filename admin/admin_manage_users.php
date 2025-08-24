<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
    header("Location: admin_login.php");
    exit();
}

include '../connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

$message = '';

// เพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // รหัสผ่าน

    if (empty($fullname) || empty($email) || empty($password)) {
        $message = "error: กรุณากรอกข้อมูลให้ครบถ้วน!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // ตรวจสอบรูปแบบอีเมล
        $message = "error: รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password) < 6) { // ตรวจสอบความยาวรหัสผ่าน
        $message = "error: รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        try {
            // ตรวจสอบอีเมลซ้ำ
            $stmt_check = $conn->prepare("SELECT id FROM locker_users WHERE email = :email");
            $stmt_check->bindParam(':email', $email);
            $stmt_check->execute();

            if ($stmt_check->fetch(PDO::FETCH_ASSOC)) {
                $message = "error: อีเมลนี้มีอยู่แล้ว!";
            } else {
                // เข้ารหัสรหัสผ่าน
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // เพิ่มผู้ใช้
                $stmt = $conn->prepare("INSERT INTO locker_users (fullname, email, password) VALUES (:fullname, :email, :password)");
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $password_hash);
                
                if ($stmt->execute()) {
                    $message = "success: เพิ่มผู้ใช้งานเรียบร้อยแล้ว!";
                } else {
                    $message = "error: เกิดข้อผิดพลาดในการเพิ่มผู้ใช้งาน: " . $stmt->errorInfo()[2];
                }
            }
        } catch (PDOException $e) {
            error_log("SQL Error adding new user: " . $e->getMessage());
            $message = "error: เกิดข้อผิดพลาดของฐานข้อมูลในการเพิ่มผู้ใช้งาน";
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$users = [];
try {
    $stmt_all_users = $conn->prepare("SELECT id, fullname, email, created_at FROM locker_users ORDER BY created_at DESC");
    $stmt_all_users->execute();
    $users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching all users: " . $e->getMessage());
    $message = "error: เกิดข้อผิดพลาดของฐานข้อมูลในการดึงข้อมูลผู้ใช้งาน";
}

// ไม่จำเป็นต้องปิดการเชื่อมต่อ PDO ด้วย $conn->close() เพราะ PDO จะจัดการเองเมื่อ script จบการทำงาน
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการผู้ใช้งาน</title>
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
    .form-control {
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
    <h4 class="mb-4 text-center"><i class="fas fa-users-cog me-2"></i>จัดการผู้ใช้งาน</h4>

    <?php if (!empty($message)): ?>
        <?php $alert_class = (strpos($message, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?>
        <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars(str_replace(['success:', 'error:'], '', $message)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Add New User Form -->
    <div class="card shadow mb-5">
        <div class="card-body p-4">
            <h5 class="card-title mb-4 text-primary"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" class="form-control" name="fullname" id="fullname" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">อีเมล</label>
                    <input type="email" class="form-control" name="email" id="email" required>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">รหัสผ่าน</label>
                    <input type="password" class="form-control" name="password" id="password" required>
                </div>
                <div class="col-12 mt-4 d-flex justify-content-end">
                    <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งาน</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User List Table -->
    <div class="card shadow">
        <div class="card-body p-4">
            <h5 class="card-title mb-4 text-primary"><i class="fas fa-users me-2"></i>รายการผู้ใช้งานทั้งหมด</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>อีเมล</th>
                            <th>วันที่สมัคร</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td><?= htmlspecialchars($user['fullname']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <a href="admin_edit_user.php?id=<?= htmlspecialchars($user['id']) ?>" class="btn btn-info btn-sm me-1" title="แก้ไข"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm delete-user-btn" data-id="<?= htmlspecialchars($user['id']) ?>" data-fullname="<?= htmlspecialchars($user['fullname']) ?>" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">ไม่มีผู้ใช้งานในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-lg shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="deleteUserModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบผู้ใช้</h5>
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
        let deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        deleteModal.show();
    });
});
</script>
</body>
</html>
