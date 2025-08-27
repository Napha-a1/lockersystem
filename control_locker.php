<?php
// เริ่มต้นการใช้งาน session
session_start();

// ตรวจสอบว่าผู้ใช้ได้ล็อกอินแล้วหรือไม่
// ในระบบจริง คุณจะใช้ session เพื่อตรวจสอบสิทธิ์ของผู้ใช้
// สำหรับตัวอย่างนี้ เราจะสมมติว่าผู้ใช้ล็อกอินแล้ว
if (!isset($_SESSION['user_email'])) {
    // หากไม่ได้ล็อกอิน ให้เปลี่ยนเส้นทางไปหน้าล็อกอิน
    // header('Location: login.php');
    // exit();
    $_SESSION['user_email'] = 'testuser@example.com'; // สมมติผู้ใช้สำหรับตัวอย่าง
}

$message = ""; // ข้อความแจ้งเตือน
$is_error = false; // สถานะของข้อความ

// ตรวจสอบว่ามีการส่งข้อมูลจากฟอร์มหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $locker_number = $_POST['locker_number'] ?? '';
    $command = $_POST['command'] ?? '';
    $user_email = $_SESSION['user_email'];

    // ที่อยู่ IP ของอุปกรณ์ (ESP32)
    // *** สำคัญ: เปลี่ยน IP Address นี้เป็นของอุปกรณ์จริงของคุณ! ***
    $esp32_ip = "http://192.168.1.100";
    $api_endpoint = "/control"; // Endpoint ที่กำหนดในโค้ด Arduino
    
    // สร้าง URL คำสั่ง
    // รูปแบบควรเป็น http://<IP_ADDRESS>/control?locker=<หมายเลข>&command=<คำสั่ง>
    $url = "{$esp32_ip}{$api_endpoint}?locker={$locker_number}&command={$command}";

    // ใช้ cURL เพื่อส่งคำสั่งไปยังอุปกรณ์
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // ตั้งค่าให้ cURL คืนค่าเป็นสตริงแทนที่จะแสดงผลทันที
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // ตั้งค่า Timeout 5 วินาที เพื่อป้องกันการรอที่นานเกินไป

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    // ปิด cURL
    curl_close($ch);

    // ตรวจสอบผลลัพธ์
    if ($curl_error) {
        $message = "เกิดข้อผิดพลาดในการเชื่อมต่อ: " . htmlspecialchars($curl_error);
        $is_error = true;
    } elseif ($http_code != 200) {
        $message = "ไม่สามารถเชื่อมต่อกับอุปกรณ์ได้ (รหัส HTTP: " . htmlspecialchars($http_code) . ")";
        $is_error = true;
    } else {
        // ตรวจสอบการตอบกลับจากอุปกรณ์
        // อุปกรณ์ควรส่งข้อความ 'SUCCESS' กลับมาเมื่อทำสำเร็จ
        if (trim($response) === 'SUCCESS') {
            $message = "ส่งคำสั่ง '". htmlspecialchars($command) ."' ไปยังล็อกเกอร์ ". htmlspecialchars($locker_number) ." สำเร็จ!";
            $is_error = false;
        } else {
            $message = "ได้รับข้อความตอบกลับที่ไม่คาดคิดจากอุปกรณ์: " . htmlspecialchars($response);
            $is_error = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ควบคุมล็อกเกอร์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white rounded-xl shadow-2xl p-6 md:p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                <i class="fas fa-lock text-blue-500 mr-2"></i>
                หน้าควบคุมล็อกเกอร์
            </h1>
            <p class="text-gray-500 mt-2">โปรดเลือกหมายเลขล็อกเกอร์และคำสั่งที่ต้องการ</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $is_error ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?> transition-all duration-300 ease-in-out transform">
                <div class="flex items-center">
                    <i class="mr-2 <?= $is_error ? 'fas fa-exclamation-circle' : 'fas fa-check-circle' ?>"></i>
                    <p class="font-medium"><?= htmlspecialchars($message) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label for="locker_number" class="block text-sm font-medium text-gray-700">หมายเลขล็อกเกอร์:</label>
                <input type="number" id="locker_number" name="locker_number" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 p-2.5 transition-colors">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button type="submit" name="command" value="open" class="w-full py-3 px-4 flex items-center justify-center text-white bg-blue-500 hover:bg-blue-600 transition-all duration-300 rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <i class="fas fa-unlock-alt mr-2"></i>
                    เปิดล็อกเกอร์
                </button>
                <button type="submit" name="command" value="close" class="w-full py-3 px-4 flex items-center justify-center text-white bg-red-500 hover:bg-red-600 transition-all duration-300 rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <i class="fas fa-lock mr-2"></i>
                    ปิดล็อกเกอร์
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            โค้ดนี้ใช้ cURL ใน PHP เพื่อส่งคำสั่งไปยังอุปกรณ์ฮาร์ดแวร์โดยตรง
        </p>

    </div>
</body>
</html>
