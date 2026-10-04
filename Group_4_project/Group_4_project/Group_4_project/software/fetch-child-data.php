<?php
session_start();

if (!isset($_SESSION["p_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "training123";
$dbname = "childsafe";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$p_id = $_SESSION['p_id'];
$response = [];

// Fetch all children for this parent
$sql = "SELECT c_id, c_name, c_age, c_safe_radius FROM child WHERE p_id = '$p_id'";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $c_id = $row['c_id'];
    $safe_radius = floatval($row['c_safe_radius']);

    // Fetch latest location
    $loc_sql = "SELECT l_distance, l_range, l_timestamp FROM location WHERE c_id = '$c_id' ORDER BY l_id DESC LIMIT 1";
    $loc_result = $conn->query($loc_sql);
    $loc = $loc_result->fetch_assoc();

    if ($loc) {
        $distance = $loc['l_distance'] !== null ? floatval($loc['l_distance']) : null;
        $last_update = date("Y-m-d H:i:s", $loc['l_timestamp']);
    } else {
        $distance = null;
        $last_update = "No reading";
    }

    // Determine status and background color hex code
    $range_status = "No reading";
    $range_color = "808080"; // Grey

    if ($distance !== null) {
        if ($distance >= $safe_radius) {
            $range_status = "Out of Range";
            $range_color = "ef4444"; // Red
        } elseif ($distance >= ($safe_radius - 0.5) && $distance < $safe_radius) {
            $range_status = "Warning";
            $range_color = "facc15"; // Yellow
        } else {
            $range_status = "In Range";
            $range_color = "22c55e"; // Green
        }
    }

    $response[] = [
        "c_id" => $c_id,
        "distance" => $distance !== null ? $distance . " m" : "No reading",
        "status" => $range_status,
        "color" => $range_color,
        "last_update" => $last_update
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
