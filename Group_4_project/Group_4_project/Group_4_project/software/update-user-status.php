<?php
session_start();

header('Content-Type: application/json');

// Session authentication check
if (!isset($_SESSION["p_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $servername = "localhost";
    $username = "root";
    $password = "training123";
    $dbname = "childsafe";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        echo json_encode(["status" => "error", "message" => "Database connection failure."]);
        exit;
    }

    $p_id = $_SESSION['p_id'];
    $new_status = $_POST['status'];

    // Enforce matching database values strictly ('active' or 'inactive')
    if ($new_status !== 'active' && $new_status !== 'inactive') {
        echo json_encode(["status" => "error", "message" => "Invalid status value provided."]);
        exit;
    }

    // Secured using Prepared Statements
    $stmt = $conn->prepare("UPDATE parent SET p_status = ? WHERE p_id = ?");
    $stmt->bind_param("ss", $new_status, $p_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Status updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database execution failed."]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
