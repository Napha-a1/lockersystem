<?php

// Check if the script is being executed by a web server
if (php_sapi_name() != "cli") {
    // --- สคริปต์ auto_return.php เริ่มทำงาน ---
    // This log entry confirms the script started via a web request.
    log_message("--- auto_return.php script started via web request ---");

    // Check for the provided API key to prevent unauthorized access.
    // Replace 'YOUR_API_KEY' with your actual key.
    $api_key = isset($_GET['key']) ? $_GET['key'] : '';
    $expected_key = 'JWIA2@AF1!kfkova'; // Your secret key for cron job

    if ($api_key !== $expected_key) {
        // Log an unauthorized access attempt.
        log_message("ERROR: Unauthorized access attempt with key: " . $api_key);
        die("Unauthorized Access");
    }

    log_message("INFO: API key authenticated successfully.");

    // Function to connect to the PostgreSQL database.
    function connect_db() {
        $host = "dpg-cpt9kmds3k9n7n763vfg-a.singapore-postgres.render.com";
        $port = "5432";
        $dbname = "lockersystem";
        $user = "lockersystem";
        $password = "92nE19Vn9E33XFjO42F80u3WjVpW4eBf";

        try {
            // Attempt to create a new PDO instance with PostgreSQL driver.
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            // Log a detailed error message if connection fails.
            log_message("FATAL ERROR: Database connection failed: " . $e->getMessage());
            die("Connection failed: " . $e->getMessage());
        }
    }

    // Function to log messages to a file.
    function log_message($message) {
        $log_file = "auto_return_log.txt";
        // Get the current time in Thai timezone for the log.
        date_default_timezone_set('Asia/Bangkok');
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] $message\n";
        // Append the message to the log file.
        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }

    // Main logic for auto-returning lockers.
    try {
        $pdo = connect_db();

        // Get the current time in UTC, which is what the database uses.
        $current_utc_time = new DateTime('now', new DateTimeZone('UTC'));
        $current_utc_timestamp = $current_utc_time->format('Y-m-d H:i:s');

        log_message("INFO: Current UTC time is " . $current_utc_timestamp);

        // Find all lockers that are currently 'occupied' and whose 'end_time' has passed.
        $sql = "SELECT id, locker_number, end_time FROM lockers WHERE status = 'occupied' AND end_time <= :current_time";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':current_time', $current_utc_timestamp);
        $stmt->execute();
        $expired_lockers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if any expired lockers were found.
        if (count($expired_lockers) > 0) {
            log_message("INFO: Found " . count($expired_lockers) . " expired locker(s).");
            foreach ($expired_lockers as $locker) {
                $locker_id = $locker['id'];
                $locker_number = $locker['locker_number'];
                $locker_end_time = $locker['end_time'];

                // Log the details of the expired locker.
                log_message("INFO: Processing expired locker ID: " . $locker_id . ", locker_number: " . $locker_number . ", end_time: " . $locker_end_time);

                // Update the locker's status to 'available'.
                $update_sql = "UPDATE lockers SET status = 'available', user_email = NULL, start_time = NULL, end_time = NULL WHERE id = :id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->bindParam(':id', $locker_id);

                if ($update_stmt->execute()) {
                    log_message("INFO: Successfully returned locker ID: " . $locker_id . " to 'available'.");
                } else {
                    log_message("ERROR: Failed to update locker ID: " . $locker_id);
                }
            }
        } else {
            // Log if no expired lockers were found.
            log_message("INFO: No expired lockers found at this time.");
        }

        log_message("--- auto_return.php script finished ---");
        echo "Auto-return process completed successfully.";

    } catch (PDOException $e) {
        // Catch and log any PDO exceptions that occur during the process.
        log_message("FATAL ERROR: An error occurred during the auto-return process: " . $e->getMessage());
        echo "An error occurred.";
    }

} else {
    // This message is for command-line execution (not for cron jobs)
    echo "This script is intended to be run via a web request with a valid key.\n";
}

?>
