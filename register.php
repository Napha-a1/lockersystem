<?php
// Include the database connection file
include 'connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } elseif (strlen($password) < 6) { // Check password length
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        try {
            // Check if email already exists
            $stmt_check = $conn->prepare("SELECT id FROM locker_users WHERE email = :email");
            $stmt_check->bindParam(':email', $email);
            $stmt_check->execute();

            if ($stmt_check->fetch(PDO::FETCH_ASSOC)) { // If email is found, it's already in use
                $error = "อีเมลนี้ถูกใช้แล้ว";
            } else {
                // Hash the password for secure storage
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new user into the database
                $stmt_insert = $conn->prepare("INSERT INTO locker_users (fullname, email, password_hash, created_at) VALUES (:fullname, :email, :password_hash, NOW())");
                
                $stmt_insert->bindParam(':fullname', $fullname);
                $stmt_insert->bindParam(':email', $email);
                $stmt_insert->bindParam(':password_hash', $hashed_password);

                if ($stmt_insert->execute()) {
                    // Redirect to login page with a success message
                    header("Location: login.php?success=" . urlencode("สมัครสมาชิกสำเร็จแล้ว! กรุณาเข้าสู่ระบบ"));
                    exit();
                } else {
                    $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
                }
            }
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage();
            error_log("Database Error in register.php: " . $e->getMessage()); // Log the error for debugging
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
        }
        .register-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .form-label {
            font-weight: 500;
        }
        .btn-success {
            background-color: #22c55e;
            border-color: #22c55e;
            transition: background-color 0.3s;
        }
        .btn-success:hover {
            background-color: #16a34a;
            border-color: #16a34a;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="text-center mb-4">สมัครสมาชิก</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" class="form-control" name="fullname" id="fullname" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">อีเมล</label>
                <input type="email" class="form-control" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-success">สมัครสมาชิก <i class="fas fa-user-check ms-2"></i></button>
            </div>
        </form>
        <div class="text-center mt-3">
            <p>มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
