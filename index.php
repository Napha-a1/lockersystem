<?php
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
        .card:hover { transform: translateY(-5px); }
        .expired-warning {
            background-color: #fef2f2; /* red-50 */
            border-left: 4px solid #ef4444; /* red-500 */
            color: #b91c1c; /* red-700 */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <!-- Header / Navbar -->
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Locker System</h1>
            <nav>
                <?php if (isset($_SESSION['user_email'])): ?>
                    <span>ยินดีต้อนรับ: <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                    <a href="logout.php" class="ml-4 px-3 py-2 bg-red-500 rounded-md hover:bg-red-600 transition-colors">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="ml-4 px-3 py-2 bg-green-500 rounded-md hover:bg-green-600 transition-colors">เข้าสู่ระบบ</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto p-6 flex-grow">
        <h2 class="text-3xl font-extrabold text-center text-gray-800 mb-8">บริการล็อกเกอร์อัจฉริยะ</h2>
        <p class="text-center text-gray-600 mb-12">จัดการล็อกเกอร์ของคุณได้อย่างง่ายดายและปลอดภัย</p>

        <?php
        // แสดงข้อความ Success/Error จากการจอง
        if (isset($_GET['success'])) {
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                    <p class="font-bold">สำเร็จ!</p>
                    <p>' . htmlspecialchars($_GET['success']) . '</p>
                  </div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                    <p class="font-bold">เกิดข้อผิดพลาด!</p>
                    <p>' . htmlspecialchars($_GET['error']) . '</p>
                  </div>';
        }
        ?>

        <!-- ส่วนของการแจ้งเตือน Locker หมดเวลา -->
        <?php if ($current_user_email):
            $user_occupied_lockers = [];
            foreach ($lockers as $locker) {
                if ($locker['user_email'] === $current_user_email && $locker['status'] === 'occupied') {
                    $user_occupied_lockers[] = $locker;
                }
            }

            foreach ($user_occupied_lockers as $locker):
                $end_time_dt = new DateTime($locker['end_time']);
                $now_dt = new DateTime();
                if ($end_time_dt <= $now_dt): ?>
                    <div class="expired-warning p-4 mb-6 rounded-md shadow-md">
                        <p class="font-bold text-lg">⚠️ ล็อกเกอร์ #<?php echo htmlspecialchars($locker['locker_number']); ?> ของคุณหมดเวลาแล้ว!</p>
                        <p>กรุณาติดต่อผู้ดูแลระบบเพื่อดำเนินการคืนล็อกเกอร์ หรืออาจมีการปรับสถานะอัตโนมัติ</p>
                    </div>
                <?php endif;
            endforeach;
        endif; ?>

        <!-- Booking & Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <!-- Card: จองล็อกเกอร์ -->
            <div class="bg-white p-6 rounded-lg shadow-lg card">
                <h3 class="text-2xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-lock mr-3 text-blue-500"></i> จองล็อกเกอร์
                </h3>
                <p class="text-gray-600 mb-6">เลือกล็อกเกอร์และช่วงเวลาที่ต้องการใช้งาน</p>
                <?php if (isset($_SESSION['user_email'])): ?>
                    <a href="book_locker.php" class="inline-block px-6 py-3 bg-blue-500 text-white font-medium rounded-md hover:bg-blue-600 transition-colors flex items-center justify-center">
                        ไปที่หน้าจอง <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                <?php else: ?>
                    <button class="inline-block px-6 py-3 bg-gray-400 text-white font-medium rounded-md cursor-not-allowed" disabled>
                        เข้าสู่ระบบเพื่อจอง
                    </button>
                <?php endif; ?>
            </div>

            <!-- Card: สถานะล็อกเกอร์ -->
            <div class="bg-white p-6 rounded-lg shadow-lg card">
                <h3 class="text-2xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-3 text-green-500"></i> สถานะล็อกเกอร์
                </h3>
                <p class="text-gray-600 mb-6">ตรวจสอบสถานะล็อกเกอร์ทั้งหมดและข้อมูลการใช้งาน</p>
                <a href="all_locker_status.php" class="inline-block px-6 py-3 bg-green-500 text-white font-medium rounded-md hover:bg-green-600 transition-colors flex items-center justify-center">
                    ดูสถานะทั้งหมด <i class="fas fa-eye ml-2"></i>
                </a>
            </div>
        </div>

        <!-- ส่วนควบคุมล็อกเกอร์ของคุณ (ปรับปรุงการแสดงผลสถานะ) -->
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-sliders-h mr-3 text-purple-600"></i> ควบคุมล็อกเกอร์ของคุณ
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (isset($_SESSION['user_email'])): ?>
                <?php $has_user_lockers = false; ?>
                <?php foreach ($lockers as $locker): ?>
                    <?php if ($locker['user_email'] === $_SESSION['user_email']): ?>
                        <?php $has_user_lockers = true; ?>
                        <div class="bg-white p-6 rounded-lg shadow-md flex flex-col justify-between">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">ล็อกเกอร์ #<?php echo htmlspecialchars($locker['locker_number']); ?></h3>
                                <?php
                                $status_text = '';
                                $status_color = '';
                                $is_expired = false;

                                if ($locker['status'] === 'occupied') {
                                    $end_time_dt = new DateTime($locker['end_time']);
                                    $now_dt = new DateTime();
                                    if ($end_time_dt <= $now_dt) {
                                        $status_text = 'หมดเวลาแล้ว';
                                        $status_color = 'text-red-500';
                                        $is_expired = true;
                                    } else {
                                        $status_text = 'ใช้งานอยู่';
                                        $status_color = 'text-yellow-600';
                                    }
                                } elseif ($locker['status'] === 'available') {
                                    $status_text = 'ว่าง';
                                    $status_color = 'text-green-500';
                                } elseif ($locker['status'] === 'reserved') {
                                    $status_text = 'ถูกจอง';
                                    $status_color = 'text-blue-500';
                                } elseif ($locker['status'] === 'maintenance') {
                                    $status_text = 'ซ่อมบำรุง';
                                    $status_color = 'text-gray-500';
                                } else {
                                    $status_text = 'ไม่ทราบสถานะ';
                                    $status_color = 'text-gray-400';
                                }
                                ?>
                                <p class="text-gray-700">สถานะ: <span class="<?php echo $status_color; ?> font-medium"><?php echo $status_text; ?></span></p>
                                <?php if ($locker['status'] === 'occupied' && !$is_expired): ?>
                                    <p class="text-gray-600 text-sm">สิ้นสุด: <?php echo date('Y-m-d H:i', strtotime($locker['end_time'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($locker['status'] === 'occupied' && !$is_expired): ?>
                                <div class="mt-4 flex flex-col space-y-2">
                                    <form action="api_locker_control.php" method="POST" class="w-full">
                                        <input type="hidden" name="locker_id" value="<?php echo htmlspecialchars($locker['id']); ?>">
                                        <input type="hidden" name="action" value="open">
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors flex items-center justify-center">
                                            <i class="fas fa-door-open mr-2"></i> เปิดล็อกเกอร์
                                        </button>
                                    </form>
                                    <form action="api_locker_control.php" method="POST" class="w-full">
                                        <input type="hidden" name="locker_id" value="<?php echo htmlspecialchars($locker['id']); ?>">
                                        <input type="hidden" name="action" value="close">
                                        <button type="submit" class="w-full px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors flex items-center justify-center">
                                            <i class="fas fa-door-closed mr-2"></i> ปิดล็อกเกอร์
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
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$has_user_lockers): ?>
                    <p class="col-span-full text-center text-gray-600">คุณยังไม่มีล็อกเกอร์ที่ใช้งานอยู่</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="col-span-full text-center text-gray-600">กรุณาเข้าสู่ระบบเพื่อดูและควบคุมล็อกเกอร์ของคุณ</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 text-center mt-auto shadow-inner">
        &copy; <?php echo date('Y'); ?> Locker System. All rights reserved.
    </footer>

</body>
</html>
