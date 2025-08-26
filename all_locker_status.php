<?php
session_start();
include 'connect.php'; // เชื่อมต่อฐานข้อมูล PDO สำหรับ PostgreSQL

// ฟังก์ชันสำหรับดึงข้อมูล Locker ทั้งหมด
function getAllLockers($conn) {
    try {
        $stmt = $conn->query("SELECT id, locker_number, status, user_email, start_time, end_time FROM lockers ORDER BY locker_number ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lockers in all_locker_status.php: " . $e->getMessage());
        return [];
    }
}

// ดึงข้อมูล Locker ทั้งหมด
$lockers = getAllLockers($conn);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถานะ Locker ทั้งหมด</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .table-header { background-color: #e2e8f0; } /* gray-200 */
        .status-available { color: #10B981; } /* green-500 */
        .status-occupied { color: #F59E0B; } /* amber-500 */
        .status-reserved { color: #3B82F6; } /* blue-500 */
        .status-maintenance { color: #6B7280; } /* gray-500 */
        .status-expired { color: #EF4444; } /* red-500 */
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <!-- Header / Navbar -->
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Locker System</h1>
            <nav>
                <a href="index.php" class="px-3 py-2 bg-blue-500 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-home mr-2"></i>หน้าแรก
                </a>
                <?php if (isset($_SESSION['user_email'])): ?>
                    <span class="ml-4">ยินดีต้อนรับ: <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                    <a href="logout.php" class="ml-4 px-3 py-2 bg-red-500 rounded-md hover:bg-red-600 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>ออกจากระบบ
                    </a>
                <?php else: ?>
                    <a href="login.php" class="ml-4 px-3 py-2 bg-green-500 rounded-md hover:bg-green-600 transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto p-6 flex-grow">
        <h2 class="text-3xl font-extrabold text-center text-gray-800 mb-8 flex items-center justify-center">
            <i class="fas fa-info-circle mr-3 text-blue-600"></i>สถานะ Locker ทั้งหมด
        </h2>
        <p class="text-center text-gray-600 mb-10">ตรวจสอบสถานะและข้อมูลการใช้งานของ Locker ทั้งหมดในระบบ</p>

        <?php if (empty($lockers)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">ไม่พบข้อมูล Locker</p>
                <p>อาจเกิดข้อผิดพลาดในการดึงข้อมูลหรือยังไม่มี Locker ในระบบ</p>
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
                                สถานะ
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
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($lockers as $locker): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?php echo htmlspecialchars($locker['locker_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $status_text = '';
                                    $status_class = '';
                                    $now_dt = new DateTime();
                                    $is_expired = false;

                                    if ($locker['status'] === 'occupied') {
                                        $end_time_dt = new DateTime($locker['end_time']);
                                        if ($end_time_dt <= $now_dt) {
                                            $status_text = 'หมดเวลาแล้ว';
                                            $status_class = 'status-expired';
                                            $is_expired = true;
                                        } else {
                                            $status_text = 'ใช้งานอยู่';
                                            $status_class = 'status-occupied';
                                        }
                                    } elseif ($locker['status'] === 'available') {
                                        $status_text = 'ว่าง';
                                        $status_class = 'status-available';
                                    } elseif ($locker['status'] === 'reserved') {
                                        $status_text = 'ถูกจอง';
                                        $status_class = 'status-reserved';
                                    } elseif ($locker['status'] === 'maintenance') {
                                        $status_text = 'ซ่อมบำรุง';
                                        $status_class = 'status-maintenance';
                                    } else {
                                        $status_text = 'ไม่ทราบสถานะ';
                                        $status_class = 'text-gray-400';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo str_replace('color-', 'bg-', $status_class) . ' ' . $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo htmlspecialchars($locker['user_email'] ?? '-'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ($locker['start_time'] && $locker['status'] === 'occupied' && !$is_expired) ? date('Y-m-d H:i', strtotime($locker['start_time'])) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ($locker['end_time'] && $locker['status'] === 'occupied' && !$is_expired) ? date('Y-m-d H:i', strtotime($locker['end_time'])) : '-'; ?>
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
