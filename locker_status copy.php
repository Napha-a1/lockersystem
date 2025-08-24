<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header('Location: login.php');
  exit();
}

include 'connect.php'; // เชื่อมต่อฐานข้อมูล
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สถานะล็อกเกอร์</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f2f4f8;
    }
    .table th, .table td {
      vertical-align: middle;
    }
    .table thead {
      background-color: #0d6efd;
      color: white;
    }
    .container h4 {
      font-weight: bold;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Locker System</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <span class="nav-link text-white">ยินดีต้อนรับ: <?= htmlspecialchars($_SESSION['user_email']) ?></span>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="logout.php">ออกจากระบบ</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-5">
    <h4 class="mb-4">📋 รายการสถานะล็อกเกอร์</h4>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr class="table-dark">
            <th>หมายเลข</th>
            <th>สถานะ</th>
            <th>ผู้จอง</th>
            <th>เวลาเริ่ม</th>
            <th>เวลาสิ้นสุด</th>
            <th>การดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sql = "SELECT * FROM lockers ORDER BY locker_number ASC";
          $result = $conn->query($sql);
          if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
              $status_badge = ($row['status'] == 'available') ? '<span class="badge bg-success">ว่าง</span>' : '<span class="badge bg-danger">ถูกจอง</span>';
              // ตรวจสอบสถานะควรเป็น 'occupied' สำหรับล็อกเกอร์ที่ถูกใช้งาน
              // และ user_email ต้องตรงกับ session ของผู้ใช้ปัจจุบัน
              $is_user_booked_and_occupied = ($row['status'] == 'occupied' && $row['user_email'] == $_SESSION['user_email']);

              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['locker_number']) . "</td>";
              echo "<td>" . $status_badge . "</td>";
              echo "<td>" . (!empty($row['user_email']) ? htmlspecialchars($row['user_email']) : '-') . "</td>";
              echo "<td>" . (!empty($row['start_time']) ? date('d/m/Y H:i', strtotime($row['start_time'])) : '-') . "</td>";
              echo "<td>" . (!empty($row['end_time']) ? date('d/m/Y H:i', strtotime($row['end_time'])) : '-') . "</td>";
              echo "<td>";
              if ($is_user_booked_and_occupied) {
                  echo '<button class="btn btn-primary btn-sm open-locker-btn" data-locker-number="' . htmlspecialchars($row['locker_number']) . '" data-user-email="' . htmlspecialchars($_SESSION['user_email']) . '">เปิดล็อกเกอร์</button>';
              } else {
                  echo '-';
              }
              echo "</td>";
              echo "</tr>";
            }
          } else {
            echo "<tr><td colspan='6' class='text-center'>ไม่มีล็อกเกอร์ในระบบ</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Event listener สำหรับปุ่ม "เปิดล็อกเกอร์"
    $('.open-locker-btn').on('click', function(){
        let lockerNumber = $(this).data('locker-number');
        let userEmail = $(this).data('user-email');
        
        // ส่งคำขอ AJAX ไปยัง api_locker_control.php
        $.get('api_locker_control.php', { 
            locker_number: lockerNumber, 
            user_email: userEmail 
        }, function(response){
            // ตรวจสอบการตอบกลับจากเซิร์ฟเวอร์
            if (response.trim() === 'OPEN') {
                alert('คำสั่งเปิดล็อกเกอร์ถูกส่งแล้ว');
                // สามารถเพิ่มโค้ดเพื่อรีโหลดหน้าหรือแสดงสถานะเพิ่มเติมได้ที่นี่
                location.reload(); // ตัวอย่าง: รีโหลดหน้าเพื่ออัปเดตสถานะ
            } else {
                alert('ไม่สามารถเปิดล็อกเกอร์ได้: ' + response);
            }
        }).fail(function() {
            // จัดการข้อผิดพลาดในการเชื่อมต่อ
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
        });
    });
});
</script>

</body>
</html>
