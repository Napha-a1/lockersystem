<?php
session_start();

// กำหนดข้อมูล Locker ที่จะแสดง
$locker_number = 1; // แสดงเฉพาะ Locker หมายเลข 1
$user_email = "l0tt@gmail.com"; // กำหนด user_email โดยตรง

// สถานะของ Locker จะจำลองขึ้นมาสำหรับหน้า UI นี้
// ในระบบจริง ESP32 จะเป็นตัวกำหนดสถานะ และหน้าเว็บนี้จะแสดงผลตามคำสั่งที่ส่งไป
$current_locker_status = 'unknown'; // เริ่มต้นเป็นสถานะไม่ทราบ

// ข้อความแจ้งเตือน (จาก URL parameters)
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locker Control</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .locker-card { transition: transform 0.2s ease-in-out; }
        .locker-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- Header -->
    <header class="bg-gray-800 text-white p-4 shadow-lg sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Locker Control System</h1>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="admin.php" class="hover:text-gray-300">เข้าสู่ระบบ Admin</a></li>
                    <li><a href="#" class="hover:text-gray-300">ยินดีต้อนรับ, <?= htmlspecialchars($user_email) ?></a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto p-6 mt-8">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-2">ควบคุม Locker #<?= htmlspecialchars($locker_number) ?></h1>
            <p class="text-lg text-gray-600">สำหรับผู้ใช้งาน: <?= htmlspecialchars($user_email) ?></p>
        </div>

        <!-- Alert Messages -->
        <div id="alert-container" class="mb-4">
            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg shadow-md" role="alert">
                    <p class="font-bold">สำเร็จ!</p>
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg shadow-md" role="alert">
                    <p class="font-bold">เกิดข้อผิดพลาด!</p>
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Locker Card (Single) -->
        <div class="max-w-md mx-auto locker-card bg-white p-6 rounded-2xl shadow-xl flex flex-col justify-between items-center text-center">
            <div class="w-full">
                <i id="locker-icon" class="fas fa-lock text-5xl mb-4 text-gray-500"></i>
                <h2 class="text-3xl font-bold mb-2 text-gray-800">Locker #<?= htmlspecialchars($locker_number) ?></h2>
                <p class="text-xl text-gray-600 mb-4">
                    สถานะ: <span id="locker-status-text" class="font-semibold text-gray-600">
                        ไม่ทราบสถานะ
                    </span>
                </p>
            </div>
            
            <!-- Control Section -->
            <div class="w-full space-y-4">
                <button onclick="sendEsp32Command('on')"
                    class="w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition duration-300">
                    <i class="fas fa-door-open mr-2"></i> เปิดล็อกเกอร์
                </button>
                
                <button onclick="sendEsp32Command('off')"
                    class="w-full px-6 py-3 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition duration-300">
                    <i class="fas fa-door-closed mr-2"></i> ปิดล็อกเกอร์
                </button>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>
    <script>
        const esp32_ip = "10.242.194.185"; // IP Address ของ ESP32

        function showStatusAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertHtml = `
                <div class="bg-${type}-100 border-l-4 border-${type}-500 text-${type}-700 p-4 mb-4 rounded-lg shadow-md" role="alert">
                    <p class="font-bold">${type === 'success' ? 'สำเร็จ!' : 'เกิดข้อผิดพลาด!'}</p>
                    <p>${message}</p>
                </div>
            `;
            alertContainer.innerHTML = alertHtml;
        }

        function updateLockerStatusUI(status) {
            const lockerIcon = document.getElementById('locker-icon');
            const lockerStatusText = document.getElementById('locker-status-text');
            const lockerCard = document.querySelector('.locker-card');

            // Reset classes
            lockerIcon.className = 'fas text-5xl mb-4';
            lockerStatusText.className = 'font-semibold';
            lockerCard.classList.remove('status-available', 'status-occupied');

            if (status === 'on') {
                lockerIcon.classList.add('fa-lock-open', 'text-green-500');
                lockerStatusText.classList.add('text-green-600');
                lockerStatusText.textContent = 'เปิดอยู่';
                lockerCard.classList.add('status-available');
            } else if (status === 'off') {
                lockerIcon.classList.add('fa-lock', 'text-red-500');
                lockerStatusText.classList.add('text-red-600');
                lockerStatusText.textContent = 'ปิดอยู่';
                lockerCard.classList.add('status-occupied');
            } else {
                lockerIcon.classList.add('fa-question-circle', 'text-gray-500');
                lockerStatusText.classList.add('text-gray-600');
                lockerStatusText.textContent = 'ไม่ทราบสถานะ';
            }
        }

        function sendEsp32Command(command) {
            const url = `http://${esp32_ip}/${command}`; // สร้าง URL สำหรับส่งคำสั่ง
            console.log(`Sending command to ESP32: ${url}`);

            fetch(url, {
                method: 'GET' 
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); 
            })
            .then(result => {
                console.log('ESP32 Response:', result);
                let message = `คำสั่ง ${command === 'on' ? 'เปิด' : 'ปิด'} ล็อกเกอร์ถูกส่งสำเร็จ.`;
                showStatusAlert(message, 'success');
                updateLockerStatusUI(command); // อัปเดต UI ทันที
            })
            .catch(error => {
                console.error('Error sending command to ESP32:', error);
                let message = `เกิดข้อผิดพลาดในการส่งคำสั่ง ${command === 'on' ? 'เปิด' : 'ปิด'} ล็อกเกอร์: ${error.message}`;
                showStatusAlert(message, 'red');
                updateLockerStatusUI('unknown'); // แสดงสถานะไม่ทราบ
            });
        }
    </script>
</body>
</html>
