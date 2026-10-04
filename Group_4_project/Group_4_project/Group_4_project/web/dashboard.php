<link rel="icon" type="image/png" sizes="96x96" href="favicon//favicon-96x96.png">
<link rel="icon" href="favicon/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="favicon//favicon.ico">
<link rel="apple-touch-icon" href="favicon//apple-touch-icon.png">
<link rel="manifest" href="favicon//site.webmanifest">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
<?php
session_start();

// Gateway check
if (!isset($_SESSION["Username"]) || !isset($_SESSION["p_id"])) {
    header("Location: login.php?status=error");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "training123";
$dbname = "childsafe";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name = $_SESSION['Username'];
$p_id = $_SESSION['p_id'];

// --- Handle Delete Child Functionality (Using a Secure Transaction) ---
if (isset($_POST['delete_child'])) {
    $delete_c_id = $_POST['c_id'];

    // Begin transaction to guarantee both tables are updated smoothly together
    $conn->begin_transaction();

    try {
        // 1. Delete associated telemetry rows from the location table first (Foreign Key constraint safeguard)
        $stmt_loc = $conn->prepare("DELETE FROM location WHERE c_id = ?");
        $stmt_loc->bind_param("i", $delete_c_id);
        $stmt_loc->execute();
        $stmt_loc->close();

        // 2. Delete the profile row from the child table
        $stmt_child = $conn->prepare("DELETE FROM child WHERE c_id = ? AND p_id = ?");
        $stmt_child->bind_param("is", $delete_c_id, $p_id);
        $stmt_child->execute();
        $stmt_child->close();

        // Commit transaction changes completely to disk
        $conn->commit();
        echo "<script>alert('Child and historical location tracking parameters successfully dropped.'); window.location.href='dashboard.php';</script>";
    } catch (Exception $e) {
        // Rollback instantly if any database operation drops out mid-execution
        $conn->rollback();
        echo "<script>alert('Error processing extraction request: Data rollback initiated.');</script>";
    }
}

// --- Handle Add Child form submission (Secured with Prepared Statements) ---
if (isset($_POST['add_child'])) {
    $c_name = $_POST['c_name'];
    $c_age = $_POST['c_age'];
    $c_mac = $_POST['c_mac'];
    $c_safe_radius = $_POST['c_safe_radius'];

    $stmt = $conn->prepare("INSERT INTO child (p_id, c_name, c_age, c_mac, c_safe_radius) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $p_id, $c_name, $c_age, $c_mac, $c_safe_radius);

    if ($stmt->execute() === TRUE) {
        echo "<script>alert('Child added successfully!'); window.location.href='dashboard.php';</script>";
    } else {
        echo "<script>alert('Error adding child: " . htmlspecialchars($conn->error) . "');</script>";
    }
    $stmt->close();
}

// --- Fetch child data for this parent only ---
$safe_p_id = $conn->real_escape_string($p_id);
$sql = "SELECT * FROM child WHERE p_id = '$safe_p_id'";
$result = $conn->query($sql);

// Fetch the parent's current status to set the initial toggle state
$status_sql = "SELECT p_status FROM parent WHERE p_id = '" . $conn->real_escape_string($p_id) . "'";
$status_result = $conn->query($status_sql);
$status_row = $status_result->fetch_assoc();
$is_active = ($status_row && $status_row['p_status'] === 'active');

// Array to temporarily save items for single-pass loop generation
$children_cards_data = [];
$any_out_of_range = false;

while ($row = $result->fetch_assoc()) {
    $c_id = $row['c_id'];
    $safe_radius = $row['c_safe_radius'];

    $loc_sql = "SELECT l_distance, l_range, l_timestamp FROM location WHERE c_id = '$c_id' ORDER BY l_id DESC LIMIT 1";
    $loc_result = $conn->query($loc_sql);
    $loc = $loc_result->fetch_assoc();

    if ($loc) {
        $distance = $loc['l_distance'];
        $range_status = $loc['l_range'];
        $last_update = date("Y-m-d H:i:s", $loc['l_timestamp']);
    } else {
        $distance = null;
        $range_status = "No reading";
        $last_update = "No reading";
    }

    if ($distance !== null) {
        if ($distance >= $safe_radius) {
            $rangeColor = "ef4444"; 
            $any_out_of_range = true;
        } elseif ($distance >= ($safe_radius - 0.5) && $distance < $safe_radius) {
            $rangeColor = "facc15"; 
        } else {
            $rangeColor = "22c55e"; 
        }
    } else {
        $rangeColor = "808080"; 
    }

    $row['c_id'] = $c_id;
    $row['c_safe_radius'] = $safe_radius;
    $row['computed_distance'] = $distance;
    $row['computed_color'] = $rangeColor;
    $row['computed_last_update'] = $last_update;
    $children_cards_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap");
    
    body {
      font-family: "Poppins", sans-serif;
      background-color: #f8f9fa;
      padding-top: 85px; 
    }

    .dashboard-container {
      min-height: calc(100vh - 85px);
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 30px 0;
    }
    .custom-navbar {
      background-color: #ffffff;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .card-group {
      display: flex;
      flex-wrap: nowrap;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      height: 200px;
      margin-bottom: 20px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card-group:hover {
      transform: scale(1.02);
      box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    .child-card {
      flex: 1;
      background-color: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .child-photo {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #fff;
    }
    .info-card {
      flex: 2;
      background-color: #fff;
      border-left: 2px solid #fff;
      border-right: 2px solid #fff;
      padding: 1rem;
    }
    .range-card {
      flex: 1;
      color: #fff;
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: center;
      transition: background-color 0.5s ease;
    }
    .distance-display {
      font-size: 2rem;
      font-weight: 600;
    }
    .range-text {
      font-size: 1rem;
      font-weight: 500;
    }

    /* --- NEW OVERLAY HAZARD SYSTEM STYLES --- */
    .danger-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(4px);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease-in-out;
    }

    .danger-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }

    .critical-alert-circle {
      position: relative;
      width: 320px;
      height: 320px;
      background-color: #ef4444;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      box-shadow: 0 0 50px rgba(239, 68, 68, 0.6);
      text-align: center;
      padding: 20px;
      z-index: 2;
      transform: scale(0.8);
      transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .danger-overlay.active .critical-alert-circle {
      transform: scale(1);
    }

    /* Multi-layered dynamic radar pulse effects */
    .critical-alert-circle::before,
    .critical-alert-circle::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #ef4444;
      border-radius: 50%;
      z-index: -1;
      opacity: 0.4;
    }

    .danger-overlay.active .critical-alert-circle::before {
      animation: expandPing 2s infinite ease-out;
    }

    .danger-overlay.active .critical-alert-circle::after {
      animation: expandPing 2s infinite ease-out 0.8s;
    }

    @keyframes expandPing {
      0% {
        transform: scale(1);
        opacity: 0.5;
      }
      100% {
        transform: scale(2.2);
        opacity: 0;
      }
    }

    .alert-icon-wave {
      font-size: 4rem;
      animation: flashIcon 1s infinite alternate ease-in-out;
    }

    @keyframes flashIcon {
      0% { transform: scale(0.9); opacity: 0.8; }
      100% { transform: scale(1.1); opacity: 1; }
    }
  </style>
</head>

<body>

  <!-- BIG RED OVERLAY ALERT VIEWPORT CONTROLLER -->
<!-- BIG RED OVERLAY ALERT VIEWPORT CONTROLLER -->
  <div id="globalDangerOverlay" class="danger-overlay <?php echo $any_out_of_range ? 'active' : ''; ?>">
    <div class="critical-alert-circle">
      <h3 class="fw-bold m-0 mt-2">DANGER</h3>
      <p class="small text-white-50 uppercase tracking-wider mb-2" id="alertChildTarget">IS OUT OF RANGE</p>
      <div id="alertChildDistance" class="badge bg-white text-danger fs-5 px-3 py-1 fw-bold mb-3">0.00 m</div>
      
      <!-- Manual Acknowledgment Dismiss Button -->
      <button type="button" id="dismissOverlayBtn" class="btn btn-light text-danger fw-bold btn-sm px-4 rounded-pill shadow-sm">
        Acknowledge & Close
      </button>
    </div>
  </div>

  <nav class="navbar navbar-expand-lg custom-navbar fixed-top py-3">
    <div class="container-fluid px-4">
      <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
        <img src="img/logo.png" alt="ChildSafe Logo" height="40" class="d-inline-block align-top">
        <span style="font-size:2rem; padding-left:10px; color:#0f172a;">
          <strong>Child</strong><span style="font-weight:normal;">Safe</span>
        </span>
      </a>
      
      <div class="d-flex align-items-center ms-auto">
        <div class="form-check form-switch me-4">
          <input class="form-check-input" type="checkbox" id="userToggle" <?php echo $is_active ? 'checked' : ''; ?>>
          <label class="form-check-label fw-medium text-secondary" for="userToggle" id="toggleLabel">
            <?php echo $is_active ? 'User Active' : 'User Inactive'; ?>
          </label>
        </div>
        <form method="POST" action="server-logout.php" class="d-inline">
          <button type="submit" class="btn btn-outline-danger btn-sm px-3 fw-semibold">Logout</button>
        </form>
      </div>
    </div>
  </nav>

  <div class="container-fluid dashboard-container">
    <div class="w-75 mx-auto">
      
      <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <h3 class="fw-bold m-0" style="color: #0f172a;">Hi <?php echo htmlspecialchars($name); ?> 👋</h3>
        <button class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addChildModal">+ Add Child</button>
      </div>

      <div class="modal fade" id="addChildModal" tabindex="-1" aria-labelledby="addChildModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <form method="POST" action="">
              <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addChildModalLabel">Add New Child</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Child Name</label>
                  <input type="text" class="form-control" name="c_name" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Age</label>
                  <input type="number" class="form-control" name="c_age" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Device MAC</label>
                  <input type="text" class="form-control" name="c_mac" required>
                </div>
                <div class="mb-3">
                  <label for="safeRadius" class="form-label">Safe Radius (m)</label>
                  <input type="range" class="form-range" id="safeRadius" name="c_safe_radius" min="0" max="10" step="0.1" value="5" 
                  oninput="document.getElementById('radiusValue').textContent = this.value">
                  <span id="radiusValue">5</span> m
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" name="add_child">Add Child</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <?php 
      foreach ($children_cards_data as $row) {
          $c_id = $row['c_id'];
          $safe_radius = $row['c_safe_radius'];
          $distance = $row['computed_distance'];
          $rangeColor = $row['computed_color'];
          $last_update = $row['computed_last_update'];
      ?>
      
      <div class="card-group">
        <div class="child-card">
          <img src="img/child.jpg" alt="Child photo" class="child-photo">
        </div>

        <div class="info-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="ms-auto">
              <button class="btn btn-outline-danger btn-sm fw-medium" 
                      data-bs-toggle="modal" 
                      data-bs-target="#deleteChildModal<?php echo $c_id; ?>">
                Remove Child
              </button>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-6">
              <p class="mb-1 text-muted small">Child Name</p>
              <h6 class="fw-semibold child-card-name" style="color: #0f172a;"><?php echo htmlspecialchars($row['c_name']); ?></h6>
            </div>
            <div class="col-6">
              <p class="mb-1 text-muted small">Age</p>
              <h6 class="fw-semibold" style="color: #0f172a;"><?php echo htmlspecialchars($row['c_age']); ?></h6>
            </div>
          </div>
          <div class="mb-2">
            <p class="mb-1 text-muted small">Safe Radius</p>
            <h6 class="fw-semibold" style="color: #0f172a;"><?php echo htmlspecialchars($safe_radius); ?> m</h6>
          </div>
        </div>

        <div id="range-card-<?php echo $c_id; ?>" class="range-card" style="background-color:#<?php echo $rangeColor; ?>;">
          <div class="distance-display">
            <?php echo $distance !== null ? htmlspecialchars($distance) . " m" : "No reading"; ?>
          </div>
          <div class="range-text" style="font-size:1.5rem; font-weight:bold;" >
            <?php
              if ($distance === null) {
                echo "No reading";
              } elseif ($distance >= $safe_radius) {
                echo "Out of Range";
              } elseif ($distance >= ($safe_radius - 0.5) && $distance < $safe_radius) {
                echo "Warning";
              } else {
                echo "In Range";
              }
            ?>
          </div>
          <small class="text-light" style="padding-top:10px;">Last update: <span class="update-time"><?php echo htmlspecialchars($last_update); ?></span></small>
        </div>
      </div> 

      <div class="modal fade" id="deleteChildModal<?php echo $c_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <form method="POST" action="">
              <input type="hidden" name="c_id" value="<?php echo $c_id; ?>">
              <div class="modal-header">
                <h5 class="modal-title text-danger fw-bold">Remove <?php echo htmlspecialchars($row['c_name']); ?>?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-start" style="color: #333 !important;">
                <p class="mb-2">Are you sure you want to drop profile tracking parameters for <strong><?php echo htmlspecialchars($row['c_name']); ?></strong>?</p>
                <p class="text-muted small mb-0">⚠️ <em>Warning: This action completely clears all corresponding data logs collected inside the location system and cannot be undone.</em></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="delete_child" class="btn btn-danger">Confirm Removal</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <?php } ?>

    </div>
  </div>

  <script>
    // Tracks whether the parent has manually hidden the alert for the active breach event
    let hasMutedCurrentBreach = false;

    function updateDashboardData() {
        fetch('fetch-child-data.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'error') return;

                let criticalBreachOccurred = false;
                let breachTargetName = "";
                let breachTargetDistance = "";

                data.forEach(child => {
                    const card = document.getElementById(`range-card-${child.c_id}`);
                    if (card) {
                        card.style.backgroundColor = `#${child.color}`;
                        
                        const distanceDisplay = card.querySelector('.distance-display');
                        const rangeText = card.querySelector('.range-text');
                        const updateTime = card.querySelector('.update-time');
                        
                        if (distanceDisplay) distanceDisplay.textContent = child.distance;
                        if (rangeText) rangeText.textContent = child.status;
                        if (updateTime) updateTime.textContent = child.last_update;

                        const masterContainer = card.closest('.card-group');
                        let childName = "Child";
                        if (masterContainer) {
                            const nameEl = masterContainer.querySelector('.child-card-name');
                            if (nameEl) childName = nameEl.textContent;
                        }

                        if (child.status && child.status.toLowerCase() === 'out of range') {
                            criticalBreachOccurred = true;
                            breachTargetName = childName;
                            breachTargetDistance = child.distance;
                        }
                    }
                });

                const overlay = document.getElementById('globalDangerOverlay');
                const targetText = document.getElementById('alertChildTarget');
                const distanceBadge = document.getElementById('alertChildDistance');

                if (criticalBreachOccurred) {
                    // Keep layout information updated behind the scenes
                    targetText.textContent = `${breachTargetName.toUpperCase()} IS OUT OF RANGE`;
                    distanceBadge.textContent = breachTargetDistance;
                    
                    // Show visually only if the user hasn't dismissed this specific alert sequence
                    if (!hasMutedCurrentBreach) {
                        overlay.classList.add('active');
                    }
                } else {
                    // Everyone is safe! Reset the latch so it triggers automatically next time
                    hasMutedCurrentBreach = false;
                    overlay.classList.remove('active');
                }
            })
            .catch(error => console.error('Real-time synchronization issue:', error));
    }

    // Bind layout controls immediately upon DOM render completion
    document.addEventListener("DOMContentLoaded", function() {
        const dismissBtn = document.getElementById('dismissOverlayBtn');
        const overlay = document.getElementById('globalDangerOverlay');
        
        if (dismissBtn && overlay) {
            dismissBtn.addEventListener('click', function() {
                hasMutedCurrentBreach = true;
                overlay.classList.remove('active');
            });
        }
        
        // Start live sync interval loops
        setInterval(updateDashboardData, 1000);
    });
  </script>
  
  <script>
    document.getElementById('userToggle').addEventListener('change', function() {
        const isChecked = this.checked;
        const statusValue = isChecked ? 'active' : 'inactive';
        const label = document.getElementById('toggleLabel');
        
        label.textContent = isChecked ? 'User Active' : 'User Inactive';

        fetch('update-user-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `status=${statusValue}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                alert('Failed to update status: ' + data.message);
                this.checked = !isChecked;
                label.textContent = !isChecked ? 'User Active' : 'User Inactive';
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            alert('Network error. Status sync failed.');
            this.checked = !isChecked;
            label.textContent = !isChecked ? 'User Active' : 'User Inactive';
        });
    });
  </script>
</body>
</html>
