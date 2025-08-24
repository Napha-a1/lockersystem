<?php
session_start();
// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่ หากไม่ ให้เปลี่ยนเส้นทางไปยังหน้า login.php
if (!isset($_SESSION['user_email'])) {
    header('Location: login.php');
    exit();
}

include 'connect.php'; // เชื่อมต่อฐานข้อมูล

// ดึงข้อมูลล็อกเกอร์ที่ผู้ใช้คนนี้จองไว้
$user_email = $_SESSION['user_email'];
$stmt = $conn->prepare("SELECT id, locker_number, status, end_time FROM lockers WHERE user_email = ? AND status = 'occupied'");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$booked_lockers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $booked_lockers[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>หน้าหลักระบบล็อกเกอร์</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Inter', sans-serif;
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
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
      border-radius: 8px;
    }
    .btn-primary:hover {
      background-color: #0056b3;
      border-color: #0056b3;
    }
    .footer {
      background-color: #343a40; /* Dark Grey */
      color: white;
      padding: 1rem 0;
      position: relative;
      bottom: 0;
      width: 100%;
      margin-top: 50px;
    }
    .status-badge-occupied {
        background-color: #dc3545; /* Red for occupied */
        color: white;
        padding: .4em .6em;
        border-radius: .3em;
    }
    .status-badge-available {
        background-color: #28a745; /* Green for available */
        color: white;
        padding: .4em .6em;
        border-radius: .3em;
    }
    .time-remaining {
        font-size: 0.9em;
        color: #6c757d;
    }
    .locker-control-section {
        background-color: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

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

  <!-- Main Content -->
  <div class="container py-5 flex-grow-1">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="text-center mb-5">
      <h2 class="fw-bold text-primary">บริการล็อกเกอร์อัจฉริยะ</h2>
      <p class="lead text-muted">จัดการล็อกเกอร์ของคุณได้อย่างง่ายดายและปลอดภัย</p>
    </div>

    <div class="row g-4 mb-5">
      <div class="col-md-6">
        <div class="card shadow h-100">
          <div class="card-body text-center d-flex flex-column justify-content-center">
            <h5 class="card-title mb-3 fs-4 text-dark">📦 จองล็อกเกอร์</h5>
            <p class="card-text text-secondary">เลือกล็อกเกอร์และช่วงเวลาที่ต้องการใช้งาน</p>
            <a href="book_locker.php" class="btn btn-primary mt-auto">ไปที่หน้าจอง <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card shadow h-100">
          <div class="card-body text-center d-flex flex-column justify-content-center">
            <h5 class="card-title mb-3 fs-4 text-dark">📊 สถานะล็อกเกอร์</h5>
            <p class="card-text text-secondary">ตรวจสอบสถานะล็อกเกอร์ทั้งหมดและข้อมูลการใช้งาน</p>
            <a href="locker_status.php" class="btn btn-primary mt-auto">ดูสถานะ <i class="fas fa-list-alt"></i></a>
          </div>
        </div>
      </div>
    </div>

    <!-- Locker Control Section -->
    <div class="locker-control-section mb-5">
        <h3 class="text-center text-dark mb-4"><i class="fas fa-door-open"></i> ควบคุมล็อกเกอร์ของคุณ</h3>
        <?php if (!empty($booked_lockers)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                <?php foreach ($booked_lockers as $locker): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                <h5 class="card-title text-primary mb-2">ล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?></h5>
                                <p class="mb-1">สถานะ: <span class="status-badge-occupied">ใช้งานอยู่</span></p>
                                <p class="time-remaining mb-3">สิ้นสุด: <span id="time-<?= htmlspecialchars($locker['locker_number']) ?>"><?= date('d/m/Y H:i:s', strtotime($locker['end_time'])) ?></span></p>
                                <button class="btn btn-success btn-sm open-locker-btn mt-2"
                                        data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>"
                                        data-user-email="<?= htmlspecialchars($user_email) ?>">
                                    <i class="fas fa-unlock-alt"></i> เปิดล็อกเกอร์
                                </button>
                                <button class="btn btn-danger btn-sm close-locker-btn mt-2"
                                        data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>"
                                        data-user-email="<?= htmlspecialchars($user_email) ?>">
                                    <i class="fas fa-lock"></i> ปิดล็อกเกอร์
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle"></i> คุณยังไม่มีการจองล็อกเกอร์ที่กำลังใช้งานอยู่
            </div>
        <?php endif; ?>
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
        // เมื่อคลิกปุ่ม "เปิดล็อกเกอร์"
        $('.open-locker-btn').on('click', function() {
            let lockerNumber = $(this).data('locker-number');
            let userEmail = $(this).data('user-email');

            // ใช้ระบบยืนยันแบบ Modal แทน alert
            showConfirmationModal('ยืนยันการเปิดล็อกเกอร์', `คุณต้องการเปิดล็อกเกอร์หมายเลข ${lockerNumber} ใช่หรือไม่?`, function() {
                $.get('check_locker_permission.php', { locker_number: lockerNumber, action: 'open' }, function(permissionResponse) {
                    if (permissionResponse.trim() === 'PERMITTED') {
                        // ผู้ใช้มีสิทธิ์ เปิดล็อกเกอร์ผ่าน api_locker_control.php
                        $.get('api_locker_control.php', { locker_number: lockerNumber, user_email: userEmail, action: 'open' }, function(controlResponse) {
                            if (controlResponse.trim() === 'OPEN') {
                                showMessageModal('สำเร็จ', `คำสั่งเปิดล็อกเกอร์ #${lockerNumber} ถูกส่งแล้ว`);
                                // ไม่ต้อง reload ทันที แต่รอให้ modal ปิด
                            } else {
                                showMessageModal('ข้อผิดพลาด', `ไม่สามารถเปิดล็อกเกอร์ได้: ${controlResponse}`);
                            }
                        }).fail(function() {
                            showMessageModal('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการส่งคำสั่งเปิดล็อกเกอร์');
                        });
                    } else {
                        showMessageModal('ข้อผิดพลาด', `คุณไม่มีสิทธิ์เปิดล็อกเกอร์นี้: ${permissionResponse}`);
                    }
                }).fail(function() {
                    showMessageModal('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการตรวจสอบสิทธิ์');
                });
            });
        });

        // เมื่อคลิกปุ่ม "ปิดล็อกเกอร์"
        $('.close-locker-btn').on('click', function() {
            let lockerNumber = $(this).data('locker-number');
            let userEmail = $(this).data('user-email');

            // ใช้ระบบยืนยันแบบ Modal แทน alert
            showConfirmationModal('ยืนยันการปิดล็อกเกอร์', `คุณต้องการปิดล็อกเกอร์หมายเลข ${lockerNumber} ใช่หรือไม่?`, function() {
                $.get('api_locker_control.php', { locker_number: lockerNumber, user_email: userEmail, action: 'close' }, function(controlResponse) {
                    if (controlResponse.trim() === 'CLOSED') {
                        showMessageModal('สำเร็จ', `คำสั่งปิดล็อกเกอร์ #${lockerNumber} ถูกส่งแล้ว`);
                        // ไม่ต้อง reload ทันที แต่รอให้ modal ปิด
                    } else {
                        showMessageModal('ข้อผิดพลาด', `ไม่สามารถปิดล็อกเกอร์ได้: ${controlResponse}`);
                    }
                }).fail(function() {
                    showMessageModal('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการส่งคำสั่งปิดล็อกเกอร์');
                });
            });
        });


        // ฟังก์ชันสำหรับแสดง Modal ข้อความ
        function showMessageModal(title, message) {
            let modalHtml = `
                <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-lg shadow-lg">
                            <div class="modal-header bg-primary text-white border-0">
                                <h5 class="modal-title" id="messageModalLabel">${title}</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <p class="lead">${message}</p>
                            </div>
                            <div class="modal-footer border-0 justify-content-center">
                                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">ตกลง</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
            let messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
            $('#messageModal').on('hidden.bs.modal', function () {
                $(this).remove(); // ลบ modal ออกจาก DOM เมื่อปิด
                location.reload(); // รีโหลดหน้าหลัก
            });
        }

        // ฟังก์ชันสำหรับแสดง Modal ยืนยัน
        function showConfirmationModal(title, message, callback) {
            let modalHtml = `
                <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-lg shadow-lg">
                            <div class="modal-header bg-warning text-dark border-0">
                                <h5 class="modal-title" id="confirmationModalLabel">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <p class="lead">${message}</p>
                            </div>
                            <div class="modal-footer border-0 justify-content-center">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                                <button type="button" class="btn btn-warning rounded-pill px-4" id="confirmActionButton">ยืนยัน</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
            let confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            confirmationModal.show();

            $('#confirmActionButton').on('click', function() {
                callback();
                confirmationModal.hide();
            });

            $('#confirmationModal').on('hidden.bs.modal', function () {
                $(this).remove(); // ลบ modal ออกจาก DOM เมื่อปิด
            });
        }

        // Countdown Timer
        function updateCountdown() {
            $('.time-remaining').each(function() {
                let endTimeText = $(this).find('span').text();
                // Parse date in DD/MM/YYYY HH:mm:ss format
                const [datePart, timePart] = endTimeText.split(' ');
                const [day, month, year] = datePart.split('/');
                const formattedEndTime = `${month}/${day}/${year} ${timePart}`; // MM/DD/YYYY HH:mm:ss for Date object
                
                let endTime = new Date(formattedEndTime);
                let now = new Date();
                let timeLeft = endTime - now;

                if (timeLeft <= 0) {
                    $(this).html('<span class="text-danger">หมดเวลาแล้ว</span>');
                    // สามารถเพิ่มโค้ดเพื่อปิดล็อกเกอร์อัตโนมัติหรืออัปเดตสถานะได้ที่นี่
                } else {
                    let days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                    let hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    let minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    let seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                    $(this).find('span').text(`${days}d ${hours}h ${minutes}m ${seconds}s`);
                }
            });
        }

        // อัปเดตทุก 1 วินาที
        setInterval(updateCountdown, 1000);
        updateCountdown(); // เรียกใช้ครั้งแรกทันที
    });
  </script>
</body>
</html>
