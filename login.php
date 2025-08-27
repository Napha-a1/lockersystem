<?php
session_start();
// Include the database connection file
include 'connect.php';

$error = '';
$success = $_GET['success'] ?? ''; // For displaying success message from register.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'user'; // Default role is 'user'

    $sql = "";
    if ($role === "admin") {
        // Admin login by username
        $sql = "SELECT id, password FROM admins WHERE username = :username";
    } else {
        // User login by email
        // We select both password and password_hash to handle legacy passwords
        $sql = "SELECT id, email, password, password_hash FROM locker_users WHERE email = :username";
    }

    try {
        // Prepare the SQL statement
        $stmt = $conn->prepare($sql);

        // Bind the username parameter
        $stmt->bindParam(':username', $username);

        // Execute the statement
        $stmt->execute();

        // Fetch the user data
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) { // If user is found
            if ($role === "admin") {
                // Admin password check (assuming no hashing)
                if ($password === $row['password']) {
                    $_SESSION['admin_username'] = $row['username'];
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $error = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                // Check if password_hash exists and is not null
                if (isset($row['password_hash']) && !empty($row['password_hash'])) {
                    // Verify the hashed password
                    if (password_verify($password, $row['password_hash'])) {
                        // Correct password
                        $_SESSION['user_email'] = $row['email'];
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "รหัสผ่านไม่ถูกต้อง";
                    }
                } else {
                    // Fallback to checking the old 'password' column
                    if ($password === $row['password']) {
                        // Hash the password and update the database
                        $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        $update_sql = "UPDATE locker_users SET password_hash = :new_hashed_password WHERE id = :id";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bindParam(':new_hashed_password', $new_hashed_password);
                        $update_stmt->bindParam(':id', $row['id']);
                        $update_stmt->execute();
                        
                        // Set session and redirect
                        $_SESSION['user_email'] = $row['email'];
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "รหัสผ่านไม่ถูกต้อง";
                    }
                }
            }
        } else {
            // Username or email not found
            $error = "ชื่อผู้ใช้หรืออีเมลไม่ถูกต้อง";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการเข้าสู่ระบบ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #3b82f6;
            border-color: #3b82f6;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">เข้าสู่ระบบ</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
