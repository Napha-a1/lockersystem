<?php
// ... (ส่วนหัวของโค้ดเหมือนเดิม) ...

try {
    // ... (ส่วนตรวจสอบสิทธิ์เหมือนเดิม) ...

    if ($controlSuccess) {
        // Start a database transaction
        $conn->beginTransaction();

        // If action is 'close', update the locker's status and record the booking
        if ($action === 'close') {
            // Retrieve start_time and price_per_hour from the current locker entry
            $get_info_stmt = $conn->prepare("SELECT start_time, price_per_hour FROM lockers WHERE id = :id");
            $get_info_stmt->bindParam(':id', $locker['id']);
            $get_info_stmt->execute();
            $locker_info = $get_info_stmt->fetch(PDO::FETCH_ASSOC);
            $start_time = $locker_info['start_time'];
            $price_per_hour = $locker_info['price_per_hour'];
            $end_time = date('Y-m-d H:i:s');
            
            // Calculate total price
            $diff_seconds = strtotime($end_time) - strtotime($start_time);
            $diff_hours = $diff_seconds / 3600;
            $total_price = $price_per_hour * $diff_hours;

            // Update the locker status
            $update_stmt = $conn->prepare("UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :id");
            $update_stmt->bindParam(':id', $locker['id']);
            $update_stmt->execute();
            
            // Insert the completed booking record into the new bookings table
            $insert_booking_stmt = $conn->prepare("
                INSERT INTO bookings (locker_id, user_email, start_time, end_time, price_per_hour, total_price, status)
                VALUES (:locker_id, :user_email, :start_time, :end_time, :price_per_hour, :total_price, 'completed')
            ");
            $insert_booking_stmt->bindParam(':locker_id', $locker['id']);
            $insert_booking_stmt->bindParam(':user_email', $userEmail);
            $insert_booking_stmt->bindParam(':start_time', $start_time);
            $insert_booking_stmt->bindParam(':end_time', $end_time);
            $insert_booking_stmt->bindParam(':price_per_hour', $price_per_hour);
            $insert_booking_stmt->bindParam(':total_price', $total_price);
            $insert_booking_stmt->execute();
        }

        // Commit the transaction
        $conn->commit();
        sendJsonResponse('success', 'Locker control command sent successfully.');
    } else {
        $conn->rollBack();
        sendJsonResponse('error', 'Failed to send command to locker hardware.');
    }

} catch (PDOException $e) {
    // Rollback the transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Database Error in api_control.php: " . $e->getMessage());
    sendJsonResponse('error', 'An internal server error occurred.');
}

// ... (ส่วนท้ายของโค้ดเหมือนเดิม) ...
?>
