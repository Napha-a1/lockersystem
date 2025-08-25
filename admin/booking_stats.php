<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
    header("Location: admin_login.php");
    exit();
}

include '../connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ดึงข้อมูลรวม
$user_count = 0;
$locker_count = 0;
$booking_count = 0;

try {
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM locker_users");
    $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->query("SELECT COUNT(*) AS total FROM lockers");
    $locker_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->query("SELECT COUNT(*) AS total FROM bookings");
    $booking_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("SQL Error fetching summary data: " . $e->getMessage());
    // ไม่ถึงกับหยุด script แต่บันทึก error
}

// ดึงผู้ใช้ล่าสุด
$recent_users = [];
try {
    $stmt = $conn->query("SELECT * FROM locker_users ORDER BY id DESC LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching recent users: " . $e->getMessage());
}

// ดึงการจองล่าสุด
$recent_bookings = [];
try {
    $sql_recent_bookings = "SELECT b.*, l.locker_number
                           FROM bookings b
                           JOIN lockers l ON b.locker_id = l.id
                           ORDER BY b.id DESC LIMIT 5";
    $stmt = $conn->query($sql_recent_bookings);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching recent bookings: " . $e->getMessage());
}


// ดึงข้อมูลสำหรับกราฟ (รวมจำนวนการจองของแต่ละล็อกเกอร์)
$chart_labels = [];
$chart_data = [];
try {
    $chart_sql = "SELECT l.locker_number, COUNT(b.id) as total_bookings
                  FROM lockers l
                  LEFT JOIN bookings b ON l.id = b.locker_id
                  GROUP BY l.locker_number
                  ORDER BY l.locker_number"; // ORDER BY เพื่อให้กราฟเรียงตามหมายเลขล็อกเกอร์
    $chart_result = $conn->query($chart_sql);
    while ($row = $chart_result->fetch(PDO::FETCH_ASSOC)) {
        $chart_labels[] = "Locker #" . htmlspecialchars($row['locker_number']);
        $chart_data[] = $row['total_bookings'];
    }
} catch (PDOException $e) {
    error_log("SQL Error fetching chart data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สถิติการใช้งานล็อกเกอร์ (ผู้ดูแลระบบ)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    .container h4, .container h5 {
      font-weight: bold;
      color: #007bff;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-3px);
    }
    .card-icon {
        font-size: 2.5rem;
        color: #007bff;
    }
    .stat-value {
        font-size: 2.2rem;
        font-weight: bold;
        color: #343a40;
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
    .table thead {
      background-color: #007bff; /* Primary Blue */
      color: white;
    }
    .table th, .table td {
      vertical-align: middle;
    }
    .chart-container {
        position: relative;
        height: 400px; /* Fixed height for consistency */
        width: 100%;
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
    <h4 class="mb-4 text-center"><i class="fas fa-chart-bar me-2"></i>ภาพรวมสถิติการใช้งานล็อกเกอร์</h4>

    <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <div class="col">
            <div class="card h-100 text-center p-3">
                <div class="card-body">
                    <i class="fas fa-users card-icon mb-3"></i>
                    <h5 class="card-title text-muted">ผู้ใช้งานทั้งหมด</h5>
                    <p class="stat-value"><?= htmlspecialchars($user_count) ?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center p-3">
                <div class="card-body">
                    <i class="fas fa-boxes card-icon mb-3"></i>
                    <h5 class="card-title text-muted">ล็อกเกอร์ทั้งหมด</h5>
                    <p class="stat-value"><?= htmlspecialchars($locker_count) ?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center p-3">
                <div class="card-body">
                    <i class="fas fa-calendar-check card-icon mb-3"></i>
                    <h5 class="card-title text-muted">การจองทั้งหมด</h5>
                    <p class="stat-value"><?= htmlspecialchars($booking_count) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Chart Section -->
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4 text-primary text-center"><i class="fas fa-chart-line me-2"></i>จำนวนการจองของแต่ละล็อกเกอร์</h5>
                    <div class="chart-container">
                        <canvas id="bookingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Users Section -->
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4 text-primary"><i class="fas fa-user-friends me-2"></i>ผู้ใช้งานล่าสุด 5 คน</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>อีเมล</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_users)): ?>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['id']) ?></td>
                                            <td><?= htmlspecialchars($user['fullname']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center">ไม่มีผู้ใช้งานล่าสุด</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings Section -->
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4 text-primary"><i class="fas fa-history me-2"></i>การจองล่าสุด 5 รายการ</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>ล็อกเกอร์</th>
                                    <th>ผู้จอง</th>
                                    <th>เวลาสิ้นสุด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_bookings)): ?>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($booking['id']) ?></td>
                                            <td>#<?= htmlspecialchars($booking['locker_number']) ?></td>
                                            <td><?= htmlspecialchars($booking['user_email']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($booking['end_time'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">ไม่มีการจองล่าสุด</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="admin_manage_lockers.php" class="btn btn-secondary btn-lg me-2"><i class="fas fa-boxes me-2"></i>จัดการล็อกเกอร์</a>
        <a href="admin_manage_users.php" class="btn btn-secondary btn-lg"><i class="fas fa-users me-2"></i>จัดการผู้ใช้งาน</a>
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
    const ctx = document.getElementById('bookingChart').getContext('2d');
    const bookingChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'จำนวนการจอง',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(0, 123, 255, 0.7)', // Slightly transparent blue
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Allow aspect ratio to adjust
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 14
                        }
                    }
                },
                title: {
                    display: false,
                    text: 'จำนวนการจองของแต่ละล็อกเกอร์'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'จำนวนการจอง',
                        font: {
                            size: 14
                        }
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'หมายเลขล็อกเกอร์',
                        font: {
                            size: 14
                        }
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>
