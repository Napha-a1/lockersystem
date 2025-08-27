<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล

$current_user_email = $_SESSION['user_email'] ?? null;
$is_admin = isset($_SESSION['admin_username']);

// IP Address ของอุปกรณ์ ESP32 สำหรับ Locker 1
// *** สำคัญ: ตรวจสอบให้แน่ใจว่า IP Address นี้ถูกต้องและสามารถเข้าถึงได้ ***
$esp32_ip_locker1 = "10.242.194.185"; 

// ข้อความแจ้งเตือน (ถ้ามี)
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// ดึงข้อมูล Locker ทั้งหมดจากฐานข้อมูล
function getAllLockers($conn) {
    try {
        $stmt = $conn->query("SELECT id, locker_number, status, esp32_ip_address, user_email, start_time, end_time, price_per_hour FROM lockers ORDER BY locker_number ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lockers: " . $e->getMessage());
        return [];
    }
}
$all_lockers = getAllLockers($conn);

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- Header -->
    <header class="bg-white shadow p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">ระบบล็อกเกอร์</h1>
            <nav>
                <?php if ($current_user_email || $is_admin): // ถ้ามีใครล็อกอินอยู่ ?>
                    <?php if ($is_admin): ?>
                        <span class="text-purple-600 font-semibold mr-4"><i class="fas fa-user-cog mr-1"></i>แอดมิน: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                        <a href="admin/booking_stats.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors mr-2">
                            <i class="fas fa-tachometer-alt mr-2"></i>แดชบอร์ดแอดมิน
                        </a>
                    <?php elseif ($current_user_email): ?>
                        <span class="text-gray-600 mr-4">สวัสดี, <?= htmlspecialchars($current_user_email) ?></span>
                        <a href="book_locker.php" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                            <i class="fas fa-plus-circle mr-2"></i>จองล็อกเกอร์
                        </a>
                        <a href="locker_status.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors ml-2">
                            <i class="fas fa-list-ul mr-2"></i>สถานะล็อกเกอร์
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors ml-2">
                        <i class="fas fa-sign-out-alt mr-2"></i>ออกจากระบบ
                    </a>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
                    </a>
                    <a href="register.php" class="bg-indigo-500 text-white px-4 py-2 rounded-lg hover:bg-indigo-600 transition-colors ml-2">
                        <i class="fas fa-user-plus mr-2"></i>สมัครสมาชิก
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto mt-8 p-4 flex-grow">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">สถานะล็อกเกอร์และควบคุม</h2>
        
        <!-- Success/Error Message -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php elseif (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php if (!empty($all_lockers)): ?>
                <?php foreach ($all_lockers as $locker): ?>
                    <?php 
                        $is_locker_1 = ($locker['locker_number'] == 1);
                        $is_occupied = ($locker['status'] === 'occupied');
                        $is_available = ($locker['status'] === 'available');
                        $is_expired = ($locker['end_time'] && strtotime($locker['end_time']) < time());
                        $is_online_config = !empty($locker['esp32_ip_address']); // ตรวจสอบว่ามี IP Address ใน DB หรือไม่ (สำหรับ Locker อื่นๆ)
                        // สำหรับ Locker 1, สถานะ "ออนไลน์" จะดูจากว่าเรามี IP Address ที่กำหนดให้มันหรือไม่
                        $is_locker1_online_actual = !empty($esp32_ip_locker1); 
                    ?>
                    <div class="card bg-white rounded-lg shadow-lg p-6 
                        <?= $is_locker_1 ? 'border-4 border-blue-500' : 
                          ($is_occupied ? 'border-yellow-500' : 
                          ($is_available ? 'border-green-500' : 'border-gray-300')) ?>">
                        
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xl font-bold text-gray-700">ล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?></span>
                            <span id="locker-<?= htmlspecialchars($locker['locker_number']) ?>-status-badge" 
                                class="px-3 py-1 text-sm font-semibold rounded-full 
                                <?= $is_occupied ? 'bg-yellow-100 text-yellow-800' : 
                                  ($is_available ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') ?>">
                                สถานะ: <?= $is_occupied ? 'ไม่ว่าง' : ($is_available ? 'ว่าง' : 'ไม่ทราบ') ?>
                            </span>
                        </div>
                        <p class="text-gray-600 mb-2"><strong>ผู้ใช้งาน:</strong> <?= htmlspecialchars($locker['user_email'] ?? '-') ?></p>
                        <p class="text-gray-600 mb-4"><strong>หมดเวลา:</strong> <?= $locker['end_time'] ? date('d/m/Y H:i', strtotime($locker['end_time'])) : '-' ?></p>

                        <?php if ($is_locker_1): // สำหรับ Locker 1 (ปุ่มหลอกๆ ไม่สน DB) ?>
                            <p class="text-gray-600 text-sm mb-2"><strong>สถานะเชื่อมต่อ:</strong> 
                                <span class="badge <?= $is_locker1_online_actual ? 'bg-primary' : 'bg-secondary' ?>">
                                    <i class="fas <?= $is_locker1_online_actual ? 'fa-globe' : 'fa-unlink' ?> mr-1"></i>
                                    <?= $is_locker1_online_actual ? 'ออนไลน์' : 'ออฟไลน์' ?>
                                </span>
                            </p>
                            <div class="flex space-x-2 mt-4">
                                <button class="dummy-control-btn w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200 <?= !$is_locker1_online_actual ? 'opacity-50 cursor-not-allowed' : '' ?>" 
                                    data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>" 
                                    data-command="on" 
                                    data-esp32-ip="<?= htmlspecialchars($esp32_ip_locker1) ?>"
                                    <?= !$is_locker1_online_actual ? 'disabled' : '' ?>>
                                    <i class="fas fa-door-open mr-2"></i> เปิดล็อกเกอร์
                                </button>
                                <button class="dummy-control-btn w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200 <?= !$is_locker1_online_actual ? 'opacity-50 cursor-not-allowed' : '' ?>" 
                                    data-locker-number="<?= htmlspecialchars($locker['locker_number']) ?>" 
                                    data-command="off" 
                                    data-esp32-ip="<?= htmlspecialchars($esp32_ip_locker1) ?>"
                                    <?= !$is_locker1_online_actual ? 'disabled' : '' ?>>
                                    <i class="fas fa-door-closed mr-2"></i> ปิดล็อกเกอร์
                                </button>
                            </div>
                            <div id="status-<?= htmlspecialchars($locker['locker_number']) ?>" class="mt-4 text-center text-gray-600"></div>
                        <?php else: // สำหรับ Locker อื่นๆ (แสดงสถานะและจอง) ?>
                            <p class="text-gray-600 text-sm mb-2"><strong>สถานะเชื่อมต่อ:</strong> 
                                <span class="badge <?= !empty($locker['esp32_ip_address']) ? 'bg-primary' : 'bg-secondary' ?>">
                                    <i class="fas <?= !empty($locker['esp32_ip_address']) ? 'fa-globe' : 'fa-unlink' ?> mr-1"></i>
                                    <?= !empty($locker['esp32_ip_address']) ? 'ออนไลน์' : 'ออฟไลน์' ?>
                                </span>
                            </p>
                            <?php if ($is_available && $current_user_email): ?>
                                <a href="book_locker.php" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200 mt-4">
                                    <i class="fas fa-calendar-check mr-2"></i> จองล็อกเกอร์
                                </a>
                            <?php elseif ($is_occupied && $current_user_email === $locker['user_email'] && !$is_expired): ?>
                                <div class="mt-4 text-center text-blue-600 font-semibold">
                                    คุณได้จองล็อกเกอร์นี้อยู่
                                </div>
                            <?php elseif ($is_expired): ?>
                                <div class="mt-4 text-center text-red-600 font-semibold">
                                    ล็อกเกอร์หมดเวลาแล้ว
                                </div>
                            <?php else: ?>
                                <div class="mt-4 text-center text-gray-500">
                                    ไม่สามารถจองได้ในสถานะนี้
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="col-span-full text-center text-gray-600">ยังไม่มีล็อกเกอร์ในระบบ</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

    <script>
    $(document).ready(function() {
        // สำหรับปุ่มหลอกๆ ของ Locker #1
        $('.dummy-control-btn').on('click', function() {
            var lockerNumber = $(this).data('locker-number');
            var command = $(this).data('command'); // 'on' or 'off'
            var esp32Ip = $(this).data('esp32-ip'); // ดึง IP จาก data attribute
            
            var statusDiv = $('#status-' + lockerNumber);
            var statusBadge = $('#locker-' + lockerNumber + '-status-badge');
            var btn = $(this);

            statusDiv.text('กำลังส่งคำสั่ง...');
            btn.prop('disabled', true).addClass('opacity-50'); // ปิดปุ่มชั่วคราว

            // ส่งคำสั่งไปยัง control_locker.php (ซึ่งจะส่งต่อไปยัง ESP32)
            $.post('control_locker.php', { 
                locker_number: lockerNumber, 
                command: command,
                esp32_ip: esp32Ip 
            }, function(response) {
                if (response.status === 'SUCCESS') {
                    statusDiv.text('คำสั่ง "' + command + '" สำเร็จ!');
                    // อัปเดตสถานะใน UI ทันที (สำหรับ Locker 1 เท่านั้น)
                    if(command === 'on') {
                        statusBadge.removeClass('bg-red-100 text-red-800 bg-gray-100 text-gray-800 bg-green-100 text-green-800').addClass('bg-yellow-100 text-yellow-800').text('สถานะ: เปิดอยู่'); 
                    } else {
                        statusBadge.removeClass('bg-red-100 text-red-800 bg-gray-100 text-gray-800 bg-yellow-100 text-yellow-800').addClass('bg-green-100 text-green-800').text('สถานะ: ปิดอยู่'); 
                    }
                } else {
                    statusDiv.text('เกิดข้อผิดพลาด: ' + response.message);
                }
            }, 'json').always(function() {
                btn.prop('disabled', false).removeClass('opacity-50'); // เปิดปุ่มเมื่อเสร็จสิ้น
            });
        });
    });
    </script>
</body>
</html>
