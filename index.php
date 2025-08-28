<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล (ยังจำเป็นสำหรับล็อกเกอร์อื่นๆ และระบบล็อกอิน/แอดมิน)

$current_user_email = $_SESSION['user_email'] ?? null;
$is_admin = isset($_SESSION['admin_username']);

// IP Address ของอุปกรณ์ ESP32 สำหรับ Locker 1
// *** สำคัญ: ตรวจสอบให้แน่ใจว่า IP Address นี้ถูกต้องและสามารถเข้าถึงได้ ***
$esp32_ip_locker1 = "10.242.194.185"; 

// ข้อความแจ้งเตือน (ถ้ามี)
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// ดึงข้อมูล Locker ทั้งหมดจากฐานข้อมูล (สำหรับแสดง Locker อื่นๆ ที่ไม่ใช่ Locker 1)
function getAllLockers($conn) {
    try {
        $stmt = $conn->query("SELECT id, locker_number, status, esp32_ip_address, user_email, start_time, end_time, price_per_hour FROM lockers WHERE locker_number != 1 ORDER BY locker_number ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching other lockers: " . $e->getMessage());
        return [];
    }
}
$other_lockers = getAllLockers($conn);

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
                <?php if ($current_user_email || $is_admin): ?>
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

        <!-- Dedicated Container for Locker #1 (10.242.194.185) - ปุ่มควบคุมด้วย AJAX ไม่สน DB และไม่แสดง error -->
        <div class="flex justify-center mb-10">
            <div class="card bg-white rounded-lg shadow-lg p-6 w-full max-w-sm border-4 border-blue-500">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xl font-bold text-gray-700">Locker หลัก #1</span>
                    <span id="locker-1-status-badge" class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                        สถานะ: ไม่ทราบ
                    </span> 
                </div>
                <p class="text-gray-600 mb-2">ควบคุม Locker #1</p>
                <p class="text-gray-600 text-sm mb-4">
                   
                    <span class="font-bold text-blue-500"></span>
                  
                </p>

                <div class="flex space-x-2 mt-4">
                    <button class="direct-ip-ajax-control-btn w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200" 
                        data-command="on">
                        <i class="fas fa-door-open mr-2"></i> เปิด Locker
                    </button>
                    <button class="direct-ip-ajax-control-btn w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200" 
                        data-command="off">
                        <i class="fas fa-door-closed mr-2"></i> ปิด Locker
                    </button>
                </div>
                <!-- ส่วนนี้จะแสดงข้อความสถานะชั่วคราวขณะส่งคำสั่งเท่านั้น ไม่ใช่ข้อผิดพลาดถาวร -->
                <div id="status-display-1" class="mt-4 text-center text-gray-600"></div>
            </div>
        </div>

        <!-- ล็อกเกอร์อื่นๆ ในระบบ - ถูกย้ายมาอยู่ด้านล่าง -->
        <h3 class="text-2xl font-bold text-center text-gray-800 mb-6">ล็อกเกอร์อื่นๆ ในระบบ</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php if (!empty($other_lockers)): ?>
                <?php foreach ($other_lockers as $locker): ?>
                    <?php 
                        $is_occupied = ($locker['status'] === 'occupied');
                        $is_available = ($locker['status'] === 'available');
                        $is_expired = ($locker['end_time'] && strtotime($locker['end_time']) < time());
                        $is_online_config = !empty($locker['esp32_ip_address']); 
                    ?>
                    <div class="card bg-white rounded-lg shadow-lg p-6 
                        <?= $is_occupied ? 'border-yellow-500' : 
                          ($is_available ? 'border-green-500' : 'border-gray-300') ?>">
                        
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xl font-bold text-gray-700">ล็อกเกอร์ #<?= htmlspecialchars($locker['locker_number']) ?></span>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                <?= $is_occupied ? 'bg-yellow-100 text-yellow-800' : 
                                  ($is_available ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') ?>">
                                สถานะ: <?= $is_occupied ? 'ไม่ว่าง' : ($is_available ? 'ว่าง' : 'ไม่ทราบ') ?>
                            </span>
                        </div>
                        <p class="text-gray-600 mb-2"><strong>ผู้ใช้งาน:</strong> <?= htmlspecialchars($locker['user_email'] ?? '-') ?></p>
                        <p class="text-gray-600 mb-4"><strong>หมดเวลา:</strong> <?= $locker['end_time'] ? date('d/m/Y H:i', strtotime($locker['end_time'])) : '-' ?></p>

                        <p class="text-gray-600 text-sm mb-2"><strong>สถานะเชื่อมต่อ:</strong> 
                            <span class="badge <?= $is_online_config ? 'bg-primary' : 'bg-secondary' ?>">
                                <i class="fas <?= $is_online_config ? 'fa-globe' : 'fa-unlink' ?> mr-1"></i>
                                <?= $is_online_config ? 'ออนไลน์' : 'ออฟไลน์' ?>
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
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="col-span-full text-center text-gray-600">ยังไม่มีล็อกเกอร์อื่น ๆ ในระบบ</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

    <script>
    $(document).ready(function() {
        // สำหรับปุ่มควบคุม Locker #1 โดยตรงด้วย AJAX (ไม่สนฐานข้อมูลและไม่แสดง error)
        $('.direct-ip-ajax-control-btn').on('click', function() {
            var command = $(this).data('command'); // 'on' or 'off'
            var esp32Ip = '<?= htmlspecialchars($esp32_ip_locker1) ?>'; // IP ที่กำหนดไว้ตายตัว
            
            var statusDiv = $('#status-display-1');
            var statusBadge = $('#locker-1-status-badge');
            var btn = $(this);

            // แสดงสถานะ "กำลังส่งคำสั่ง..." ชั่วคราว
            statusDiv.text('กำลังส่งคำสั่ง...');
            btn.prop('disabled', true).addClass('opacity-50'); // ปิดปุ่มชั่วคราว

            // ส่งคำสั่ง HTTP GET โดยตรงไปยัง ESP32 ด้วย AJAX
            $.ajax({
                url: 'http://' + esp32Ip + '/' + command,
                type: 'GET',
                timeout: 5000, // กำหนด timeout 5 วินาที
                success: function(response) {
                    // สมมติว่า ESP32 ตอบกลับเป็น "OK" เมื่อสำเร็จ
                    if (response.trim() === 'OK') {
                        statusDiv.text('คำสั่ง "' + (command === 'on' ? 'เปิด' : 'ปิด') + '" สำเร็จ!');
                        if(command === 'on') {
                            statusBadge.removeClass('bg-red-100 text-red-800 bg-gray-100 text-gray-800').addClass('bg-yellow-100 text-yellow-800').text('สถานะ: เปิดอยู่'); 
                        } else {
                            statusBadge.removeClass('bg-yellow-100 text-yellow-800 bg-gray-100 text-gray-800').addClass('bg-green-100 text-green-800').text('สถานะ: ปิดอยู่'); 
                        }
                    } else {
                        // หาก ESP32 ตอบกลับแต่ไม่ใช่ "OK"
                        statusDiv.text(''); // ล้างข้อความสถานะ
                        // console.error('ESP32 responded but not "OK":', response); // บันทึกใน console log
                    }
                },
                error: function(xhr, status, error) {
                    // หากเกิดข้อผิดพลาดในการเชื่อมต่อ (เช่น ESP32 ออฟไลน์)
                    statusDiv.text(''); // ล้างข้อความสถานะ
                    // console.error('Error connecting to ESP32:', error); // บันทึกใน console log
                }
            }).always(function() {
                // ไม่ว่าจะสำเร็จหรือล้มเหลว ให้เปิดปุ่มและลบ opacity
                btn.prop('disabled', false).removeClass('opacity-50'); 
            });
        });
    });
    </script>
</body>
</html>
