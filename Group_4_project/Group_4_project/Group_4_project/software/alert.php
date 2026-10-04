<?php
// alert.php
// - Reads the last 3 rows from the location table
// - If all 3 are 'out', sends one alert email
// - Inserts into alert table with status 'sent' or 'error'
// - Uses a flag file to avoid spamming (resets when an 'in' reading appears)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// ── DB connection ─────────────────────────────────────────────────────────────
$host   = "localhost";
$dbname = "childsafe";
$user   = "root";
$pass   = "training123";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// ── Flag file to prevent spam ─────────────────────────────────────────────────
$flag_dir = __DIR__ . '/alert_flags/';
if (!is_dir($flag_dir)) mkdir($flag_dir, 0777, true);

// ── Get distinct c_ids being tracked ─────────────────────────────────────────
$cid_result = $conn->query("SELECT DISTINCT c_id FROM location");
if (!$cid_result || $cid_result->num_rows === 0) {
    die("No location data found.");
}

while ($cid_row = $cid_result->fetch_assoc()) {
    $c_id      = $cid_row['c_id'];
    $flag_file = $flag_dir . "alert_sent_c{$c_id}.flag";

    // ── Fetch last 3 readings for this child ──────────────────────────────────
    $result = $conn->query(
        "SELECT l_range, l_distance, l_timestamp
         FROM location
         WHERE c_id = $c_id
         ORDER BY l_id DESC
         LIMIT 3"
    );

    if (!$result || $result->num_rows < 3) {
        echo "c_id=$c_id: Not enough readings yet (need 3).\n";
        continue;
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);

    // ── If any reading is 'in', reset the flag ────────────────────────────────
    $all_out = true;
    foreach ($rows as $row) {
        if ($row['l_range'] === 'in') {
            $all_out = false;
            break;
        }
    }

    if (!$all_out) {
        if (file_exists($flag_file)) {
            unlink($flag_file);
            echo "c_id=$c_id: Back in range. Alert flag reset.\n";
        } else {
            echo "c_id=$c_id: In range. No alert needed.\n";
        }
        continue;
    }

    // ── All 3 are 'out' — check if alert already sent ─────────────────────────
    if (file_exists($flag_file)) {
        echo "c_id=$c_id: All 3 OUT but alert already sent. Skipping.\n";
        continue;
    }

    // ── Get parent email via child table ──────────────────────────────────────
    $info_result = $conn->query(
        "SELECT child.c_name, child.c_safe_radius,
                parent.p_email, parent.p_username
         FROM child
         JOIN parent ON child.p_id = parent.p_id
         WHERE child.c_id = $c_id
         LIMIT 1"
    );

    if (!$info_result || $info_result->num_rows === 0) {
        echo "c_id=$c_id: No parent info found.\n";
        continue;
    }

    $info             = $info_result->fetch_assoc();
    $c_name           = $info['c_name'];
    $safe_radius      = $info['c_safe_radius'];
    $p_email          = $info['p_email'];
    $p_username       = $info['p_username'];
    $latest_distance  = $rows[0]['l_distance'];
    $latest_timestamp = date("d/m/Y H:i:s", $rows[0]['l_timestamp']);
    $now              = time();

    // ── Send email via PHPMailer ───────────────────────────────────────────────
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'childsafealert@gmail.com';
        $mail->Password   = 'cdik wzvb bthx jomr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('childsafealert@gmail.com', 'ChildSafe-Alert');
        $mail->addAddress($p_email, $p_username);

        $mail->isHTML(true);
        $mail->Subject = "[ChildSafe] ALERT: {$c_name} is out of range!";
        $mail->Body    = "Dear {$p_username},<br><br>"
                       . "Your child <strong>{$c_name}</strong> has been out of the safe radius "
                       . "for 3 consecutive readings.<br><br>"
                       . "<b>Latest Distance:</b> {$latest_distance} m<br>"
                       . "<b>Safe Radius:</b> {$safe_radius} m<br>"
                       . "<b>Time:</b> {$latest_timestamp}<br><br>"
                       . "Please check on your child immediately.<br><br>"
                       . "Regards,<br>ChildSafe System";
        $mail->AltBody = "Dear {$p_username},\n\n"
                       . "{$c_name} has been out of range for 3 consecutive readings.\n"
                       . "Latest Distance: {$latest_distance} m\n"
                       . "Safe Radius: {$safe_radius} m\n"
                       . "Time: {$latest_timestamp}\n\n"
                       . "Please check on your child immediately.";

        $mail->send();

        // ── Insert 'sent' into alert table ────────────────────────────────────
        $conn->query(
            "INSERT INTO alert (c_id, a_status, a_timestamp)
             VALUES ($c_id, 'sent', $now)"
        );

        // ── Write flag to prevent spam ────────────────────────────────────────
        file_put_contents($flag_file, $now);
        echo "c_id=$c_id: Alert email sent to {$p_email}. DB updated (sent).\n";

    } catch (Exception $e) {
        // ── Insert 'error' into alert table ───────────────────────────────────
        $conn->query(
            "INSERT INTO alert (c_id, a_status, a_timestamp)
             VALUES ($c_id, 'error', $now)"
        );
        echo "c_id=$c_id: Email failed. DB updated (error). {$mail->ErrorInfo}\n";
    }
}

$conn->close();
?>
