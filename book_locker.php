<?php
// book_locker.php
// ไฟล์สำหรับหน้าฟอร์มการจองล็อกเกอร์
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
    .card {
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .card-header {
      background-color: #007bff;
      color: white;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
      text-align: center;
      padding: 1.5rem;
    }
    .form-label {
      font-weight: 600;
    }
    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
      transition: background-color 0.3s;
    }
    .btn-primary:hover {
      background-color: #0056b3;
      border-color: #0056b3;
    }
    .total-price {
      font-size: 1.5rem;
      font-weight: bold;
      color: #28a745;
    }
  </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card p-4" style="width: 100%; max-width: 500px;">
        <div class="card-header">
            <h3>จองล็อกเกอร์</h3>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="book_process.php" method="POST">
                <div class="mb-3">
                    <label for="locker_id" class="form-label">เลือกล็อกเกอร์:</label>
                    <select name="locker_id" id="locker_id" class="form-select" required>
                        <?php if (count($available_lockers) > 0): ?>
                            <?php foreach ($available_lockers as $locker): ?>
                                <option value="<?= htmlspecialchars($locker['id']) ?>" data-price="<?= htmlspecialchars($locker['price_per_hour']) ?>">
                                    ล็อกเกอร์ที่ <?= htmlspecialchars($locker['locker_number']) ?> (ราคา: <?= number_format($locker['price_per_hour'], 2) ?> บาท/ชม.)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled selected>ไม่มีล็อกเกอร์ว่างในขณะนี้</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="start_time" class="form-label">เวลาเริ่มต้น:</label>
                    <input type="datetime-local" class="form-control" name="start_time" id="start_time" required>
                </div>

                <div class="mb-3">
                    <label for="end_time" class="form-label">เวลาสิ้นสุด:</label>
                    <input type="datetime-local" class="form-control" name="end_time" id="end_time" required>
                </div>

                <div class="mb-3 text-center">
                    <label class="form-label">ราคารวม:</label>
                    <span class="total-price" id="total_price">0.00</span> บาท
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg" <?= (count($available_lockers) == 0) ? 'disabled' : '' ?>>
                        <i class="fas fa-lock me-2"></i>ยืนยันการจอง
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>กลับหน้าหลัก
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // ตั้งค่าเวลาเริ่มต้นและเวลาสิ้นสุดเป็นค่าเริ่มต้น
    const now = new Date();
    // ปรับเวลาให้เป็นโซนเวลาท้องถิ่น (ป้องกันปัญหาเรื่อง offset)
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

    // เรียกใช้ฟังก์ชันคำนวณราคาเมื่อค่าในฟอร์มมีการเปลี่ยนแปลง
    $('#locker_id, #start_time, #end_time').on('change', calculateTotalPrice);

    // เรียกใช้ฟังก์ชันครั้งแรกตอนโหลดหน้าเว็บ
    calculateTotalPrice();
});
</script>

</body>
</html>
