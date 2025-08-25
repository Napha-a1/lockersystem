<?php
session_start();
if (!isset($_SESSION['admin'])) {
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
$recent_bookings = mysqli_query($conn, "SELECT * FROM bookings ORDER BY id DESC LIMIT 5");

// ดึงข้อมูลสำหรับกราฟ
$chart_sql = "SELECT locker_id, COUNT(*) as total FROM bookings GROUP BY locker_id ORDER BY locker_id";
$chart_result = $conn->query($chart_sql);
$chart_labels = [];
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = 'Locker ' . $row['locker_id'];
    $chart_data[] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f4f7;
            padding: 2rem;
            font-family: 'Segoe UI', sans-serif;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h3 {
            margin-bottom: 0;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container">
    <h2 class="mb-4"> <strong><?= $_SESSION['admin']; ?></strong></h2>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>ผู้ใช้ทั้งหมด</h5>
                <h3><?= $user_count ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>ล็อกเกอร์ทั้งหมด</h5>
                <h3><?= $locker_count ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>การจองทั้งหมด</h5>
                <h3><?= $booking_count ?></h3>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">ผู้ใช้ล่าสุด</h5>
                <ul class="list-group">
                    <?php while ($user = mysqli_fetch_assoc($recent_users)): ?>
                        <li class="list-group-item"><?= $user['email'] ?> (<?= $user['fullname'] ?>)</li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">การจองล่าสุด</h5>
                <ul class="list-group">
                    <?php while ($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                        <li class="list-group-item">
                            Locker <?= $booking['locker_id'] ?> - <?= $booking['start_time'] ?> ถึง <?= $booking['end_time'] ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <h5 class="mb-3">📊 กราฟจำนวนการจองของแต่ละล็อกเกอร์</h5>
        <canvas id="lockerChart" height="100"></canvas>
    </div>

    <div class="text-end">
        <a href="admin_manage_users.php" class="btn btn-primary">🔧 จัดการผู้ใช้</a>
        <a href="admin_manage_lockers.php" class="btn btn-secondary">📦 จัดการล็อกเกอร์</a>
        <a href="login.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>

<script>
const ctx = document.getElementById('lockerChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'จำนวนครั้งที่ถูกจอง',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'จำนวนการจอง' }
            },
            x: {
                title: { display: true, text: 'ล็อกเกอร์' }
            }
        }
    }
});
</script>

</body>
</html>
