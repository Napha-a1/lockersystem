<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล (ยังคงต้องมีสำหรับ Session และ Navbar)

// ดึงข้อมูลล็อกเกอร์ (สำหรับ Navbar เท่านั้น)
$current_user_email = $_SESSION['user_email'] ?? null;

// ข้อความแจ้งเตือน (ถ้ามี)
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// IP Address ของอุปกรณ์ ESP32 สำหรับ Locker 1
// *** สำคัญ: ตรวจสอบให้แน่ใจว่า IP Address นี้ถูกต้องและสามารถเข้าถึงได้ ***
$esp32_ip_locker1 = "10.242.194.185"; 

// สถานะจำลองของ Locker 1
// สถานะนี้จะอัปเดตบน UI โดย JavaScript หลังจากส่งคำสั่ง
$locker1_status_display = "ไม่ทราบ"; 

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
                <?php if ($current_user_email): ?>
                    <span class="text-gray-600 mr-4">สวัสดี, <?= htmlspecialchars($current_user_email) ?></span>
                    <a href="book_locker.php" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i>จองล็อกเกอร์
                    </a>
                    <a href="locker_status.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors ml-2">
                        <i class="fas fa-list-ul mr-2"></i>สถานะล็อกเกอร์
                    </a>
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
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">ควบคุมล็อกเกอร์หมายเลข 1</h2>
        
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

        <div class="flex justify-center">
            <!-- Card สำหรับ Locker 1 -->
            <div class="card bg-white rounded-lg shadow-lg p-6 w-full max-w-sm border-4 border-blue-500">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xl font-bold text-gray-700">ล็อกเกอร์ #1</span>
                    <span id="locker-1-status-badge" class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">สถานะ: <?= $locker1_status_display ?></span> 
                </div>
                <p class="text-gray-600 mb-4">ควบคุมล็อกเกอร์หมายเลข 1 โดยตรงผ่าน IP</p>

                <div class="flex space-x-2 mt-4">
                    <button class="control-btn w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200" data-locker-number="1" data-command="on">
                        <i class="fas fa-door-open mr-2"></i> เปิดล็อกเกอร์
                    </button>
                    <button class="control-btn w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center transition-colors duration-200" data-locker-number="1" data-command="off">
                        <i class="fas fa-door-closed mr-2"></i> ปิดล็อกเกอร์
                    </button>
                </div>
                <div id="status-1" class="mt-4 text-center text-gray-600"></div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

    <script>
    $(document).ready(function() {
        $('.control-btn').on('click', function() {
            var lockerNumber = $(this).data('locker-number');
            var command = $(this).data('command'); // 'on' or 'off'
            var statusDiv = $('#status-' + lockerNumber);
            var statusBadge = $('#locker-' + lockerNumber + '-status-badge');
            var btn = $(this);

            statusDiv.text('กำลังส่งคำสั่ง...');
            btn.prop('disabled', true).addClass('opacity-50'); // ปิดปุ่มชั่วคราว

            $.post('control_locker.php', { 
                locker_number: lockerNumber, 
                command: command,
                esp32_ip: '<?= htmlspecialchars($esp32_ip_locker1) ?>' 
            }, function(response) {
                if (response.status === 'SUCCESS') {
                    statusDiv.text('คำสั่ง "' + command + '" สำเร็จ!');
                    // อัปเดตสถานะใน UI ทันที
                    if(command === 'on') {
                        statusBadge.removeClass('bg-red-100 text-red-800 bg-gray-100 text-gray-800').addClass('bg-green-100 text-green-800').text('สถานะ: เปิดอยู่');
                    } else {
                        statusBadge.removeClass('bg-green-100 text-green-800 bg-gray-100 text-gray-800').addClass('bg-red-100 text-red-800').text('สถานะ: ปิดอยู่');
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
