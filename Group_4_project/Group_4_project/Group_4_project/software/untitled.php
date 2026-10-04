<?php
// Prevents PHP from timing out
set_time_limit(0);
ob_implicit_flush(true);
if (ob_get_level() > 0) ob_end_flush();

// Database configuration
$host     = 'localhost';
$dbname   = 'childsafe'; 
$username = 'root';      
$password = 'training123';               

$log_path = '/home/training/RSSI/btmgmt_rssi.log';

try {
    // 1. Connect to the database using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 2. Prepare SQL statements (including the new MAC address lookup)
    // Assumed child table name is 'child' based on columns like c_id, c_mac, c_name. Change if needed!
    $lookup_sql = "SELECT c_id FROM child WHERE LOWER(c_mac) = LOWER(:mac) LIMIT 1";
    $lookup_stmt = $pdo->prepare($lookup_sql);

    $insert_sql = "INSERT INTO location (c_id, l_distance, l_range, l_timestamp) VALUES (:c_id, :distance, :range, :timestamp)";
    $insert_stmt = $pdo->prepare($insert_sql);

    // Distance parameters from your original C code
    $txPower = -60.0; // Reference RSSI at 1 meter
    $n       = 4.0;   // Path loss exponent

    echo "Starting continuous log processor... (Press Ctrl+C to stop)\n\n";

    // Continuous loop wrapping the processing logic
    while (true) {

        // Check if the log file exists and is readable
        if (!file_exists($log_path)) {
            echo "Error: Log file not found at $log_path. Retrying in 5s...\n";
            sleep(5);
            continue;
        }

        $file_content = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($file_content === false) {
            echo "Error: Could not read the log file. Retrying in 5s...\n";
            sleep(5);
            continue;
        }

        $inserted_count = 0;

        // 3. Parse each line
        foreach ($file_content as $line) {
            if (preg_match('/Device\s+([0-9A-Fa-f:]{17})\s+RSSI:\s+(-?\d+)/', $line, $matches)) {
                $mac_address = $matches[1];
                $rssi        = (int)$matches[2]; 

                // --- NEW: FETCH c_id FROM DATABASE USING MAC ADDRESS ---
                $lookup_stmt->execute([':mac' => $mac_address]);
                $child = $lookup_stmt->fetch();

                if (!$child) {
                    // Skip if this MAC address doesn't belong to any registered child
                    continue; 
                }

                $c_id = $child['c_id'];

                // --- DISTANCE CALCULATION (Ported from your C program) ---
                $distance_meters = pow(10.0, ($txPower - $rssi) / (10.0 * $n));
                $final_distance = round($distance_meters, 1);

                // Determine range status (If less than 3 meters, it's 'in', else 'out')
                $range_status = ($final_distance < 3.0) ? 'in' : 'out';

                // --- DATABASE INSERTION ---
                $insert_stmt->execute([
                    ':c_id'      => $c_id,          // Dynamic c_id retrieved from database
                    ':distance'  => $final_distance, 
                    ':range'     => $range_status,   
                    ':timestamp' => time()           
                ]);
                
                $inserted_count++;
                echo "Inserted for Child ID {$c_id} ({$mac_address}): Distance = {$final_distance}m ({$range_status})\n";
            }
        }

        if ($inserted_count > 0) {
            echo "Batch finished! Total records inserted this cycle: $inserted_count\n\n";
        }

        // 4. Pause for 5 seconds before checking the log file again
        sleep(5);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
