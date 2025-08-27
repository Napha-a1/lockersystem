<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header('Location: login.php');
  exit();
}

include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ดึงรายการล็อกเกอร์ที่ 'available'
$available_lockers = [];
try {
    $stmt = $conn->prepare("SELECT id, locker_number, price_per_hour FROM lockers WHERE status = 'available' ORDER BY locker_number ASC");
    $stmt->execute();
    $available_lockers = $stmt->fetchAll(PDO::FETCH_ASSOC); // ดึงข้อมูลทั้งหมดในรูปแบบ associative array
} catch (PDOException $e) {
    error_log("SQL Error fetching available lockers: " . $e->getMessage());
    // อาจจะแสดงข้อความ error บนหน้าเว็บ หรือ redirect ไปหน้า error
    header('Location: error.php?message=' . urlencode('เกิดข้อผิดพลาดในการดึงข้อมูลล็อกเกอร์ที่ว่าง'));
    exit();
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
      font-family: 'Inter', sans-serif;
      background-color: #f8f9fa;
    }
    .booking-card {
      background-color: #ffffff;
      padding: 2rem;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 500px;
    }
    .btn-primary, .btn-secondary {
      border-radius: 50px;
      padding: 10px 20px;
    }
    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
    }
    .btn-primary:hover {
      background-color: #0056b3;
      border-color: #004d99;
    }
    .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
    }
    .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #545b62;
    }
    .text-center a {
        color: #6c757d;
        text-decoration: none;
    }
    .text-center a:hover {
        color: #007bff;
    }
  </style>
</head>
<body>

<div class="d-flex justify-content-center align-items-center" style="min-height: 100vh;">
  <div class="booking-card shadow">
    <h2 class="text-center mb-4"><i class="fas fa-calendar-check me-2"></i>จองล็อกเกอร์</h2>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (empty($available_lockers)): ?>
      <div class="alert alert-warning text-center" role="alert">
        <i class="fas fa-info-circle me-2"></i>ขณะนี้ไม่มีล็อกเกอร์ว่าง
      </div>
    <?php else: ?>
    <form action="book_process.php" method="POST">
      <div class="mb-3">
        <label for="locker_id" class="form-label">เลือกหมายเลขล็อกเกอร์:</label>
        <select class="form-select" id="locker_id" name="locker_id" required>
          <?php foreach ($available_lockers as $locker): ?>
            <option value="<?= htmlspecialchars($locker['id']) ?>" data-price="<?= htmlspecialchars($locker['price_per_hour']) ?>">
              ล็อกเกอร์หมายเลข <?= htmlspecialchars($locker['locker_number']) ?> (฿<?= number_format($locker['price_per_hour'], 2) ?>/ชั่วโมง)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="start_time" class="form-label">เวลาเริ่มต้น:</label>
        <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
      </div>

      <div class="mb-3">
        <label for="end_time" class="form-label">เวลาสิ้นสุด:</label>
        <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
      </div>

      <div class="mb-4 text-center">
          <h5>ราคารวม: <span id="total_price" class="text-success">0.00</span> บาท</h5>
      </div>
      
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-lock-open me-2"></i>ยืนยันการจอง</button>
        <a href="index.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>กลับ</a>
      </div>
    </form>
    <?php endif; ?>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // ตั้งค่าเวลาเริ่มต้นและสิ้นสุดอัตโนมัติ
    const now = new Date();
    // ปรับเวลาท้องถิ่น (ป้องกันปัญหาเรื่อง offset)
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

    // เรียกใช้ฟังก์ชันคำนวณราคาเมื่อค่าในฟอร์มเปลี่ยน
    $('#locker_id, #start_time, #end_time').on('change input', calculateTotalPrice);

    // เรียกใช้ครั้งแรกเมื่อโหลดหน้าเว็บ
    calculateTotalPrice();
});
</script>
</body>
</html>
