<?php
session_start();
// Include the database connection file.
// IMPORTANT: For security, use environment variables to configure the database.
// Do not hard-code connection details here.
include 'connect.php';

$error = '';
$success = $_GET['success'] ?? ''; // For displaying messages from register.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'user'; // default user

    $sql = "";
    if ($role === "admin") {
        // Admin login by username
        $sql = "SELECT * FROM admins WHERE username = :username";
    } else {
        // User login by email
        $sql = "SELECT * FROM locker_users WHERE email = :username";
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) { // If user is found
            if ($role === "admin") {
                // For admin, we use password_verify on the hashed password.
                if (password_verify($password, $row['password'])) {
                    $_SESSION['admin_username'] = $row['username'];
                    header("Location: locker_status.php");
                    exit();
                } else {
                    $error = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                // For user, use password_verify.
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_email'] = $row['email'];
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
                }
            }
        } else {
            $error = "ไม่พบผู้ใช้หรือแอดมิน";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการเข้าสู่ระบบ";
        error_log("Login error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .login-container {
            max-width: 400px;
            margin-top: 50px;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-container">
                    <h2 class="text-center mb-4 font-weight-bold">เข้าสู่ระบบ</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="role" class="form-label">ประเภทผู้ใช้:</label>
                            <select name="role" id="role" class="form-select">
                                <option value="user">ผู้ใช้</option>
                                <option value="admin">แอดมิน</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้ (แอดมิน) / อีเมล (ผู้ใช้):</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">รหัสผ่าน:</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary">เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-2"></i></button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p>ยังไม่มีบัญชีใช่ไหม? <a href="register.php">สมัครสมาชิก</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
