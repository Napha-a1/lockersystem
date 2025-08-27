<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
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

    // ดึงจำนวนการจองจากตาราง 'bookings'
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM bookings");
    $booking_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("SQL Error fetching summary data: " . $e->getMessage());
}

// ดึงผู้ใช้ล่าสุด
$recent_users = [];
try {
    $stmt = $conn->query("SELECT id, fullname, email, created_at FROM locker_users ORDER BY id DESC LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching recent users: " . $e->getMessage());
}

// ดึงการจองล่าสุด (จากตาราง bookings)
$recent_bookings = [];
try {
    $sql_recent_bookings = "
        SELECT b.id, b.locker_id, b.user_email, b.start_time, b.end_time, b.total_price, l.locker_number
        FROM bookings b
        JOIN lockers l ON b.locker_id = l.id
        ORDER BY b.id DESC LIMIT 5
    ";
    $stmt = $conn->query($sql_recent_bookings);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SQL Error fetching recent bookings: " . $e->getMessage());
}

// ดึงข้อมูลสำหรับ Chart (จำนวนการจองของแต่ละล็อกเกอร์)
$locker_booking_counts = [];
$locker_labels = [];
$booking_data = [];
try {
    $sql_chart_data = "
        SELECT l.locker_number, COUNT(b.id) AS booking_count
        FROM lockers l
        LEFT JOIN bookings b ON l.id = b.locker_id
        GROUP BY l.locker_number
        ORDER BY l.locker_number ASC
    ";
    $stmt = $conn->query($sql_chart_data);
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($chart_data as $row) {
        $locker_labels[] = "Locker " . htmlspecialchars($row['locker_number']);
        $booking_data[] = (int)$row['booking_count'];
    }
} catch (PDOException $e) {
    error_log("SQL Error fetching chart data: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แดชบอร์ดแอดมิน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .main-container { padding-top: 2rem; padding-bottom: 2rem; }
        .card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }
        .icon-users { background-color: #007bff; }
        .icon-lockers { background-color: #ffc107; }
        .icon-bookings { background-color: #28a745; }
        .chart-container {
            position: relative;
            height: 40vh; /* Responsive height */
            width: 100%;
        }
    </style>
</head>
<body>

<div class="container-fluid main-container">
    <div class="row mb-4">
        <div class="col text-center">
            <h2 class="text-primary"><i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ดแอดมิน</h2>
            <nav class="nav justify-content-center">
                <a class="nav-link btn btn-primary mx-1" href="booking_stats.php"><i class="fas fa-chart-line me-2"></i>สรุปภาพรวม</a>
                <a class="nav-link btn btn-outline-primary mx-1" href="admin_manage_lockers.php"><i class="fas fa-box me-2"></i>จัดการล็อกเกอร์</a>
                <a class="nav-link btn btn-outline-primary mx-1" href="admin_manage_users.php"><i class="fas fa-users me-2"></i>จัดการผู้ใช้</a>
                <a class="nav-link btn btn-outline-danger mx-1" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a>
            </nav>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card p-3 d-flex align-items-center flex-row">
                <div class="icon-circle icon-users me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-muted">จำนวนผู้ใช้งาน</h5>
                    <h3 class="mb-0 fw-bold"><?= htmlspecialchars($user_count) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 d-flex align-items-center flex-row">
                <div class="icon-circle icon-lockers me-3">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-muted">จำนวนล็อกเกอร์</h5>
                    <h3 class="mb-0 fw-bold"><?= htmlspecialchars($locker_count) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 d-flex align-items-center flex-row">
                <div class="icon-circle icon-bookings me-3">
                    <i class="fas fa-book-open"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-muted">จำนวนการจอง (ทั้งหมด)</h5>
                    <h3 class="mb-0 fw-bold"><?= htmlspecialchars($booking_count) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Users Table -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h4 class="card-title mb-3"><i class="fas fa-user-friends me-2"></i>ผู้ใช้งานล่าสุด</h4>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>อีเมล</th>
                                <th>วันที่สมัคร</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_users)): ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['id']) ?></td>
                                        <td><?= htmlspecialchars($user['fullname']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">ไม่พบผู้ใช้งานล่าสุด</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Bookings Table -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h4 class="card-title mb-3"><i class="fas fa-calendar-alt me-2"></i>การจองล่าสุด</h4>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ล็อกเกอร์</th>
                                <th>ผู้ใช้</th>
                                <th>เวลาเริ่ม</th>
                                <th>ราคารวม (฿)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_bookings)): ?>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($booking['id']) ?></td>
                                        <td><?= htmlspecialchars($booking['locker_number']) ?></td>
                                        <td><?= htmlspecialchars($booking['user_email']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($booking['start_time'])) ?></td>
                                        <td><?= number_format($booking['total_price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">ไม่พบการจองล่าสุด</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="row g-4 mt-5">
        <div class="col-12">
            <div class="card p-4">
                <h4 class="card-title mb-3"><i class="fas fa-chart-bar me-2"></i>จำนวนการจองของแต่ละล็อกเกอร์</h4>
                <div class="chart-container">
                    <canvas id="lockerBookingChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Footer -->
<footer class="text-center mt-5 p-3 bg-light text-muted">
    &copy; <?= date('Y') ?> Locker System Admin. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('lockerBookingChart').getContext('2d');
    var lockerBookingChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($locker_labels) ?>,
            datasets: [{
                label: 'จำนวนการจอง',
                data: <?= json_encode($booking_data) ?>,
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
