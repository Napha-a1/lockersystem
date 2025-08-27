<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['admin_email'])) {
    header('Location: admin.php');
    exit();
}

// ฟังก์ชันสำหรับตรวจสอบสถานะออนไลน์ของ ESP32 โดยเรียก API ภายใน
function isEsp32Online($ip_address) {
    // ต้องตรวจสอบว่า IP Address เป็นค่าว่างหรือไม่ก่อนเรียก cURL
    if (empty($ip_address)) {
        return false;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$ip_address}/status"); // สมมติว่า ESP32 มี endpoint /status
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // ตั้ง timeout สั้นๆ
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($http_code === 200); // ถ้ารับ HTTP 200 กลับมา ถือว่าออนไลน์
}

// ฟังก์ชันสำหรับดึงข้อมูล Locker ทั้งหมด
function getAllLockers($conn) {
    try {
        $stmt = $conn->query("SELECT id, locker_number, status, esp32_ip_address, user_email, start_time, end_time, price_per_hour FROM lockers ORDER BY locker_number ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lockers in all_locker_status.php: " . $e->getMessage());
        return [];
    }
}

// ฟังก์ชันตรวจสอบว่าล็อกเกอร์หมดเวลาการใช้งานแล้วหรือไม่
function isLockerExpired($endTime) {
    if (!$endTime) {
        return false;
    }
    $now = new DateTime();
    $end_time_dt = new DateTime($endTime);
    return $now > $end_time_dt;
}

$lockers = getAllLockers($conn);
$add_error = '';
$add_success = '';

