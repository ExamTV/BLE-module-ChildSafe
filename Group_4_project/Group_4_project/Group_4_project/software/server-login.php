<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "training123";
$dbname = "childsafe";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Get form data ---
$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

if (empty($user) || empty($pass)) {
    header("Location: login.php?status=error");
    exit;
}

// --- Look up parent ---
$stmt = $conn->prepare("SELECT p_id, p_username, p_password, p_status FROM parent WHERE p_username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();

    if (password_verify($pass, $row['p_password'])) {
        if ($row['p_status'] === 'active') {
            //Save both username and p_id in session
            $_SESSION['Username'] = $row['p_username'];
            $_SESSION['p_id'] = $row['p_id'];

            header("Location: dashboard.php");
            exit;
        } else {
            header("Location: login.php?status=inactive");
            exit;
        }
    } else {
        header("Location: login.php?status=error");
        exit;
    }
} else {
    header("Location: login.php?status=error");
    exit;
}

$stmt->close();
$conn->close();
?>
