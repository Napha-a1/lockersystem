<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header('Location: login.php');
  exit();
}

include 'connect.php'; // เชื่อมต่อฐานข้อมูล

// ดึงข้อมูลล็อกเกอร์ทั้งหมด
$sql = "SELECT id, locker_number, status, user_email, start_time, end_time, price_per_hour, blynk_virtual_pin
        FROM lockers ORDER BY locker_number ASC";
$result = $conn->query($sql);
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
    .table thead {
      background-color: #007bff; /* Primary Blue */
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
        display: inline-block; /* เพื่อให้ padding ทำงาน */
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
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid container">
    <a class="navbar-brand" href="index.php">
      <i class="fas fa-box-open"></i> Locker System
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <span class="nav-link text-white">ยินดีต้อนรับ: <?= htmlspecialchars($_SESSION['user_email']) ?></span>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="logout.php">
            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-5 flex-grow-1">
    <h4 class="mb-4 text-center"><i class="fas fa-info-circle me-2"></i>สถานะล็อกเกอร์ทั้งหมด</h4>

    <div class="card shadow mb-4">
        <div class="card-body">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['locker_number']) ?></td>
                                    <td>
                                        <?php
                                            $status_class = ($row['status'] === 'available') ? 'status-available' : 'status-occupied';
                                            $status_text = ($row['status'] === 'available') ? 'ว่าง' : 'ใช้งานอยู่';
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'occupied'): ?>
                                            <?= htmlspecialchars($row['user_email']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $row['start_time'] ? date('d/m/Y H:i', strtotime($row['start_time'])) : '-' ?></td>
                                    <td><?= $row['end_time'] ? date('d/m/Y H:i', strtotime($row['end_time'])) : '-' ?></td>
                                    <td><?= number_format($row['price_per_hour'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
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
