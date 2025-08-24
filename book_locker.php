<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header('Location: login.php');
  exit();
}

include 'connect.php'; // เชื่อมต่อฐานข้อมูล

// ดึงรายการล็อกเกอร์ที่ 'available'
$available_lockers = [];
$sql_lockers = "SELECT id, locker_number, price_per_hour FROM lockers WHERE status = 'available' ORDER BY locker_number ASC";
$result_lockers = $conn->query($sql_lockers);
if ($result_lockers && $result_lockers->num_rows > 0) {
    while ($row = $result_lockers->fetch_assoc()) {
        $available_lockers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จองล็อกเกอร์</title>
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
    .card {
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-3px);
    }
    .form-label {
        font-weight: 600;
        color: #495057;
    }
    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.75rem 1rem;
    }
    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      font-size: 1.1rem;
      font-weight: bold;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-primary:hover {
      background-color: #0056b3;
      border-color: #0056b3;
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
  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card mx-auto shadow" style="max-width: 600px;">
    <div class="card-body p-4">
      <h4 class="card-title text-center mb-4 text-primary"><i class="fas fa-calendar-check me-2"></i>ระบบจองล็อกเกอร์</h4>

      <form action="book_process.php" method="post">
        <div class="mb-3">
          <label for="locker_id" class="form-label">หมายเลขล็อกเกอร์</label>
          <select class="form-select" name="locker_id" id="locker_id" required>
            <option value="">-- กรุณาเลือก --</option>
            <?php foreach ($available_lockers as $locker): ?>
                <option value="<?= htmlspecialchars($locker['id']) ?>" data-price="<?= htmlspecialchars($locker['price_per_hour']) ?>">
                    ล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?> (<?= number_format($locker['price_per_hour'], 2) ?> บาท/ชั่วโมง)
                </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="start_time" class="form-label">เวลาเริ่ม</label>
          <input type="datetime-local" class="form-control" name="start_time" id="start_time" required>
        </div>

        <div class="mb-3">
          <label for="end_time" class="form-label">เวลาสิ้นสุด</label>
          <input type="datetime-local" class="form-control" name="end_time" id="end_time" required>
        </div>

        <div class="mb-3">
          <label for="user_email" class="form-label">อีเมลผู้จอง</label>
          <input type="email" class="form-control" name="user_email" id="user_email" value="<?= htmlspecialchars($_SESSION['user_email']) ?>" readonly>
        </div>

        <div class="mb-4">
            <h5 class="text-end">ยอดชำระ: <span id="total_price" class="text-success fw-bold">0.00</span> บาท</h5>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">ยืนยันการจอง <i class="fas fa-clipboard-check ms-2"></i></button>
          <a href="index.php" class="btn btn-secondary btn-lg mt-2">กลับหน้าหลัก</a>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // กำหนดค่าเริ่มต้นของเวลาปัจจุบันและเวลาสิ้นสุด
    const now = new Date();
    // Offset for local timezone (Bangkok, +07:00)
    const offset = now.getTimezoneOffset() * 60000; // milliseconds
    const localNow = new Date(now.getTime() - offset);

    const nowISO = localNow.toISOString().slice(0, 16); // YYYY-MM-DDTHH:mm
    
    const twoHoursLater = new Date(localNow.getTime() + (2 * 60 * 60 * 1000));
    const twoHoursLaterISO = twoHoursLater.toISOString().slice(0, 16);

    $('#start_time').val(nowISO);
    $('#end_time').val(twoHoursLaterISO);

    // ฟังก์ชันคำนวณราคา
    function calculateTotalPrice() {
        const lockerSelect = $('#locker_id');
        const selectedOption = lockerSelect.find('option:selected');
        const pricePerHour = parseFloat(selectedOption.data('price') || 0);

        const startTime = new Date($('#start_time').val());
        const endTime = new Date($('#end_time').val());

        if (isNaN(startTime.getTime()) || isNaN(endTime.getTime()) || startTime >= endTime) {
            $('#total_price').text('0.00');
            return;
        }

        const diffMilliseconds = endTime - startTime;
        const diffHours = diffMilliseconds / (1000 * 60 * 60); // แปลงเป็นชั่วโมง

        const totalPrice = pricePerHour * diffHours;
        $('#total_price').text(totalPrice.toFixed(2));
    }

    // เรียกฟังก์ชันคำนวณราคาเมื่อมีการเปลี่ยนแปลง
    $('#locker_id, #start_time, #end_time').on('change input', calculateTotalPrice);

    // เรียกใช้ครั้งแรกเมื่อโหลดหน้า
    calculateTotalPrice();
});
</script>
</body>
</html>
