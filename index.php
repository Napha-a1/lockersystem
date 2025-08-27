<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// Function to fetch all locker data
function getAllLockers($conn) {
    try {
        $stmt = $conn->query("SELECT id, locker_number, status, esp32_ip_address, user_email, end_time, price_per_hour FROM lockers ORDER BY locker_number ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lockers: " . $e->getMessage());
        return [];
    }
}

// Function to check if a locker has expired
function isLockerExpired($endTime) {
    if (!$endTime) {
        return false;
    }
    $now = new DateTime();
    $end_time_dt = new DateTime($endTime);
    return $now > $end_time_dt;
}

// Get all locker data
$lockers = getAllLockers($conn);
$current_user_email = $_SESSION['user_email'] ?? null;
$has_user_lockers = false;

// Handle alert messages
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Assume Blynk/ESP32 is online for the purpose of this UI
$blynk_status = true; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการ Locker</title>
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
            <a href="index.php" class="text-2xl font-bold">ระบบจัดการ Locker</a>
            <nav>
                <ul class="flex space-x-4">
                    <?php if ($current_user_email): ?>
                        <li><a href="all_locker_status.php" class="hover:text-gray-300">สถานะทั้งหมด</a></li>
                        <li><a href="logout.php" class="hover:text-gray-300">ออกจากระบบ</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="hover:text-gray-300">เข้าสู่ระบบ</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto p-6 mt-8">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-2">สถานะ Locker ของคุณ</h1>
            <?php if ($current_user_email): ?>
                <p class="text-lg text-gray-600">ยินดีต้อนรับ, <?= htmlspecialchars($current_user_email) ?></p>
            <?php else: ?>
                <p class="text-lg text-gray-600">กรุณาเข้าสู่ระบบเพื่อดูสถานะและจัดการล็อกเกอร์ของคุณ</p>
                <a href="login.php" class="mt-4 inline-block px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition duration-300">เข้าสู่ระบบ</a>
            <?php endif; ?>
        </div>

        <!-- Alert Messages -->
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

        <!-- Locker List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (!empty($lockers)): ?>
                <?php foreach ($lockers as $locker): 
                    $is_occupied_by_user = ($locker['status'] === 'occupied' && $locker['user_email'] === $current_user_email);
                    $is_expired = isLockerExpired($locker['end_time']);
                    
                    if ($current_user_email && $is_occupied_by_user):
                        $has_user_lockers = true;
                    else:
                        continue;
                    endif;
                ?>
                    <div class="locker-card bg-white p-6 rounded-2xl shadow-xl flex flex-col justify-between items-center text-center">
                        <div class="w-full">
                            <i class="fas fa-lock text-5xl mb-4 <?= $locker['status'] === 'available' ? 'text-green-500' : 'text-yellow-500' ?>"></i>
                            <h2 class="text-3xl font-bold mb-2 text-gray-800">Locker #<?= htmlspecialchars($locker['locker_number']) ?></h2>
                            <p class="text-xl text-gray-600 mb-4">
                                สถานะ: <span class="font-semibold <?= $locker['status'] === 'available' ? 'text-green-600' : 'text-yellow-600' ?>">
                                    <?= $locker['status'] === 'available' ? 'ว่าง' : 'ไม่ว่าง' ?>
                                </span>
                            </p>
                        </div>
                        
                        <!-- Control Section -->
                        <?php if ($current_user_email): ?>
                            <?php if ($is_occupied_by_user && !$is_expired): ?>
                                <!-- Control form with robust JavaScript -->
                                <div class="w-full space-y-4">
                                    <button onclick="controlLocker(<?= htmlspecialchars($locker['locker_number']) ?>, 'open')"
                                        class="w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition duration-300">
                                        <i class="fas fa-unlock-alt mr-2"></i> เปิด/ปิด Locker
                                    </button>
                                    
                                    <form id="cancel-form-<?= htmlspecialchars($locker['locker_number']) ?>" action="cancel_booking.php" method="POST">
                                        <input type="hidden" name="locker_id" value="<?= htmlspecialchars($locker['id']) ?>">
                                        <button type="submit"
                                            class="w-full px-6 py-3 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition duration-300">
                                            <i class="fas fa-times-circle mr-2"></i> ยกเลิกการจอง
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($is_expired): ?>
                                <div class="mt-4 text-center text-red-600 font-semibold">
                                    ล็อกเกอร์หมดเวลาแล้ว ไม่สามารถควบคุมได้
                                </div>
                            <?php else: ?>
                                <div class="mt-4 text-center text-gray-500">
                                    ไม่สามารถควบคุมได้ในสถานะนี้
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$has_user_lockers && $current_user_email): ?>
                    <p class="col-span-full text-center text-gray-600">คุณยังไม่มีล็อกเกอร์ที่ใช้งานอยู่</p>
                    <a href="book_locker.php" class="col-span-full text-center mt-4 inline-block px-6 py-3 bg-green-600 text-white font-semibold rounded-lg shadow-md hover:bg-green-700 transition duration-300">จองล็อกเกอร์</a>
                <?php endif; ?>
            <?php else: ?>
                <p class="col-span-full text-center text-gray-600">ไม่มีล็อกเกอร์ในระบบ</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>
    <script>
        function controlLocker(lockerNumber, action) {
            console.log(`Sending command: ${action} to locker #${lockerNumber}`);
            
            // แสดงข้อความกำลังโหลด
            // Note: For a real application, you might add a loading spinner or disable the button.
            
            $.ajax({
                url: 'api_control.php',
                type: 'POST',
                data: {
                    locker_number: lockerNumber,
                    action: action
                },
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    console.log('API Response:', response);
                    if (response.status === 'success') {
                        // Reload the page to show the updated status
                        window.location.href = `index.php?success=${encodeURIComponent(response.message)}`;
                    } else {
                        // Show error message
                        window.location.href = `index.php?error=${encodeURIComponent(response.message)}`;
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์ กรุณาลองใหม่อีกครั้ง';
                    // Attempt to parse JSON error message if available
                    try {
                        const errorResponse = JSON.parse(jqXHR.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {
                        // Fallback to generic error message
                    }
                    window.location.href = `index.php?error=${encodeURIComponent(errorMessage)}`;
                }
            });
        }
    </script>
</body>
</html>
