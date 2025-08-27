<?php
session_start();
include 'connect.php';
header('Content-Type: application/json');

function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

if (!isset($_SESSION['user_email'])) {
    sendJsonResponse('error', 'Authentication failed: User not logged in.');
}

$locker_id = $_POST['locker_id'] ?? null;
$user_email = $_SESSION['user_email'];

if (empty($locker_id)) {
    sendJsonResponse('error', 'Missing required parameter: locker_id.');
}

try {
    $conn->beginTransaction();

    $stmt_check = $conn->prepare("SELECT id FROM lockers WHERE id = :id AND user_email = :user_email AND status = 'occupied' FOR UPDATE");
    $stmt_check->bindParam(':id', $locker_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $stmt_check->execute();
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $conn->rollBack();
        sendJsonResponse('error', 'You do not have permission to cancel this booking or it is not currently occupied by you.');
    }

    $update_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :id";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bindParam(':id', $locker_id, PDO::PARAM_INT);
    
    if (!$update_stmt->execute()) {
        $conn->rollBack();
        throw new Exception("Failed to update locker status.");
    }

    $conn->commit();
    sendJsonResponse('success', 'Booking canceled successfully.');

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Cancellation Error: " . $e->getMessage());
    sendJsonResponse('error', 'An error occurred while canceling the booking.');
}
?>
