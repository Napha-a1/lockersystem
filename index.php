<?php
// index.php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ฟังก์ชันสำหรับดึงข้อมูล Locker ทั้งหมด
function getAllLockers($conn) {
    try {
        $stmt = $conn->query("SELECT id, locker_number, status, esp32_ip_address, user_email, end_time, price_per_hour FROM lockers ORDER BY locker_number ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lockers: " . $e->getMessage());
        return [];
    }
}

// ดึงข้อมูล Locker ทั้งหมด
$lockers = getAllLockers($conn);
$current_user_email = $_SESSION['user_email'] ?? null;
$has_user_lockers = false; // Flag เพื่อตรวจสอบว่ามีล็อกเกอร์ของผู้ใช้หรือไม่

// ข้อความ Success/Error จากการจองหรือยกเลิก
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .occupied { border-left: 5px solid #ef4444; }
        .available { border-left: 5px solid #22c55e; }
        .btn-cancel {
            background-color: #dc2626;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-cancel:hover {
            background-color: #b91c1c;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-lock text-yellow-500 mr-2"></i>Locker System
            </h1>
            <nav>
                <?php if ($current_user_email): ?>
                    <span class="text-gray-700 mr-4">
                        <i class="fas fa-user-circle mr-1"></i>เข้าสู่ระบบโดย: <?= htmlspecialchars($current_user_email) ?>
                    </span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>ออกจากระบบ
                    </a>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto p-4 flex-grow">
        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="block sm:inline"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">สถานะล็อกเกอร์ทั้งหมด</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($lockers as $locker): ?>
                <?php
                    $is_available = $locker['status'] === 'available';
                    $is_occupied_by_user = $locker['status'] === 'occupied' && $locker['user_email'] === $current_user_email;
                    $is_expired = $locker['end_time'] && (new DateTime($locker['end_time']) < new DateTime());
                    if ($is_occupied_by_user) {
                        $has_user_lockers = true;
                    }
                ?>
                <div class="card bg-white rounded-lg shadow-md p-6 border-l-4 <?= $is_available ? 'border-green-500' : 'border-red-500' ?>">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xl font-semibold text-gray-700">ล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?></span>
                        <?php if ($is_available): ?>
                            <span class="bg-green-500 text-white text-xs font-semibold px-2.5 py-1 rounded-full">ว่าง</span>
                        <?php else: ?>
                            <span class="bg-red-500 text-white text-xs font-semibold px-2.5 py-1 rounded-full">ไม่ว่าง</span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-gray-600 mb-2">ราคา: <strong><?= number_format($locker['price_per_hour'], 2) ?> บาท/ชม.</strong></p>
                    <p class="text-gray-600 mb-2">สถานะ: <strong><?= htmlspecialchars($locker['status']) ?></strong></p>
                    <?php if (!$is_available): ?>
                        <p class="text-sm text-gray-500">จองโดย: <?= htmlspecialchars($locker['user_email'] ?? '-') ?></p>
                        <p class="text-sm text-gray-500">สิ้นสุด: <?= $locker['end_time'] ? date('d/m/Y H:i', strtotime($locker['end_time'])) : '-' ?></p>
                    <?php endif; ?>

                    <?php if ($current_user_email): ?>
                        <div class="mt-4 text-center">
                            <?php if ($is_available): ?>
                                <a href="book_locker.php?id=<?= htmlspecialchars($locker['id']) ?>" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                                    <i class="fas fa-calendar-alt mr-2"></i>จองล็อกเกอร์
                                </a>
                            <?php elseif ($is_occupied_by_user): ?>
                                <div class="flex flex-col space-y-2">
                                    <form action="api_control.php" method="post" class="w-full">
                                        <input type="hidden" name="locker_number" value="<?= htmlspecialchars($locker['locker_number']) ?>">
                                        <input type="hidden" name="action" value="open">
                                        <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors flex items-center justify-center mb-2">
                                            <i class="fas fa-door-open mr-2"></i> เปิดล็อกเกอร์
                                        </button>
                                    </form>
                                    <form action="cancel_booking.php" method="post" class="w-full">
                                        <input type="hidden" name="locker_id" value="<?= htmlspecialchars($locker['id']) ?>">
                                        <button type="submit" class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors flex items-center justify-center">
                                            <i class="fas fa-times-circle mr-2"></i> ยกเลิกการจอง
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="mt-4 text-center text-gray-500">
                                    ล็อกเกอร์ไม่ว่าง
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?= date('Y'); ?> Locker System. All rights reserved.
    </footer>

</body>
</html>
