<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เกิดข้อผิดพลาด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error-container {
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            background-color: #ffffff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545; /* Red for error */
            margin-bottom: 20px;
        }
        .error-message {
            font-size: 1.2rem;
            color: #343a40;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-exclamation-circle error-icon"></i>
        <h1>เกิดข้อผิดพลาด!</h1>
        <p class="error-message">
            <?php
                echo htmlspecialchars($_GET['message'] ?? 'ไม่สามารถดำเนินการตามคำขอของคุณได้');
            ?>
        </p>
        <a href="index.php" class="btn btn-primary"><i class="fas fa-home me-2"></i>กลับหน้าหลัก</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