// จัดการการเพิ่ม Locker ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_locker'])) {
    $new_locker_number = $_POST['locker_number'] ?? null;
    $new_esp32_ip = $_POST['esp32_ip_address'] ?? null;
    $new_price = $_POST['price_per_hour'] ?? null;

    if (empty($new_locker_number) || empty($new_esp32_ip) || empty($new_price)) {
        $add_error = "กรุณากรอกข้อมูล Locker ให้ครบถ้วน (หมายเลข, IP, ราคา)";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO lockers (locker_number, esp32_ip_address, price_per_hour, status) VALUES (:locker_number, :esp32_ip_address, :price_per_hour, 'available')");
            $stmt->bindParam(':locker_number', $new_locker_number, PDO::PARAM_INT);
            $stmt->bindParam(':esp32_ip_address', $new_esp32_ip, PDO::PARAM_STR);
            $stmt->bindParam(':price_per_hour', $new_price, PDO::PARAM_STR);
            $stmt->execute();
            $add_success = "เพิ่ม Locker หมายเลข {$new_locker_number} สำเร็จแล้ว!";
            $lockers = getAllLockers($conn); // โหลดข้อมูล Locker ใหม่หลังจากเพิ่ม
        } catch (PDOException $e) {
            error_log("Error adding new locker: " . $e->getMessage());
            $add_error = "เกิดข้อผิดพลาดในการเพิ่ม Locker: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถานะ Locker ทั้งหมด (Admin)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .table-header { background-color: #e2e8f0; } /* gray-200 */
        .status-online { color: #10B981; } /* green-500 */
        .status-offline { color: #EF4444; } /* red-500 */
        .status-occupied { color: #F59E0B; } /* amber-500 */
        .status-available { color: #10B981; } /* green-500 */
        .status-expired { color: #EF4444; } /* red-500 */
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <!-- Header / Navbar -->
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Locker System (Admin)</h1>
            <nav>
                <a href="index.php" class="px-3 py-2 bg-blue-500 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-home mr-2"></i>หน้าหลัก
                </a>
                <span class="ml-4">ยินดีต้อนรับ: <?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
                <a href="logout.php" class="ml-4 px-3 py-2 bg-red-500 rounded-md hover:bg-red-600 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>ออกจากระบบ
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto p-6 flex-grow">
        <h2 class="text-3xl font-extrabold text-center text-gray-800 mb-8 flex items-center justify-center">
            <i class="fas fa-cubes mr-3 text-blue-600"></i>จัดการ Locker ทั้งหมด
        </h2>
        <p class="text-center text-gray-600 mb-10">ดูสถานะและเพิ่ม Locker ใหม่เข้าสู่ระบบ</p>

        <!-- Add New Locker Form -->
        <div class="bg-white shadow-lg rounded-lg p-6 mb-8 max-w-lg mx-auto">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">เพิ่ม Locker ใหม่</h3>
            <?php if ($add_success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg shadow-md" role="alert">
                    <p><?= htmlspecialchars($add_success) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($add_error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg shadow-md" role="alert">
                    <p><?= htmlspecialchars($add_error) ?></p>
                </div>
            <?php endif; ?>
            <form action="all_locker_status.php" method="POST" class="space-y-4">
                <div>
                    <label for="locker_number" class="block text-gray-700 text-sm font-bold mb-2">หมายเลข Locker:</label>
                    <input type="number" id="locker_number" name="locker_number" required
                           class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="esp32_ip_address" class="block text-gray-700 text-sm font-bold mb-2">IP Address (ESP32):</label>
                    <input type="text" id="esp32_ip_address" name="esp32_ip_address" required pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                           title="กรุณากรอก IP Address ที่ถูกต้อง เช่น 192.168.1.100"
                           class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="price_per_hour" class="block text-gray-700 text-sm font-bold mb-2">ราคาต่อชั่วโมง:</label>
                    <input type="number" step="0.01" id="price_per_hour" name="price_per_hour" required
                           class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" name="add_locker"
                        class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition-colors duration-200">
                    <i class="fas fa-plus-circle mr-2"></i> เพิ่ม Locker
                </button>
            </form>
        </div>

        <!-- Locker Status Table -->
        <?php if (empty($lockers)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">ไม่พบข้อมูล Locker</p>
                <p>ยังไม่มี Locker ในระบบ คุณสามารถเพิ่มได้จากฟอร์มด้านบน</p>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="table-header">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                หมายเลข Locker
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                สถานะระบบ
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                IP Address (ESP32)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                สถานะการใช้งาน
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                ผู้ใช้งาน
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                เริ่มใช้งาน
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                สิ้นสุด
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                ราคา/ชม.
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($lockers as $locker): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($locker['locker_number']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php 
                                    $is_online = isEsp32Online($locker['esp32_ip_address']);
                                    $system_status_text = $is_online ? 'Online' : 'Offline';
                                    $system_status_class = $is_online ? 'bg-green-100 text-green-700 border-green-500' : 'bg-red-100 text-red-700 border-red-500';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $system_status_class; ?>">
                                        <?= $system_status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= htmlspecialchars($locker['esp32_ip_address'] ?? '-') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $usage_status_text = '';
                                    $usage_status_class = '';
                                    $is_expired = isLockerExpired($locker['end_time']);

                                    if ($locker['status'] === 'occupied') {
                                        if ($is_expired) {
                                            $usage_status_text = 'หมดเวลาแล้ว';
                                            $usage_status_class = 'bg-red-100 text-red-700 border-red-500';
                                        } else {
                                            $usage_status_text = 'ใช้งานอยู่';
                                            $usage_status_class = 'bg-yellow-100 text-yellow-700 border-yellow-500';
                                        }
                                    } elseif ($locker['status'] === 'available') {
                                        $usage_status_text = 'ว่าง';
                                        $usage_status_class = 'bg-green-100 text-green-700 border-green-500';
                                    } else {
                                        $usage_status_text = 'ไม่ทราบสถานะ';
                                        $usage_status_class = 'bg-gray-100 text-gray-500 border-gray-400';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $usage_status_class; ?>">
                                        <?= $usage_status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= htmlspecialchars($locker['user_email'] ?? '-') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= ($locker['start_time'] && $locker['status'] === 'occupied' && !$is_expired) ? date('Y-m-d H:i', strtotime($locker['start_time'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= ($locker['end_time'] && $locker['status'] === 'occupied' && !$is_expired) ? date('Y-m-d H:i', strtotime($locker['end_time'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= number_format($locker['price_per_hour'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

</body>
</html>
