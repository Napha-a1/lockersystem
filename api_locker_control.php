<?php
header('Content-Type: text/plain'); // Set Content-Type to plain text for response
session_start();
include 'connect.php'; // Connect to PostgreSQL database using PDO

// Define the base IP address or a prefix for your ESP32 devices.
// IMPORTANT: You should store the full IP address for each ESP32 in your database.
// For demonstration, we'll assume a prefix and append the locker number,
// or you can add a dedicated 'esp32_ip_address' column to your 'lockers' table.
$esp32IpAddress = "192.168.1.100"; // <<< Change this to your actual ESP32's IP address, or retrieve from DB.
                                 // Recommendation: Add 'esp32_ip_address' column to 'lockers' table.

// Check if necessary parameters are provided
if (isset($_GET['locker_number']) && isset($_GET['user_email']) && isset($_GET['action'])) {
    $lockerNumber = $_GET['locker_number'];
    $userEmail = $_GET['user_email'];
    $action = $_GET['action']; // 'open' or 'close'

    // Check session permissions
    // The logged-in user must be the same user attempting to control the locker
    if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] !== $userEmail) {
        echo "ERROR: Permission Denied. User session mismatch.";
        exit();
    }

    try {
        // Prevent SQL Injection using Prepared Statement to retrieve locker data
        // We now need 'blynk_virtual_pin' (which we will use as GPIO pin) and 'esp32_ip_address' if you add it.
        // For this example, we're assuming 'blynk_virtual_pin' is the GPIO pin on ESP32.
        // If you add 'esp32_ip_address' column, modify the SELECT query:
        // $sql = "SELECT status, user_email, blynk_virtual_pin, esp32_ip_address FROM lockers WHERE locker_number = :locker_number";
        $sql = "SELECT status, user_email, blynk_virtual_pin FROM lockers WHERE locker_number = :locker_number";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':locker_number', $lockerNumber);
        $stmt->execute();
        $locker_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($locker_data) {
            $status = $locker_data['status'];
            $bookedBy = $locker_data['user_email'];
            $esp32GpioPin = $locker_data['blynk_virtual_pin']; // Using blynk_virtual_pin as the GPIO pin on ESP32
            // If you added 'esp32_ip_address' column: $esp32IpAddress = $locker_data['esp32_ip_address'];

            // Check conditions: locker must be occupied by this user and in 'occupied' status
            if ($status == 'occupied' && $bookedBy == $userEmail) {
                // Determine the value to send to ESP32 based on low-active relay logic
                // 'open' means relay active (LOW = 0)
                // 'close' means relay inactive (HIGH = 1)
                $commandValue = ($action === 'open') ? 0 : 1; 
                
                // Construct the URL for the ESP32 Web Server
                $esp32Endpoint = "http://{$esp32IpAddress}/control?pin={$esp32GpioPin}&value={$commandValue}";

                // Send command to ESP32 Web Server using cURL
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $esp32Endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Do not output response directly
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Set a timeout of 5 seconds
                $esp32_response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP response code
                curl_close($ch);

                // Check if command was sent successfully
                if ($esp32_response !== false && $http_code >= 200 && $http_code < 300) {
                    if ($action === 'open') {
                        echo "OPEN (Command sent to ESP32)";
                    } else {
                        echo "CLOSED (Command sent to ESP32)";
                    }
                } else {
                    echo "ERROR: Failed to send command to ESP32. Check network connection or ESP32 Web Server. (HTTP Code: {$http_code}, Response: {$esp32_response})";
                }
            } else {
                // If conditions are not met (locker not booked by this user or status is incorrect)
                echo "ERROR: Locker is not occupied by this user or status is incorrect.";
            }
        } else {
            // Locker not found with the specified number
            echo "ERROR: Locker not found.";
        }

    } catch (PDOException $e) {
        // Log SQL errors
        error_log("SQL Error in api_locker_control.php: " . $e->getMessage());
        echo "ERROR: Database error occurred while controlling locker.";
    }
} else {
    echo "ERROR: Missing parameters. Please provide locker_number, user_email, and action.";
}
// PDO connection is automatically closed when the script finishes
?>
