<?php
// --- CONFIG ---
$DB_HOSTNAME = "localhost";
$DB_USERNAME = "root";
$DB_PASSWORD = "training123";
$DB_TO_USE   = "childsafe";

$conn = new mysqli($DB_HOSTNAME, $DB_USERNAME, $DB_PASSWORD, $DB_TO_USE);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- SANITIZE FUNCTION ---
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// --- GET FORM DATA ---
$username = test_input($_POST["username"]);
$password = test_input($_POST["password"]);
$email    = test_input($_POST["email"]);
$phone    = test_input($_POST["phone"]);

if (empty($username) || empty($password) || empty($email) || empty($phone)) {
    die("Missing required fields");
}

// --- HASH PASSWORD ---
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$created = time();
$status  = "active";

// --- PREPARED STATEMENT ---
$stmt = $conn->prepare("INSERT INTO parent (p_username, p_password, p_email, p_phone_no, p_created, p_status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssis", $username, $hashed_password, $email, $phone, $created, $status);


// --- EXECUTE ---
if ($stmt->execute()) {
    header("Location: login.php?status=success");
    exit;
} else {
    echo "Error: " . $stmt->error;
}

// --- DEBUG: print SQL ---
//echo "<pre>$sql</pre>";

$stmt->close();
$conn->close();
?>
