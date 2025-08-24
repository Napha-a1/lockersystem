<?php
session_start();
if (!isset($_SESSION['admin_username'])) { // เปลี่ยนเป็น admin_username
    header("Location: admin_login.php");
    exit();
}

include '../connect.php';

// ดึงข้อมูลรวม
$user_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM locker_users"))['total'];
$locker_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM lockers"))['total'];
$booking_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings"))['total'];

// ดึงผู้ใช้ล่าสุด
$recent_users = mysqli_query($conn, "SELECT * FROM locker_users ORDER BY id DESC LIMIT 5");

// ดึงการจองล่าสุด
$recent_bookings = mysqli_query($conn, "SELECT b.*, l.locker_number 
                                       FROM bookings b 
                                       JOIN lockers l ON b.locker_id = l.id
                                       ORDER BY b.id DESC LIMIT 5");


// ดึงข้อมูลสำหรับกราฟ (รวมจำนวนการจองของแต่ละล็อกเกอร์)
$chart_sql = "SELECT l.locker_number, COUNT(b.id) as total 
              FROM lockers l
              LEFT JOIN bookings b ON l.id = b.locker_id
              GROUP BY l.locker_number
              ORDER BY l.locker_number";
$chart_result = $conn->query($chart_sql);
$chart_labels = [];
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = 'Locker ' . htmlspecialchars($row['locker_number']);
    $chart_data[] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แผงควบคุมผู้ดูแลระบบ</title>
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
        .stats-box {
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .stats-box .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .bg-gradient-primary { background: linear-gradient(45deg, #007bff, #0056b3); }
        .bg-gradient-success { background: linear-gradient(45deg, #28a745, #1e7e34); }
        .bg-gradient-warning { background: linear-gradient(45deg, #ffc107, #d39e00); }
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
    <h2 class="mb-5 text-center text-primary fw-bold"><i class="fas fa-tachometer-alt me-2"></i>ภาพรวมระบบแอดมิน</h2>

    <!-- Summary Stats -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stats-box bg-gradient-primary">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div>ผู้ใช้ทั้งหมด</div>
                <div class="fs-1"><?= $user_count ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-box bg-gradient-success">
                <div class="icon"><i class="fas fa-box"></i></div>
                <div>ล็อกเกอร์ทั้งหมด</div>
                <div class="fs-1"><?= $locker_count ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-box bg-gradient-warning">
                <div class="icon"><i class="fas fa-book-open"></i></div>
                <div>การจองทั้งหมด</div>
                <div class="fs-1"><?= $booking_count ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Recent Users -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white"><i class="fas fa-user-friends me-2"></i>ผู้ใช้ล่าสุด 5 คน</div>
                <div class="card-body">
                    <?php if ($recent_users->num_rows > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-user-circle me-2 text-muted"></i><?= htmlspecialchars($user['fullname']) ?> (<?= htmlspecialchars($user['email']) ?>)</span>
                                    <span class="badge bg-light text-dark">ID: <?= $user['id'] ?></span>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info text-center m-0">ไม่มีผู้ใช้ล่าสุด</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white"><i class="fas fa-calendar-alt me-2"></i>การจองล่าสุด 5 รายการ</div>
                <div class="card-body">
                    <?php if ($recent_bookings->num_rows > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php while($booking = $recent_bookings->fetch_assoc()): ?>
                                <li class="list-group-item">
                                    <strong><i class="fas fa-box me-2 text-muted"></i>Locker #<?= htmlspecialchars($booking['locker_number']) ?></strong> โดย <?= htmlspecialchars($booking['user_email']) ?><br>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($booking['start_time'])) ?> ถึง <?= date('d/m/Y H:i', strtotime($booking['end_time'])) ?></small>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info text-center m-0">ไม่มีการจองล่าสุด</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Locker Booking Chart -->
    <div class="card mb-5">
        <div class="card-header bg-primary text-white"><i class="fas fa-chart-bar me-2"></i>กราฟจำนวนการจองของแต่ละล็อกเกอร์</div>
        <div class="card-body">
            <canvas id="lockerChart" height="150"></canvas>
        </div>
    </div>

    <!-- Admin Actions -->
    <div class="text-center mt-4">
        <a href="admin_manage_users.php" class="btn btn-info btn-lg me-3"><i class="fas fa-users-cog me-2"></i>จัดการผู้ใช้</a>
        <a href="admin_manage_lockers.php" class="btn btn-secondary btn-lg me-3"><i class="fas fa-boxes me-2"></i>จัดการล็อกเกอร์</a>
        <a href="admin_logout.php" class="btn btn-danger btn-lg"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a>
    </div>
</div>

<!-- Footer -->
<footer class="footer text-center">
    <div class="container">
      <p class="mb-0">&copy; <?= date('Y') ?> Locker System Admin. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('lockerChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'จำนวนครั้งที่ถูกจอง',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(0, 123, 255, 0.7)', // Primary blue with transparency
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // อนุญาตให้ปรับ aspect ratio
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
